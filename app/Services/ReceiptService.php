<?php

namespace App\Services;

use App\Enums\UnitDocumentType;
use App\Enums\UnitStatus;
use App\Exceptions\ReceiptRuleViolation;
use App\Models\ActivityLog;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptCheck;
use App\Models\GoodsReceiptFile;
use App\Models\GoodsReceiptFinding;
use App\Models\GoodsReceiptItem;
use App\Models\Sequence;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Goods receipt (spec A18). All rules live here, not in the Filament pages:
 * numbering, pass/fail, the document lock on step 4, unit creation, finding handling,
 * and approval. Every status change is written to the append-only activity log.
 */
class ReceiptService
{
    public const CHECK_CODES = [
        'packaging_photographed', 'damage_noted', 'documents_match', 'model_quantity_ok', 'import_documents', 'delivery_note_signed',
    ];

    /** Checks whose "no" means the paperwork does not match the goods. */
    public const MISMATCH_CHECKS = ['documents_match', 'model_quantity_ok'];

    public function __construct(private readonly UnitService $units, private readonly UnitStatusService $unitStatus) {}

    // ------------------------------------------------------------------ creation and numbering

    /** @param  array{received_on?: ?string, po_number?: ?string, supplier?: ?string, forwarder?: ?string, tracking_number?: ?string, packages_expected?: ?int, from_paper?: bool}  $header */
    public function create(User $actor, array $header = []): GoodsReceipt
    {
        return DB::transaction(function () use ($actor, $header) {
            $date = isset($header['received_on']) ? Carbon::parse($header['received_on']) : today();

            $receipt = new GoodsReceipt($header);
            $receipt->received_on = $date;
            $receipt->number = $this->nextNumber($date);
            $receipt->created_by_id = $actor->id;
            $receipt->status = GoodsReceipt::DRAFT;
            $receipt->save();

            ActivityLog::record($receipt, 'receipt.created', $actor, null, GoodsReceipt::DRAFT, $receipt->number);

            return $receipt;
        });
    }

    /** PB-YYYYMM-0001, counting per month. Safe against two people saving at the same time. */
    public function nextNumber(Carbon $date): string
    {
        $period = $date->format('Ym');

        return sprintf('PB-%s-%04d', $period, Sequence::next("receipt:{$period}"));
    }

    // ------------------------------------------------------------------ steps 2 and 3: checks and the document lock

    public function setCheck(GoodsReceipt $receipt, string $code, bool $passed, ?string $notes = null): GoodsReceiptCheck
    {
        $receipt->refresh();
        $this->assertEditable($receipt);

        if (! in_array($code, self::CHECK_CODES, true)) {
            throw new ReceiptRuleViolation('Jenis pemeriksaan tidak dikenal.');
        }

        $check = GoodsReceiptCheck::query()->updateOrCreate(
            ['goods_receipt_id' => $receipt->id, 'code' => $code],
            ['passed' => $passed, 'notes' => $notes],
        );
        $receipt->unsetRelation('checks');

        return $check;
    }

    /** Documents or quantity/model do not match the goods. */
    public function hasMismatch(GoodsReceipt $receipt): bool
    {
        return $receipt->checks()->whereIn('code', self::MISMATCH_CHECKS)->where('passed', false)->exists();
    }

    /** Blocking findings that still stop the unit check. */
    public function blockingOpen(GoodsReceipt $receipt): int
    {
        return $receipt->findings()->where('is_blocking', true)->where('status', '!=', GoodsReceiptFinding::CLOSED)->whereNull('waived_at')->count();
    }

    /** Step 4 is locked while a mismatch has no finding, or a blocking finding is not closed or waived. */
    public function stepFourLocked(GoodsReceipt $receipt): bool
    {
        if ($this->hasMismatch($receipt) && ! $receipt->findings()->where('is_blocking', true)->exists()) {
            return true;
        }

        return $this->blockingOpen($receipt) > 0;
    }

