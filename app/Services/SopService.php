<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Sop;
use App\Models\SopAcknowledgement;
use App\Models\SopAssignment;
use App\Models\SopEdge;
use App\Models\SopForm;
use App\Models\SopRole;
use App\Models\SopStep;
use App\Models\SopVersion;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Versioning, approval and read-confirmation rules for the SOP library.
 *
 * A SOP version is edited and compared as a plain "document" array:
 *   roles: [name, ...]
 *   steps: [{key, number, type, title, description, role, deadline, related_route}]
 *   edges: [{from, to, label, type}]   (from/to are step keys)
 *   forms: [{name, type, route_or_file, step}]  (step is a step key or null)
 */
class SopService
{
    /** @return array{roles: list<string>, steps: list<array<string, mixed>>, edges: list<array<string, mixed>>, forms: list<array<string, mixed>>} */
    public function toDocument(SopVersion $version): array
    {
        $version->load(['roles', 'steps.role', 'edges.from', 'edges.to', 'forms']);
        $keys = $version->steps->pluck('key', 'id');

        return [
            'roles' => $version->roles->pluck('name')->all(),
            'steps' => $version->steps->map(fn (SopStep $s) => [
                'key' => $s->key,
                'number' => $s->number,
                'type' => $s->type,
                'title' => $s->title,
                'description' => $s->description,
                'role' => $s->role?->name,
                'deadline' => $s->deadline,
                'related_route' => $s->related_route,
            ])->values()->all(),
            'edges' => $version->edges->map(fn (SopEdge $e) => [
                'from' => $e->from->key,
                'to' => $e->to->key,
                'label' => $e->label,
                'type' => $e->type,
            ])->values()->all(),
            'forms' => $version->forms->map(fn (SopForm $f) => [
                'name' => $f->name,
                'type' => $f->type,
                'route_or_file' => $f->route_or_file,
                'step' => $f->sop_step_id ? $keys[$f->sop_step_id] ?? null : null,
            ])->values()->all(),
        ];
    }

