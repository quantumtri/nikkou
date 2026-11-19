<?php

namespace App\Filament\Resources\Sops\Pages;

use App\Filament\Resources\Sops\SopResource;
use App\Models\Sop;
use App\Models\SopAssignment;
use App\Models\SopStep;
use App\Models\SopVersion;
use App\Models\User;
use App\Services\SopDiagram;
use App\Services\SopService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

class ViewSop extends ViewRecord
{
    protected static string $resource = SopResource::class;

    protected string $view = 'filament.sops.view';

    #[Url]
    public string $tab = 'alur';

    /** Version being shown; defaults to the active one, else the newest. */
    #[Url(as: 'versi')]
    public ?int $versionId = null;

    public ?int $compareFrom = null;

    public ?int $compareTo = null;

    public function getTitle(): string
    {
        /** @var Sop $sop */
        $sop = $this->getRecord();

        return $sop->code.' '.$sop->title;
    }

    public function getSubheading(): ?string
    {
        return 'Pemilik: '.($this->getRecord()->owner?->name ?? '-');
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['alur', 'langkah', 'versi'], true) ? $tab : 'alur';
    }

    private function sop(): Sop
    {
        /** @var Sop $sop */
        $sop = $this->getRecord();

        return $sop;
    }

    private function shownVersion(): ?SopVersion
    {
        $sop = $this->sop();
        $versions = $sop->versions()->get();

        if ($this->versionId && ($found = $versions->firstWhere('id', $this->versionId))) {
            // Non-admins only ever see the active version.
            if (auth()->user()->isAdmin() || $found->id === $sop->active_version_id) {
                return $found;
            }
        }

        return $versions->firstWhere('id', $sop->active_version_id) ?? (auth()->user()->isAdmin() ? $versions->first() : null);
    }

    protected function getHeaderActions(): array
    {
        $service = app(SopService::class);

        return [
            Action::make('acknowledge')
                ->label(fn () => 'Saya sudah membaca versi '.$this->sop()->activeVersion?->number)
                ->color('primary')
                ->visible(fn () => ($v = $this->sop()->activeVersion)
                    && $service->isAssigned($this->sop(), auth()->user())
                    && ! $v->acknowledgements()->where('user_id', auth()->id())->exists())
                ->action(function () use ($service) {
                    $service->acknowledge($this->sop()->activeVersion, auth()->user());
                    Notification::make()->title('Terima kasih, konfirmasi baca tercatat.')->success()->send();
                }),
            EditAction::make()->label('Ubah')->outlined()->visible(fn () => auth()->user()->can('update', $this->sop())),
            Action::make('approve')
                ->label('Setujui draf')
                ->color('primary')
                ->visible(fn () => auth()->user()->can('approve', $this->sop()) && $this->sop()->draftVersion() !== null)
                ->requiresConfirmation()
                ->modalHeading('Setujui draf ini?')
                ->modalDescription('Draf menjadi versi aktif, versi aktif sebelumnya diarsipkan, dan staf yang ditugaskan diminta membaca ulang.')
                ->action(function () use ($service) {
                    try {
                        $version = $service->publish($this->sop()->draftVersion(), auth()->user());
                    } catch (ValidationException $e) {
                        Notification::make()->title('Draf belum bisa disetujui')->body(collect($e->errors())->flatten()->implode(' '))->danger()->send();

                        return;
                    }
                    $this->versionId = $version->id;
                    $this->record->refresh();
                    Notification::make()->title('Versi '.$version->number.' sekarang aktif.')->success()->send();
                }),
            Action::make('assign')
                ->label('Atur penerima')
                ->outlined()
                ->visible(fn () => auth()->user()->can('update', $this->sop()))
                ->fillForm(fn () => [
                    'roles' => $this->sop()->assignments()->whereNotNull('role')->pluck('role')->all(),
                    'users' => $this->sop()->assignments()->whereNotNull('user_id')->pluck('user_id')->all(),
                ])
                ->schema([
                    CheckboxList::make('roles')->label('Seluruh peran')->options(['admin' => 'Admin', 'warehouse' => 'Gudang', 'sales' => 'Sales'])->columns(3),
                    Select::make('users')->label('Orang tertentu')->multiple()->searchable()
                        ->options(fn () => User::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
                ])
                ->action(function (array $data) {
                    $sop = $this->sop();
                    SopAssignment::where('sop_id', $sop->id)->delete();
                    foreach ($data['roles'] ?? [] as $role) {
                        SopAssignment::create(['sop_id' => $sop->id, 'role' => $role]);
                    }
                    foreach ($data['users'] ?? [] as $id) {
                        SopAssignment::create(['sop_id' => $sop->id, 'user_id' => $id]);
                    }
                    Notification::make()->title('Penerima SOP diperbarui.')->success()->send();
                }),
            Action::make('remind')
                ->label('Kirim pengingat baca')
                ->outlined()
                ->visible(fn () => auth()->user()->isAdmin() && $this->sop()->activeVersion !== null)
                ->action(function () use ($service) {
                    $n = $service->notifyAssigned($this->sop()->activeVersion);
                    Notification::make()->title($n ? "Pengingat dikirim ke {$n} orang." : 'Semua penerima sudah membaca.')->success()->send();
                }),
            Action::make('archive')
                ->label('Arsipkan')
                ->color('danger')
                ->outlined()
                ->visible(fn () => auth()->user()->can('approve', $this->sop()) && $this->sop()->status !== Sop::ARCHIVED)
                ->requiresConfirmation()
                ->modalDescription('SOP tidak lagi tampil di aplikasi sales. Riwayat versi tetap tersimpan.')
                ->action(function () use ($service) {
                    $service->archive($this->sop(), auth()->user());
                    $this->record->refresh();
                    Notification::make()->title('SOP diarsipkan.')->success()->send();
                }),
        ];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $service = app(SopService::class);
        $sop = $this->sop()->load('owner', 'activeVersion');
        $version = $this->shownVersion();
        $isAdmin = auth()->user()->isAdmin();

        $doc = $version ? $service->toDocument($version) : null;
        $versions = $isAdmin ? $sop->versions()->with(['creator:id,name', 'approver:id,name'])->get() : collect();

        $comparison = null;
        if ($isAdmin && $this->compareFrom && $this->compareTo && $this->compareFrom !== $this->compareTo) {
            $a = $versions->firstWhere('id', $this->compareFrom);
            $b = $versions->firstWhere('id', $this->compareTo);
            $comparison = $a && $b ? ['from' => $a, 'to' => $b, 'diff' => $service->compare($a, $b)] : null;
        }

        $readers = null;
        if ($isAdmin && $sop->activeVersion) {
            $acks = $sop->activeVersion->acknowledgements()->get()->keyBy('user_id');
            $readers = $service->assignedUsers($sop)->map(fn (User $u) => ['name' => $u->name, 'role' => $u->role->value, 'read_at' => $acks[$u->id]->read_at ?? null]);
        }

        return [
            'sop' => $sop,
            'version' => $version,
            'doc' => $doc,
            'svg' => $doc ? app(SopDiagram::class)->render($doc) : null,
            'stepData' => $doc ? collect($doc['steps'])->mapWithKeys(fn ($s) => [$s['key'] => $s + [
                'forms' => collect($doc['forms'])->where('step', $s['key'])->values()->all(),
                'type_label' => SopStep::TYPE_LABELS[$s['type']] ?? $s['type'],
            ]])->all() : [],
            'versions' => $versions,
            'comparison' => $comparison,
            'readers' => $readers,
            'isAdmin' => $isAdmin,
            'sharedForms' => $doc ? collect($doc['forms'])->whereNull('step')->values()->all() : [],
        ];
    }
}