    /** Called when leaving step 3: a mismatch must be recorded as a finding. */
    public function assertDocumentsHandled(GoodsReceipt $receipt): void
    {
        if ($this->hasMismatch($receipt) && ! $receipt->findings()->where('is_blocking', true)->exists()) {
            throw new ReceiptRuleViolation('Ada dokumen atau jumlah yang tidak cocok. Catat temuan lebih dulu sebelum lanjut.');
        }
    }

    /** Moves the receipt between draft and held as the document findings open and close. */
    public function syncHold(GoodsReceipt $receipt, ?User $actor = null): GoodsReceipt
    {
        if (! in_array($receipt->status, [GoodsReceipt::DRAFT, GoodsReceipt::HELD], true)) {
            return $receipt;
        }

        $target = $this->stepFourLocked($receipt) ? GoodsReceipt::HELD : GoodsReceipt::DRAFT;

        if ($target !== $receipt->status) {
            $this->setStatus($receipt, $target, $actor, $target === GoodsReceipt::HELD ? 'Dokumen atau jumlah tidak cocok.' : 'Semua temuan dokumen sudah ditutup.');
        }

        return $receipt;
    }

    // ------------------------------------------------------------------ steps 4 and 5: units and tests

    /** @param  array{unit_type_id?: ?int, serial_number?: ?string, components?: array<int, array{name: string, present: bool}>, physical_ok?: ?bool, notes?: ?string}  $data */
    public function addItem(GoodsReceipt $receipt, array $data): GoodsReceiptItem
    {
        $receipt->refresh();
        $this->assertEditable($receipt);

        if ($this->stepFourLocked($receipt)) {
            throw new ReceiptRuleViolation('Pemeriksaan unit terkunci sampai semua temuan dokumen ditutup atau disetujui pemilik untuk tetap terbuka.');
        }

        return DB::transaction(function () use ($receipt, $data) {
            $item = new GoodsReceiptItem($data);
            $item->goods_receipt_id = $receipt->id;
            $item->position = (int) $receipt->items()->max('position') + 1;
            $item->unit_code = $this->allocateUnitCode();
            $item->is_complete = $this->completeness($item);
            $this->assertSerialUnique($receipt, $item);
            $item->save();

            return $item;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function updateItem(GoodsReceiptItem $item, array $data): GoodsReceiptItem
    {
        $this->assertEditable($item->receipt);
        $this->assertNotSaved($item);

        $item->fill($data);
        $item->is_complete = $this->completeness($item);
        $this->assertSerialUnique($item->receipt, $item);
        $item->save();

        // Anything that feeds the result changed, so the stored result is recomputed.
        if ($item->result !== null) {
            $this->applyResult($item);
        }

        return $item;
    }

    /** All listed components present (and there is at least a checklist). */
    public function completeness(GoodsReceiptItem $item): ?bool
    {
        $components = $item->components;

        if (! is_array($components) || $components === []) {
            return null;
        }

        return collect($components)->every(fn ($c) => ! empty($c['present']));
    }

    /**
     * Record the function test. Pass only when powers on, outputs, no leak, no abnormal noise/heat,
     * physical condition good and complete are all true; otherwise fail, and a reason is required.
     *
     * @param  array{powers_on: bool, outputs: bool, no_leak: bool, no_abnormal: bool, fail_reason?: ?string}  $flags
     */
    public function recordTest(GoodsReceiptItem $item, array $flags, User $tester): GoodsReceiptItem
    {
        $this->assertEditable($item->receipt);
        $this->assertNotSaved($item);

        $item->fill(['physical_ok' => $item->physical_ok]);
        foreach (GoodsReceiptItem::TEST_FLAGS as $flag) {
            $item->{$flag} = (bool) ($flags[$flag] ?? false);
        }
        $item->tester_id = $tester->id;
        $item->fail_reason = isset($flags['fail_reason']) ? trim((string) $flags['fail_reason']) : null;

        $this->applyResult($item);
        $item->save();

        ActivityLog::record($item->receipt, 'receipt.item_tested', $tester, null, $item->result, "{$item->unit_code}: ".($item->passed() ? 'lolos' : 'gagal'));

        return $item;
    }

    /** One retest is allowed, and only after a failed test. */
    public function retest(GoodsReceiptItem $item, array $flags, User $tester): GoodsReceiptItem
    {
        if ($item->result !== GoodsReceiptItem::FAIL) {
            throw new ReceiptRuleViolation('Uji ulang hanya untuk unit yang gagal uji.');
        }

        if ($item->is_retest) {
            throw new ReceiptRuleViolation('Uji ulang hanya boleh satu kali.');
        }

        $item->is_retest = true;
        $item->save();

        return $this->recordTest($item, $flags, $tester);
    }

    public function computeResult(GoodsReceiptItem $item): string
    {
        $all = $item->physical_ok === true && $item->is_complete === true;

        foreach (GoodsReceiptItem::TEST_FLAGS as $flag) {
            $all = $all && $item->{$flag} === true;
        }

        return $all ? GoodsReceiptItem::PASS : GoodsReceiptItem::FAIL;
    }

    private function applyResult(GoodsReceiptItem $item): void
    {
        $item->result = $this->computeResult($item);

        if ($item->result === GoodsReceiptItem::PASS) {
            $item->fail_reason = null;

            return;
        }

        if (blank($item->fail_reason)) {
            throw new ReceiptRuleViolation("Unit {$item->unit_code} gagal uji: isi alasan gagal.");
        }
    }

    // ------------------------------------------------------------------ step 6: units and labels

    /**
     * "Simpan unit": creates the unit records. Passed units become available; failed or defective units
     * need service; everything is held while a document finding is still open (owner-waived).
     *
     * @return list<Unit>
     */
    public function saveUnits(GoodsReceipt $receipt, User $actor): array
    {
        $receipt->refresh();
        $this->assertEditable($receipt);

        if ($this->stepFourLocked($receipt)) {
            throw new ReceiptRuleViolation('Unit belum bisa disimpan: masih ada temuan dokumen yang terbuka.');
        }

        $items = $receipt->items()->with(['files', 'findings'])->get();

        if ($items->isEmpty()) {
            throw new ReceiptRuleViolation('Belum ada unit yang diperiksa.');
        }

        foreach ($items as $item) {
            foreach ($this->itemProblems($item, forSaving: true) as $problem) {
                throw new ReceiptRuleViolation($problem);
            }
        }

        return DB::transaction(function () use ($receipt, $actor, $items) {
            $created = [];
            $importDocuments = $receipt->files()->where('kind', GoodsReceiptFile::IMPORT_DOCUMENT)->get();

            foreach ($items as $item) {
                if ($item->unit_id) {
                    continue;
                }

                $status = $this->statusFor($receipt, $item);
                $unit = $this->units->create([
                    'code' => $item->unit_code,
                    'unit_type_id' => $item->unit_type_id,
                    'received_on' => $receipt->received_on->toDateString(),
                    'forwarder' => $receipt->forwarder,
                    'initial_notes' => $item->notes,
                    'components' => collect($item->components ?? [])->map(fn ($c) => ['name' => $c['name'], 'is_present' => (bool) $c['present']])->all(),
                    'documents' => $importDocuments->map(fn ($f) => [
                        'type' => $f->document_type ?: UnitDocumentType::Other->value,
                        'file_path' => $f->file_path,
                        'original_name' => $f->original_name,
                    ])->all(),
                    'photos' => $item->files->where('kind', GoodsReceiptFile::UNIT_PHOTO)->pluck('file_path')->all(),
                ], $actor);

                $unit->forceFill(['shelf' => $item->shelf])->save();

                if ($status !== UnitStatus::Available) {
                    $this->unitStatus->transition($unit, $status, $actor, "Penerimaan {$receipt->number}: ".$this->statusReason($receipt, $item, $status), 'unit.status_changed');
                }

                $item->forceFill(['unit_id' => $unit->id])->save();
                $created[] = $unit->refresh();
            }

            ActivityLog::record($receipt, 'receipt.units_saved', $actor, null, null, count($created).' unit disimpan.');

            return $created;
        });
    }

    private function statusFor(GoodsReceipt $receipt, GoodsReceiptItem $item): UnitStatus
    {
        // A document finding still open (kept open by the owner) quarantines the whole delivery.
        if ($receipt->findings()->where('is_blocking', true)->where('status', '!=', GoodsReceiptFinding::CLOSED)->exists()) {
            return UnitStatus::Held;
        }

        $defect = $item->findings->contains(fn ($f) => ! $f->isResolved() || in_array($f->type, ['unit_defect', 'failed_test', 'accessories_missing'], true));

        if (! $item->passed() || $item->physical_ok === false || $item->is_complete === false || $defect) {
            return UnitStatus::NeedsService;
        }

        return UnitStatus::Available;
    }

    private function statusReason(GoodsReceipt $receipt, GoodsReceiptItem $item, UnitStatus $status): string
    {
        return match ($status) {
            UnitStatus::Held => 'ditahan karena temuan dokumen.',
            default => $item->passed() ? 'cacat atau kelengkapan kurang.' : 'gagal uji: '.$item->fail_reason,
        };
    }

    /** "Label terbaca": scan the printed label once to confirm it reads back as the unit code. */
    public function confirmLabel(GoodsReceiptItem $item, string $scanned, User $actor): GoodsReceiptItem
    {
        if (! $item->unit_id) {
            throw new ReceiptRuleViolation('Unit belum disimpan, label belum bisa dikonfirmasi.');
        }

        if (strtoupper(trim($scanned)) !== strtoupper((string) $item->unit_code)) {
            throw new ReceiptRuleViolation("Hasil scan tidak sama dengan kode unit {$item->unit_code}. Cetak ulang atau scan label yang benar.");
        }

        $item->forceFill(['label_read_at' => now()])->save();
        ActivityLog::record($item->unit, 'unit.label_confirmed', $actor, notes: "Label terbaca (penerimaan {$item->receipt->number}).");

        return $item;
    }

    // ------------------------------------------------------------------ findings

    /** @param  array{type: string, description: string, action?: ?string, due_on?: ?string, responsible_id?: ?int, goods_receipt_item_id?: ?int}  $data */
    public function addFinding(GoodsReceipt $receipt, array $data, User $actor): GoodsReceiptFinding
    {
        if ($receipt->isCompleted()) {
            throw new ReceiptRuleViolation('Penerimaan sudah selesai; temuan baru tidak bisa ditambahkan.');
        }

        if (! isset(GoodsReceiptFinding::TYPES[$data['type']])) {
            throw new ReceiptRuleViolation('Jenis temuan tidak dikenal.');
        }

        return DB::transaction(function () use ($receipt, $data, $actor) {
            [$label, $days, $blocking] = GoodsReceiptFinding::TYPES[$data['type']];

            $finding = new GoodsReceiptFinding($data);
            $finding->goods_receipt_id = $receipt->id;
            $finding->due_on = $data['due_on'] ?? today()->addWeekdays($days);
            $finding->is_blocking = $blocking && empty($data['goods_receipt_item_id']);
            $finding->status = GoodsReceiptFinding::OPEN;
            $finding->created_by_id = $actor->id;
            $finding->save();

            ActivityLog::record($receipt, 'receipt.finding_added', $actor, null, GoodsReceiptFinding::OPEN, "{$label}: ".mb_substr($finding->description, 0, 120));
            $this->syncHold($receipt, $actor);

            return $finding;
        });
    }

    public function submitFinding(GoodsReceiptFinding $finding, User $actor): GoodsReceiptFinding
    {
        return $this->moveFinding($finding, GoodsReceiptFinding::SUBMITTED, $actor);
    }

    public function closeFinding(GoodsReceiptFinding $finding, User $actor): GoodsReceiptFinding
    {
        if (! $actor->isAdmin()) {
            throw new ReceiptRuleViolation('Hanya pemilik atau manajer yang bisa menutup temuan.');
        }

        return $this->moveFinding($finding, GoodsReceiptFinding::CLOSED, $actor);
    }

    /** The owner agrees the finding may stay open and the receipt can still be completed. */
    public function waiveFinding(GoodsReceiptFinding $finding, User $owner, string $reason): GoodsReceiptFinding
    {
        if (! $owner->isAdmin()) {
            throw new ReceiptRuleViolation('Hanya pemilik atau manajer yang bisa menyetujui temuan tetap terbuka.');
        }

        if (trim($reason) === '') {
            throw new ReceiptRuleViolation('Alasan persetujuan wajib diisi.');
        }

        return DB::transaction(function () use ($finding, $owner, $reason) {
            $finding->forceFill(['waived_at' => now(), 'waived_by_id' => $owner->id, 'waiver_reason' => trim($reason)])->save();
            ActivityLog::record($finding->receipt, 'receipt.finding_waived', $owner, $finding->status, 'waived', trim($reason));
            $this->syncHold($finding->receipt, $owner);

            return $finding;
        });
    }

    private function moveFinding(GoodsReceiptFinding $finding, string $to, User $actor): GoodsReceiptFinding
    {
        if ($finding->status === $to) {
            return $finding;
        }

        return DB::transaction(function () use ($finding, $to, $actor) {
            $from = $finding->status;
            $finding->status = $to;

            if ($to === GoodsReceiptFinding::CLOSED) {
                $finding->closed_at = now();
                $finding->closed_by_id = $actor->id;
            }

            $finding->save();
            ActivityLog::record($finding->receipt, 'receipt.finding_status', $actor, $from, $to, GoodsReceiptFinding::typeLabel($finding->type));
            $this->syncHold($finding->receipt, $actor);

            return $finding;
        });
    }

    // ------------------------------------------------------------------ step 7: finish and approve

    /** @return list<string> everything still preventing the receipt from being finished */
    public function readinessProblems(GoodsReceipt $receipt): array
    {
        $receipt->refresh();
        $problems = [];
        $items = $receipt->items()->with(['files'])->get();

        if ($receipt->status === GoodsReceipt::HELD) {
            $problems[] = 'Penerimaan masih ditahan karena temuan dokumen.';
        }

        if ($items->isEmpty()) {
            $problems[] = 'Belum ada unit.';
        }

        foreach ($items as $item) {
            foreach ($this->itemProblems($item) as $problem) {
                $problems[] = $problem;
            }
        }

        $open = $receipt->findings()->get()->reject->isResolved();
        foreach ($open as $finding) {
            $problems[] = 'Temuan belum ditutup: '.GoodsReceiptFinding::typeLabel($finding->type).'.';
        }

        if ($receipt->from_paper && ! $receipt->files()->where('kind', GoodsReceiptFile::PAPER_PHOTO)->exists()) {
            $problems[] = 'Foto lembar kertas wajib diunggah untuk data dari kertas.';
        }

        return $problems;
    }

    /** @return list<string> */
    private function itemProblems(GoodsReceiptItem $item, bool $forSaving = false): array
    {
        $label = $item->unit_code ?: "baris {$item->position}";
        $problems = [];

        if ($item->unit_type_id === null) {
            $problems[] = "Unit {$label}: tipe belum dipilih.";
        }

        if (! $item->files->contains('kind', GoodsReceiptFile::UNIT_PHOTO)) {
            $problems[] = "Unit {$label}: foto kondisi awal wajib.";
        }

        if ($item->result === null) {
            $problems[] = "Unit {$label}: hasil uji belum diisi.";
        }

        if (! $forSaving) {
            if (! $item->unit_id) {
                $problems[] = "Unit {$label}: belum disimpan.";
            }

            if (blank($item->shelf)) {
                $problems[] = "Unit {$label}: rak belum diisi.";
            }
        }

        return $problems;
    }

    public function submitForApproval(GoodsReceipt $receipt, User $actor): GoodsReceipt
    {
        $receipt->refresh();
        $this->assertEditable($receipt);

        if ($problems = $this->readinessProblems($receipt)) {
            throw new ReceiptRuleViolation(implode(' ', $problems));
        }

        return DB::transaction(function () use ($receipt, $actor) {
            $receipt->forceFill(['submitted_at' => now()])->save();
            $this->setStatus($receipt, GoodsReceipt::AWAITING_APPROVAL, $actor, 'Diajukan untuk persetujuan.');

            return $receipt;
        });
    }

    public function approve(GoodsReceipt $receipt, User $approver): GoodsReceipt
    {
        $receipt->refresh();
        if (! $approver->isAdmin()) {
            throw new ReceiptRuleViolation('Hanya pemilik atau manajer yang bisa menyetujui penerimaan.');
        }

        if ($receipt->status !== GoodsReceipt::AWAITING_APPROVAL) {
            throw new ReceiptRuleViolation('Penerimaan belum diajukan untuk persetujuan.');
        }

        if ($problems = $this->readinessProblems($receipt)) {
            throw new ReceiptRuleViolation(implode(' ', $problems));
        }

        // A second person must sign off whenever something went wrong (failed units or findings kept open).
        $troubled = $receipt->items()->where('result', GoodsReceiptItem::FAIL)->exists() || $receipt->findings()->whereNotNull('waived_at')->exists();

        if ($troubled && $approver->id === $receipt->created_by_id) {
            throw new ReceiptRuleViolation('Penerima tidak boleh menyetujui sendiri bila ada unit gagal atau temuan terbuka. Minta pemilik atau manajer lain.');
        }

        return DB::transaction(function () use ($receipt, $approver) {
            $receipt->forceFill(['approved_by_id' => $approver->id, 'approved_at' => now()])->save();
            $this->setStatus($receipt, GoodsReceipt::COMPLETED, $approver, 'Disetujui.');

            return $receipt;
        });
    }

    /** @return array{received: int, passed: int, failed: int, held: int, findings_open: int, findings_total: int} */
    public function summary(GoodsReceipt $receipt): array
    {
        $items = $receipt->items()->with('unit')->get();

        return [
            'received' => $items->count(),
            'passed' => $items->filter->passed()->count(),
            'failed' => $items->where('result', GoodsReceiptItem::FAIL)->count(),
            'held' => $items->filter(fn ($i) => $i->unit?->status === UnitStatus::Held)->count(),
            'findings_open' => $receipt->findings()->get()->reject->isResolved()->count(),
            'findings_total' => $receipt->findings()->count(),
        ];
    }

    // ------------------------------------------------------------------ header, files, shelf, removal (used by the wizard)

    /** @param  array{received_on?: ?string, po_number?: ?string, supplier?: ?string, forwarder?: ?string, tracking_number?: ?string, packages_expected?: ?int, packages_received?: ?int, from_paper?: bool}  $data */
    public function updateHeader(GoodsReceipt $receipt, array $data): GoodsReceipt
    {
        $receipt->refresh();
        $this->assertEditable($receipt);
        $receipt->fill($data)->save();

        return $receipt;
    }

    /**
     * Make the stored files of one kind match the given paths (new paths added, missing ones removed).
     *
     * @param  array<int, string>  $paths
     */
    public function syncFiles(GoodsReceipt $receipt, string $kind, array $paths, ?int $itemId = null, ?string $documentType = null, ?int $findingId = null): void
    {
        $this->assertEditableForFiles($receipt);

        $paths = array_values(array_filter($paths, fn ($p) => is_string($p) && $p !== ''));
        $query = GoodsReceiptFile::query()
            ->where('goods_receipt_id', $receipt->id)
            ->where('kind', $kind)
            ->where('goods_receipt_item_id', $itemId)
            ->where('goods_receipt_finding_id', $findingId)
            ->when($documentType, fn ($q) => $q->where('document_type', $documentType));

        $existing = $query->get();

        foreach ($existing->whereNotIn('file_path', $paths) as $gone) {
            $gone->delete();
        }

        foreach (array_diff($paths, $existing->pluck('file_path')->all()) as $path) {
            GoodsReceiptFile::create([
                'goods_receipt_id' => $receipt->id,
                'goods_receipt_item_id' => $itemId,
                'goods_receipt_finding_id' => $findingId,
                'kind' => $kind,
                'document_type' => $documentType,
                'file_path' => $path,
                'original_name' => basename($path),
            ]);
        }
    }

    /** A row that is not saved as a unit yet can be taken out again. */
    public function removeItem(GoodsReceiptItem $item): void
    {
        $this->assertEditable($item->receipt);
        $this->assertNotSaved($item);
        $item->delete();
    }

    /** Shelf can still be set after the unit exists, until the receipt is approved. */
    public function setShelf(GoodsReceiptItem $item, ?string $shelf, User $actor): GoodsReceiptItem
    {
        $this->assertEditable($item->receipt);

        $shelf = filled($shelf) ? trim($shelf) : null;
        $item->forceFill(['shelf' => $shelf])->save();
        $item->unit?->forceFill(['shelf' => $shelf])->save();

        return $item;
    }

    // ------------------------------------------------------------------ internals

    private function setStatus(GoodsReceipt $receipt, string $to, ?User $actor, ?string $note = null): void
    {
        $from = $receipt->status;
        $receipt->forceFill(['status' => $to])->save();
        ActivityLog::record($receipt, 'receipt.status_changed', $actor, $from, $to, $note);
    }

    private function assertEditable(GoodsReceipt $receipt): void
    {
        if ($receipt->status === GoodsReceipt::COMPLETED) {
            throw new ReceiptRuleViolation('Penerimaan yang sudah selesai tidak bisa diubah.');
        }

        if ($receipt->status === GoodsReceipt::AWAITING_APPROVAL) {
            throw new ReceiptRuleViolation('Penerimaan sedang menunggu persetujuan dan tidak bisa diubah.');
        }
    }

    /** Files may still be attached while the receipt waits for approval? No: only while it is a draft or held. */
    private function assertEditableForFiles(GoodsReceipt $receipt): void
    {
        $this->assertEditable($receipt);
    }

    private function assertNotSaved(GoodsReceiptItem $item): void
    {
        if ($item->unit_id) {
            throw new ReceiptRuleViolation("Unit {$item->unit_code} sudah disimpan. Koreksi dilakukan lewat halaman unit.");
        }
    }

    private function assertSerialUnique(GoodsReceipt $receipt, GoodsReceiptItem $item): void
    {
        if (blank($item->serial_number)) {
            return;
        }

        $duplicate = GoodsReceiptItem::query()
            ->where('goods_receipt_id', $receipt->id)
            ->where('serial_number', trim($item->serial_number))
            ->when($item->exists, fn ($q) => $q->whereKeyNot($item->id))
            ->exists();

        if ($duplicate) {
            throw new ReceiptRuleViolation("Nomor seri {$item->serial_number} sudah dipakai di penerimaan ini.");
        }

        $item->serial_number = trim($item->serial_number);
    }

    /** NK-#### codes handed out in order; skips codes already used by a unit or reserved on another receipt. */
    private function allocateUnitCode(): string
    {
        $seed = Unit::query()->where('code', 'like', 'NK-%')->pluck('code')
            ->map(fn (string $code) => (int) preg_replace('/\D/', '', $code))->max() ?? 0;

        do {
            $code = sprintf('NK-%04d', Sequence::next('unit_code', $seed));
        } while (Unit::query()->where('code', $code)->exists() || GoodsReceiptItem::query()->where('unit_code', $code)->exists());

        return $code;
    }
}