    /**
     * Validate the document and throw a ValidationException (keyed by "document")
     * when the flow cannot be drawn.
     *
     * @param  array<string, mixed>  $doc
     */
    public function validate(array $doc): void
    {
        $errors = [];
        $steps = collect($doc['steps'] ?? []);
        $roles = collect($doc['roles'] ?? [])->map(fn ($r) => trim((string) $r))->filter();
        $keys = $steps->pluck('key');

        if ($roles->count() !== $roles->unique()->count()) {
            $errors[] = 'Nama peran tidak boleh sama.';
        }
        if ($keys->count() !== $keys->unique()->count() || $keys->contains(fn ($k) => trim((string) $k) === '')) {
            $errors[] = 'Setiap langkah butuh kode unik.';
        }
        if ($steps->where('type', SopStep::START)->count() > 1) {
            $errors[] = 'Alur hanya boleh punya satu titik Mulai.';
        }
        if ($steps->where('type', SopStep::END)->count() < 1) {
            $errors[] = 'Alur harus punya minimal satu titik Selesai.';
        }
        foreach ($steps as $step) {
            if (! array_key_exists($step['type'] ?? null, SopStep::TYPE_LABELS)) {
                $errors[] = 'Jenis langkah tidak dikenal: '.($step['key'] ?? '?').'.';
            }
            if (trim((string) ($step['title'] ?? '')) === '') {
                $errors[] = 'Langkah '.($step['key'] ?? '?').' belum punya judul.';
            }
            if (! empty($step['role']) && ! $roles->contains($step['role'])) {
                $errors[] = 'Langkah '.$step['key'].' memakai peran yang tidak ada.';
            }
        }
        foreach ($doc['edges'] ?? [] as $edge) {
            if (! $keys->contains($edge['from'] ?? null) || ! $keys->contains($edge['to'] ?? null)) {
                $errors[] = 'Ada panah yang menuju langkah yang tidak ada.';
            }
            if (! array_key_exists($edge['type'] ?? 'normal', SopEdge::TYPE_LABELS)) {
                $errors[] = 'Jenis panah tidak dikenal.';
            }
        }
        foreach ($doc['forms'] ?? [] as $form) {
            if (! array_key_exists($form['type'] ?? null, SopForm::TYPE_LABELS) || trim((string) ($form['route_or_file'] ?? '')) === '') {
                $errors[] = 'Formulir terkait "'.($form['name'] ?? '?').'" belum lengkap.';
            }
            if (! empty($form['step']) && ! $keys->contains($form['step'])) {
                $errors[] = 'Formulir terkait menunjuk langkah yang tidak ada.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages(['document' => array_values(array_unique($errors))]);
        }
    }

    /**
     * Save the document as a draft version. An open draft is replaced in place;
     * otherwise a new version number is opened (copy-on-edit of the active one).
     *
     * @param  array<string, mixed>  $doc
     */
    public function saveDraft(Sop $sop, array $doc, User $by, ?string $summary = null): SopVersion
    {
        $this->validate($doc);

        return DB::transaction(function () use ($sop, $doc, $by, $summary) {
            $sop->refresh();
            $draft = $sop->draftVersion();

            if ($draft) {
                $draft->steps()->delete();
                $draft->roles()->delete();
                $draft->forms()->delete();
                $draft->edges()->delete();
                $draft->update(['change_summary' => $summary ?? $draft->change_summary]);
            } else {
                $number = ((int) $sop->versions()->max('number')) + 1;
                $draft = new SopVersion(['sop_id' => $sop->id, 'number' => $number, 'change_summary' => $summary]);
                $draft->created_by_id = $by->id;
                $draft->save();
            }

            $this->writeDocument($draft, $doc);
            ActivityLog::record($sop, 'sop.draft_saved', $by, null, $draft->label(), $summary);

            return $draft->refresh();
        });
    }

    /** @param  array<string, mixed>  $doc */
    private function writeDocument(SopVersion $version, array $doc): void
    {
        $roleIds = [];
        foreach (array_values($doc['roles'] ?? []) as $i => $name) {
            $roleIds[$name] = SopRole::create(['sop_version_id' => $version->id, 'name' => trim($name), 'position' => $i + 1])->id;
        }

        $stepIds = [];
        foreach (array_values($doc['steps'] ?? []) as $i => $s) {
            $stepIds[$s['key']] = SopStep::create([
                'sop_version_id' => $version->id,
                'key' => $s['key'],
                'number' => $s['type'] === SopStep::STEP ? ($s['number'] ?? null) : null,
                'type' => $s['type'],
                'title' => $s['title'],
                'description' => $s['description'] ?? null,
                'sop_role_id' => ! empty($s['role']) ? ($roleIds[$s['role']] ?? null) : null,
                'deadline' => $s['deadline'] ?? null,
                'related_route' => $s['related_route'] ?? null,
                'position' => $i + 1,
            ])->id;
        }

        foreach ($doc['edges'] ?? [] as $e) {
            SopEdge::create([
                'sop_version_id' => $version->id,
                'from_step_id' => $stepIds[$e['from']],
                'to_step_id' => $stepIds[$e['to']],
                'label' => $e['label'] ?? null,
                'type' => $e['type'] ?? SopEdge::NORMAL,
            ]);
        }

        foreach ($doc['forms'] ?? [] as $f) {
            SopForm::create([
                'sop_version_id' => $version->id,
                'sop_step_id' => ! empty($f['step']) ? $stepIds[$f['step']] : null,
                'name' => $f['name'],
                'type' => $f['type'],
                'route_or_file' => $f['route_or_file'],
            ]);
        }
    }

    /** Only the owner (admin) approves; the draft becomes the single active version. */
    public function publish(SopVersion $version, User $approver, ?string $effectiveFrom = null): SopVersion
    {
        if (! $approver->isAdmin()) {
            throw ValidationException::withMessages(['version' => 'Hanya pemilik yang boleh menyetujui SOP.']);
        }

        $doc = $this->toDocument($version);
        $this->validate($doc);

        DB::transaction(function () use ($version, $approver, $effectiveFrom) {
            $version->refresh();
            if ($version->status !== SopVersion::DRAFT) {
                throw ValidationException::withMessages(['version' => 'Hanya versi draf yang bisa disetujui.']);
            }

            $sop = Sop::query()->lockForUpdate()->findOrFail($version->sop_id);
            SopVersion::where('sop_id', $sop->id)->where('status', SopVersion::ACTIVE)->update(['status' => SopVersion::ARCHIVED]);

            $version->forceFill([
                'status' => SopVersion::ACTIVE,
                'approved_by_id' => $approver->id,
                'approved_at' => now(),
                'effective_from' => $effectiveFrom ?? now()->toDateString(),
            ])->save();

            $sop->forceFill([
                'status' => Sop::ACTIVE,
                'active_version_id' => $version->id,
                'review_on' => now()->addYear()->toDateString(),
            ])->save();

            ActivityLog::record($sop, 'sop.approved', $approver, null, $version->label());
        });

        $version->refresh();
        $this->notifyAssigned($version);

        return $version;
    }

    public function archive(Sop $sop, User $by): void
    {
        if (! $by->isAdmin()) {
            throw ValidationException::withMessages(['sop' => 'Hanya pemilik yang boleh mengarsipkan SOP.']);
        }
        DB::transaction(function () use ($sop, $by) {
            SopVersion::where('sop_id', $sop->id)->whereIn('status', [SopVersion::ACTIVE, SopVersion::DRAFT])->update(['status' => SopVersion::ARCHIVED]);
            $sop->forceFill(['status' => Sop::ARCHIVED, 'active_version_id' => null])->save();
            ActivityLog::record($sop, 'sop.archived', $by);
        });
    }

    /** Everyone who must read this SOP: named users plus everyone in an assigned role. */
    public function assignedUsers(Sop $sop): Collection
    {
        $assignments = SopAssignment::where('sop_id', $sop->id)->get();
        $roles = $assignments->pluck('role')->filter()->all();
        $ids = $assignments->pluck('user_id')->filter()->all();

        return User::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereIn('id', $ids)->orWhereIn('role', $roles))
            ->orderBy('name')
            ->get();
    }

    public function isAssigned(Sop $sop, User $user): bool
    {
        return $this->assignedUsers($sop)->contains('id', $user->id);
    }

    /** @return Collection<int, User> */
    public function pendingReaders(SopVersion $version): Collection
    {
        $read = $version->acknowledgements()->pluck('user_id');

        return $this->assignedUsers($version->sop)->reject(fn (User $u) => $read->contains($u->id))->values();
    }

    public function acknowledge(SopVersion $version, User $user): SopAcknowledgement
    {
        if ($version->status !== SopVersion::ACTIVE) {
            throw ValidationException::withMessages(['version' => 'Hanya versi aktif yang bisa dikonfirmasi.']);
        }
        if (! $this->isAssigned($version->sop, $user)) {
            throw ValidationException::withMessages(['version' => 'SOP ini tidak ditugaskan kepada Anda.']);
        }

        $ack = SopAcknowledgement::firstOrCreate(
            ['sop_version_id' => $version->id, 'user_id' => $user->id],
            ['read_at' => now()],
        );
        if ($ack->wasRecentlyCreated) {
            ActivityLog::record($version->sop, 'sop.acknowledged', $user, null, $version->label());
        }

        return $ack;
    }

    /** Active SOPs a user is assigned to that they have not confirmed yet. */
    public function unreadFor(User $user): Collection
    {
        return Sop::query()->where('status', Sop::ACTIVE)->with('activeVersion')->get()
            ->filter(fn (Sop $sop) => $sop->activeVersion
                && $this->isAssigned($sop, $user)
                && ! $sop->activeVersion->acknowledgements()->where('user_id', $user->id)->exists())
            ->values();
    }

    /** Active SOPs visible to a user (assigned to them), for the sales app. */
    public function visibleTo(User $user): Collection
    {
        return Sop::query()->where('status', Sop::ACTIVE)->with('activeVersion')->orderBy('code')->get()
            ->filter(fn (Sop $sop) => $this->isAssigned($sop, $user))->values();
    }

    public function notifyAssigned(SopVersion $version): int
    {
        $sop = $version->sop;
        $count = 0;
        foreach ($this->pendingReaders($version) as $user) {
            $this->notify($user, 'SOP baru perlu dibaca', "{$sop->code} {$sop->title} — {$version->label()}. Buka SOP lalu tekan \"Saya sudah membaca\".");
            $count++;
        }

        return $count;
    }

    /** Remind staff who still have not confirmed (cache-throttled by the command). */
    public function remindUnread(): int
    {
        $count = 0;
        Sop::where('status', Sop::ACTIVE)->with('activeVersion')->get()->each(function (Sop $sop) use (&$count) {
            foreach ($this->pendingReaders($sop->activeVersion) as $user) {
                // At most one automatic reminder per person and version every 3 days.
                if (! Cache::add("sop-remind:{$sop->activeVersion->id}:{$user->id}", true, now()->addDays(3))) {
                    continue;
                }
                $this->notify($user, 'Pengingat: SOP belum dibaca', "{$sop->code} {$sop->title} — {$sop->activeVersion->label()} belum Anda konfirmasi.");
                $count++;
            }
        });

        return $count;
    }

    /** Tell admins when a SOP is due for review within 30 days (or overdue). */
    public function remindReviews(): int
    {
        $count = 0;
        $admins = User::where('role', UserRole::Admin)->where('is_active', true)->get();
        Sop::where('status', Sop::ACTIVE)->whereNotNull('review_on')->whereDate('review_on', '<=', now()->addDays(30))->get()
            ->each(function (Sop $sop) use ($admins, &$count) {
                foreach ($admins as $admin) {
                    if (! Cache::add("sop-review:{$sop->id}:{$admin->id}", true, now()->addDays(7))) {
                        continue;
                    }
                    $this->notify($admin, 'SOP perlu ditinjau', "{$sop->code} {$sop->title} — tinjauan berikutnya {$sop->review_on->format('d/m/Y')}.");
                    $count++;
                }
            });

        return $count;
    }

    private function notify(User $user, string $title, string $body): void
    {
        $user->notifyNow(Notification::make()->title($title)->body($body)->toDatabase());
    }

    /**
     * Compare two versions: what was added, removed or changed.
     *
     * @return array{steps: list<array{change: string, key: string, title: string, fields: list<string>}>, edges: list<string>, roles: list<string>, forms: list<string>}
     */
    public function compare(SopVersion $old, SopVersion $new): array
    {
        $a = $this->toDocument($old);
        $b = $this->toDocument($new);

        $oldSteps = collect($a['steps'])->keyBy('key');
        $newSteps = collect($b['steps'])->keyBy('key');
        $steps = [];

        foreach ($newSteps as $key => $s) {
            if (! $oldSteps->has($key)) {
                $steps[] = ['change' => 'added', 'key' => $key, 'title' => $s['title'], 'fields' => []];

                continue;
            }
            $changed = collect(['title', 'description', 'type', 'role', 'deadline', 'related_route', 'number'])
                ->filter(fn ($f) => ($oldSteps[$key][$f] ?? null) !== ($s[$f] ?? null))->values()->all();
            if ($changed) {
                $steps[] = ['change' => 'changed', 'key' => $key, 'title' => $s['title'], 'fields' => $changed];
            }
        }
        foreach ($oldSteps as $key => $s) {
            if (! $newSteps->has($key)) {
                $steps[] = ['change' => 'removed', 'key' => $key, 'title' => $s['title'], 'fields' => []];
            }
        }

        $edgeLabel = fn (array $e) => "{$e['from']} → {$e['to']}".($e['label'] ? " ({$e['label']})" : '').($e['type'] !== 'normal' ? " [{$e['type']}]" : '');
        $oldEdges = collect($a['edges'])->map($edgeLabel);
        $newEdges = collect($b['edges'])->map($edgeLabel);
        $edges = array_merge(
            $newEdges->diff($oldEdges)->map(fn ($e) => "+ $e")->all(),
            $oldEdges->diff($newEdges)->map(fn ($e) => "− $e")->all(),
        );

        $roles = array_merge(
            collect($b['roles'])->diff($a['roles'])->map(fn ($r) => "+ $r")->all(),
            collect($a['roles'])->diff($b['roles'])->map(fn ($r) => "− $r")->all(),
        );

        $formLabel = fn (array $f) => $f['name'].' ('.$f['route_or_file'].')';
        $oldForms = collect($a['forms'])->map($formLabel);
        $newForms = collect($b['forms'])->map($formLabel);
        $forms = array_merge(
            $newForms->diff($oldForms)->map(fn ($f) => "+ $f")->all(),
            $oldForms->diff($newForms)->map(fn ($f) => "− $f")->all(),
        );

        return compact('steps', 'edges', 'roles', 'forms');
    }
}
