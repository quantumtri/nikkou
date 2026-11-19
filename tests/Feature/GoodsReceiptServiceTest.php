<?php

use App\Enums\UnitStatus;
use App\Exceptions\ReceiptRuleViolation;
use App\Exceptions\UnitNotLoanable;
use App\Models\ActivityLog;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptFile;
use App\Models\GoodsReceiptItem;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Services\LoanService;
use App\Services\ReceiptService;

beforeEach(function () {
    $this->service = app(ReceiptService::class);
    $this->warehouse = User::factory()->warehouse()->create(['name' => 'Agus Gudang']);
    $this->owner = User::factory()->admin()->create(['name' => 'Rose Pemilik']);
    $this->type = UnitType::factory()->create(['name' => 'H2 Home']);

    $this->receipt = fn (array $header = [], ?User $by = null) => $this->service->create($by ?? $this->warehouse, [
        'received_on' => '2026-11-19', 'po_number' => 'PO-77', 'supplier' => 'PT Hidrogen Asia', 'forwarder' => 'PT Samudra Kargo', 'packages_expected' => 2, ...$header,
    ]);

    // An item that has everything needed to pass, plus its photo.
    $this->item = function (GoodsReceipt $receipt, array $overrides = [], bool $photo = true): GoodsReceiptItem {
        $item = $this->service->addItem($receipt, [
            'unit_type_id' => $this->type->id,
            'serial_number' => 'SN-'.fake()->unique()->numerify('#####'),
            'components' => [['name' => 'Unit utama', 'present' => true], ['name' => 'Selang', 'present' => true]],
            'physical_ok' => true,
            'shelf' => 'A1',
            ...$overrides,
        ]);

        if ($photo) {
            GoodsReceiptFile::create(['goods_receipt_id' => $receipt->id, 'goods_receipt_item_id' => $item->id, 'kind' => GoodsReceiptFile::UNIT_PHOTO, 'file_path' => "receipts/{$item->id}.jpg"]);
        }

        return $item->refresh();
    };

    $this->pass = fn (GoodsReceiptItem $item) => $this->service->recordTest($item, ['powers_on' => true, 'outputs' => true, 'no_leak' => true, 'no_abnormal' => true], $this->warehouse);
});

// ---------- numbering ----------
it('numbers receipts PB-YYYYMM-0001 and restarts every month', function () {
    $a = ($this->receipt)(['received_on' => '2026-11-03']);
    $b = ($this->receipt)(['received_on' => '2026-11-28']);
    $c = ($this->receipt)(['received_on' => '2026-12-01']);
    $d = ($this->receipt)(['received_on' => '2026-11-30']);

    expect([$a->number, $b->number, $c->number, $d->number])->toBe(['PB-202611-0001', 'PB-202611-0002', 'PB-202612-0001', 'PB-202611-0003']);
});

it('never repeats a receipt number, even for many in a row', function () {
    $numbers = collect(range(1, 25))->map(fn () => ($this->receipt)()->number);

    expect($numbers->unique())->toHaveCount(25)->and($numbers->last())->toBe('PB-202611-0025');
});

it('starts a receipt as draft with the creator, and logs it', function () {
    $receipt = ($this->receipt)();

    expect($receipt)->status->toBe('draft')->created_by_id->toBe($this->warehouse->id)->from_paper->toBeFalse();
    expect(ActivityLog::where('subject_type', 'goods_receipt')->where('action', 'receipt.created')->sole())->user_id->toBe($this->warehouse->id);
});

// ---------- pass / fail ----------
it('passes a unit only when every check is true', function () {
    $receipt = ($this->receipt)();
    $item = ($this->item)($receipt);

    $item = ($this->pass)($item);
    expect($item)->result->toBe('pass')->fail_reason->toBeNull()->tester_id->toBe($this->warehouse->id);
});

it('fails a unit when any single check is false and then needs a reason', function (string $flag) {
    $receipt = ($this->receipt)();
    $item = ($this->item)($receipt);
    $flags = ['powers_on' => true, 'outputs' => true, 'no_leak' => true, 'no_abnormal' => true, $flag => false];

    expect(fn () => $this->service->recordTest($item, $flags, $this->warehouse))->toThrow(ReceiptRuleViolation::class, 'alasan');

    $item = $this->service->recordTest($item, [...$flags, 'fail_reason' => 'Cek: '.$flag], $this->warehouse);
    expect($item->result)->toBe('fail')->and($item->fail_reason)->toBe('Cek: '.$flag);
})->with(['powers_on', 'outputs', 'no_leak', 'no_abnormal']);

it('fails a unit that is physically damaged or incomplete even if the function test is fine', function () {
    $receipt = ($this->receipt)();
    $damaged = ($this->item)($receipt, ['physical_ok' => false]);
    $missing = ($this->item)($receipt, ['components' => [['name' => 'Unit utama', 'present' => true], ['name' => 'Selang', 'present' => false]]]);
    $flags = ['powers_on' => true, 'outputs' => true, 'no_leak' => true, 'no_abnormal' => true, 'fail_reason' => 'Penyok / selang tidak ada'];

    expect($this->service->recordTest($damaged, $flags, $this->warehouse)->result)->toBe('fail')
        ->and($this->service->recordTest($missing, $flags, $this->warehouse))->result->toBe('fail')->is_complete->toBeFalse();
});

it('allows a single retest, and only after a failure', function () {
    $receipt = ($this->receipt)();
    $item = ($this->item)($receipt);
    $good = ['powers_on' => true, 'outputs' => true, 'no_leak' => true, 'no_abnormal' => true];

    expect(fn () => $this->service->retest($item, $good, $this->warehouse))->toThrow(ReceiptRuleViolation::class, 'gagal uji');

    $this->service->recordTest($item, [...$good, 'outputs' => false, 'fail_reason' => 'Output lemah'], $this->warehouse);
    $item = $this->service->retest($item->refresh(), $good, $this->warehouse);
    expect($item)->result->toBe('pass')->is_retest->toBeTrue();

    $this->service->recordTest($item, [...$good, 'no_leak' => false, 'fail_reason' => 'Bocor'], $this->warehouse);
    expect(fn () => $this->service->retest($item->refresh(), $good, $this->warehouse))->toThrow(ReceiptRuleViolation::class, 'satu kali');
});

it('keeps serial numbers unique within a receipt and gives each unit its own code', function () {
    $receipt = ($this->receipt)();
    $a = ($this->item)($receipt, ['serial_number' => 'SN-1']);
    $b = ($this->item)($receipt, ['serial_number' => 'SN-2']);

    expect($a->unit_code)->not->toBe($b->unit_code)->and($a->unit_code)->toMatch('/^NK-\d{4}$/');
    expect(fn () => ($this->item)($receipt, ['serial_number' => ' SN-1 ']))->toThrow(ReceiptRuleViolation::class, 'sudah dipakai');
});

// ---------- document lock ----------
it('locks step 4 when documents do not match, until the finding is closed', function () {
    $receipt = ($this->receipt)();
    $this->service->setCheck($receipt, 'documents_match', false, 'Invoice 10 unit, packing list 12');

    expect(fn () => $this->service->assertDocumentsHandled($receipt))->toThrow(ReceiptRuleViolation::class, 'Catat temuan');
    expect($this->service->stepFourLocked($receipt))->toBeTrue();

    $finding = $this->service->addFinding($receipt, ['type' => 'quantity_model_mismatch', 'description' => 'Invoice 10 unit, packing list 12'], $this->warehouse);
    expect($receipt->refresh()->status)->toBe('held')->and($finding->is_blocking)->toBeTrue();
    expect(fn () => ($this->item)($receipt))->toThrow(ReceiptRuleViolation::class, 'terkunci');

    $this->service->closeFinding($finding, $this->owner);
    expect($receipt->refresh()->status)->toBe('draft');
    expect($finding->refresh())->status->toBe('closed')->closed_by_id->toBe($this->owner->id);
    expect(($this->item)($receipt))->toBeInstanceOf(GoodsReceiptItem::class);
});

it('also unlocks step 4 when the owner agrees the finding may stay open', function () {
    $receipt = ($this->receipt)();
    $this->service->setCheck($receipt, 'model_quantity_ok', false);
    $finding = $this->service->addFinding($receipt, ['type' => 'documents_incomplete', 'description' => 'PIB belum ada'], $this->warehouse);

    expect(fn () => $this->service->waiveFinding($finding, $this->warehouse, 'boleh'))->toThrow(ReceiptRuleViolation::class, 'pemilik');
    expect(fn () => $this->service->waiveFinding($finding, $this->owner, '  '))->toThrow(ReceiptRuleViolation::class, 'Alasan');

    $this->service->waiveFinding($finding, $this->owner, 'PIB menyusul besok');
    expect($receipt->refresh()->status)->toBe('draft')->and($this->service->stepFourLocked($receipt))->toBeFalse();
});

it('does not lock step 4 for a matching delivery, or for non-blocking findings', function () {
    $receipt = ($this->receipt)();
    $this->service->setCheck($receipt, 'documents_match', true);
    $this->service->setCheck($receipt, 'model_quantity_ok', true);
    $this->service->addFinding($receipt, ['type' => 'damaged_packaging', 'description' => 'Kardus penyok'], $this->warehouse);

    expect($this->service->stepFourLocked($receipt))->toBeFalse()->and($receipt->refresh()->status)->toBe('draft');
});

it('lets only the owner close a finding', function () {
    $receipt = ($this->receipt)();
    $finding = $this->service->addFinding($receipt, ['type' => 'accessories_missing', 'description' => 'Selang kurang 2'], $this->warehouse);

    expect(fn () => $this->service->closeFinding($finding, $this->warehouse))->toThrow(ReceiptRuleViolation::class, 'pemilik');
    $this->service->submitFinding($finding, $this->warehouse);
    expect($finding->refresh()->status)->toBe('submitted');
});

it('sets the default due date from the finding type', function () {
    $this->travelTo('2026-11-19 09:00:00'); // Thursday
    $receipt = ($this->receipt)();

    $sameDay = $this->service->addFinding($receipt, ['type' => 'quantity_model_mismatch', 'description' => 'x'], $this->warehouse);
    $twoDays = $this->service->addFinding($receipt, ['type' => 'unit_defect', 'description' => 'y'], $this->warehouse);

    expect($sameDay->due_on->toDateString())->toBe('2026-11-19')->and($twoDays->due_on->toDateString())->toBe('2026-11-23'); // two working days: Fri, Mon
});

// ---------- saving units ----------
it('creates available units for passed items with code, date, forwarder, documents, photos and components', function () {
    $receipt = ($this->receipt)();
    GoodsReceiptFile::create(['goods_receipt_id' => $receipt->id, 'kind' => 'import_document', 'document_type' => 'invoice', 'file_path' => 'receipts/inv.pdf', 'original_name' => 'invoice.pdf']);
    $item = ($this->pass)(($this->item)($receipt, ['notes' => 'Segel utuh']));

    $units = $this->service->saveUnits($receipt, $this->warehouse);

    $unit = Unit::sole();
    expect($units)->toHaveCount(1)
        ->and($unit)->code->toBe($item->unit_code)->status->toBe(UnitStatus::Available)->forwarder->toBe('PT Samudra Kargo')->initial_notes->toBe('Segel utuh')->shelf->toBe('A1')
        ->and($unit->received_on->toDateString())->toBe('2026-11-19')
        ->and($unit->components->pluck('name')->all())->toBe(['Unit utama', 'Selang'])
        ->and($unit->documents)->toHaveCount(1)
        ->and($unit->photos)->toHaveCount(1)
        ->and($unit->type->id)->toBe($this->type->id)
        ->and($item->refresh()->unit_id)->toBe($unit->id);

    // Saving again changes nothing.
    expect($this->service->saveUnits($receipt, $this->warehouse))->toBe([]);
    expect(Unit::count())->toBe(1);
});

it('marks a failed unit as needs service and it cannot be lent', function () {
    $receipt = ($this->receipt)();
    $item = ($this->item)($receipt);
    $this->service->recordTest($item, ['powers_on' => false, 'outputs' => true, 'no_leak' => true, 'no_abnormal' => true, 'fail_reason' => 'Tidak menyala'], $this->warehouse);

    $this->service->saveUnits($receipt, $this->warehouse);

    $unit = Unit::sole();
    expect($unit->status)->toBe(UnitStatus::NeedsService);

    $sales = User::factory()->sales()->create();
    expect(fn () => app(LoanService::class)->create(['unit_id' => $unit->id, 'type' => 'event', 'event_name' => 'Pameran', 'starts_on' => today(), 'due_on' => today()], $sales))
        ->toThrow(UnitNotLoanable::class);
});

it('holds the units when a document finding stays open by the owner, and held units cannot be lent', function () {
    $receipt = ($this->receipt)();
    $this->service->setCheck($receipt, 'documents_match', false);
    $finding = $this->service->addFinding($receipt, ['type' => 'documents_incomplete', 'description' => 'PIB belum ada'], $this->warehouse);
    $this->service->waiveFinding($finding, $this->owner, 'PIB menyusul');
    ($this->pass)(($this->item)($receipt));

    $this->service->saveUnits($receipt, $this->warehouse);

    $unit = Unit::sole();
    expect($unit->status)->toBe(UnitStatus::Held)->and($unit->status->label())->toBe('Ditahan');

    $sales = User::factory()->sales()->create();
    expect(fn () => app(LoanService::class)->create(['unit_id' => $unit->id, 'type' => 'event', 'event_name' => 'Pameran', 'starts_on' => today(), 'due_on' => today()], $sales))
        ->toThrow(UnitNotLoanable::class, 'Ditahan');
});

it('does not save a unit without a photo, a type or a test result', function () {
    $receipt = ($this->receipt)();
    $noPhoto = ($this->item)($receipt, [], photo: false);
    ($this->pass)($noPhoto);

    expect(fn () => $this->service->saveUnits($receipt, $this->warehouse))->toThrow(ReceiptRuleViolation::class, 'foto kondisi awal');
    expect(Unit::count())->toBe(0);

    GoodsReceiptFile::create(['goods_receipt_id' => $receipt->id, 'goods_receipt_item_id' => $noPhoto->id, 'kind' => 'unit_photo', 'file_path' => 'x.jpg']);
    ($this->item)($receipt); // photo yes, test result missing

    expect(fn () => $this->service->saveUnits($receipt, $this->warehouse))->toThrow(ReceiptRuleViolation::class, 'hasil uji');
    expect(Unit::count())->toBe(0);
});

it('refuses to save units while the documents are locked', function () {
    $receipt = ($this->receipt)();
    $this->service->setCheck($receipt, 'documents_match', false);
    $this->service->addFinding($receipt, ['type' => 'quantity_model_mismatch', 'description' => 'x'], $this->warehouse);

    expect(fn () => $this->service->saveUnits($receipt, $this->warehouse))->toThrow(ReceiptRuleViolation::class, 'terbuka');
});

it('freezes a saved unit row', function () {
    $receipt = ($this->receipt)();
    $item = ($this->pass)(($this->item)($receipt));
    $this->service->saveUnits($receipt, $this->warehouse);

    expect(fn () => $this->service->updateItem($item->refresh(), ['notes' => 'ubah']))->toThrow(ReceiptRuleViolation::class, 'sudah disimpan')
        ->and(fn () => $this->service->recordTest($item->refresh(), ['powers_on' => true, 'outputs' => true, 'no_leak' => true, 'no_abnormal' => true], $this->warehouse))->toThrow(ReceiptRuleViolation::class);
});

it('confirms the label only when the scan matches the unit code', function () {
    $receipt = ($this->receipt)();
    $item = ($this->pass)(($this->item)($receipt));

    expect(fn () => $this->service->confirmLabel($item, $item->unit_code, $this->warehouse))->toThrow(ReceiptRuleViolation::class, 'belum disimpan');

    $this->service->saveUnits($receipt, $this->warehouse);
    $item->refresh();

    expect(fn () => $this->service->confirmLabel($item, 'NK-9999', $this->warehouse))->toThrow(ReceiptRuleViolation::class, 'tidak sama');
    expect($item->refresh()->label_read_at)->toBeNull();

    $this->service->confirmLabel($item, strtolower($item->unit_code).' ', $this->warehouse);
    expect($item->refresh()->label_read_at)->not->toBeNull();
});

// ---------- finishing ----------
it('refuses to finish when a unit has no photo or no shelf, or a finding is open', function () {
    $receipt = ($this->receipt)();
    $item = ($this->item)($receipt, ['shelf' => null], photo: false);
    ($this->pass)($item);

    expect(fn () => $this->service->submitForApproval($receipt, $this->warehouse))->toThrow(ReceiptRuleViolation::class, 'foto kondisi awal');

    GoodsReceiptFile::create(['goods_receipt_id' => $receipt->id, 'goods_receipt_item_id' => $item->id, 'kind' => 'unit_photo', 'file_path' => 'x.jpg']);
    expect(fn () => $this->service->submitForApproval($receipt, $this->warehouse))->toThrow(ReceiptRuleViolation::class, 'belum disimpan');

    $this->service->saveUnits($receipt, $this->warehouse);
    expect(fn () => $this->service->submitForApproval($receipt, $this->warehouse))->toThrow(ReceiptRuleViolation::class, 'rak belum diisi');

    $item->refresh()->forceFill(['shelf' => 'B2'])->save();
    $finding = $this->service->addFinding($receipt, ['type' => 'damaged_packaging', 'description' => 'Penyok'], $this->warehouse);
    expect(fn () => $this->service->submitForApproval($receipt, $this->warehouse))->toThrow(ReceiptRuleViolation::class, 'Temuan belum ditutup');

    $this->service->closeFinding($finding, $this->owner);
    expect($this->service->submitForApproval($receipt, $this->warehouse)->status)->toBe('awaiting_approval');
});

it('needs a photo of the paper sheet when the data comes from paper', function () {
    $receipt = ($this->receipt)(['from_paper' => true]);
    $item = ($this->pass)(($this->item)($receipt));
    $this->service->saveUnits($receipt, $this->warehouse);

    expect(fn () => $this->service->submitForApproval($receipt, $this->warehouse))->toThrow(ReceiptRuleViolation::class, 'lembar kertas');

    GoodsReceiptFile::create(['goods_receipt_id' => $receipt->id, 'kind' => 'paper_photo', 'file_path' => 'paper.jpg']);
    expect($this->service->submitForApproval($receipt, $this->warehouse)->status)->toBe('awaiting_approval');
});

it('does not let the receiver approve their own receipt when a unit failed', function () {
    $receipt = ($this->receipt)(by: $this->owner);
    $item = ($this->item)($receipt);
    $this->service->recordTest($item, ['powers_on' => false, 'outputs' => true, 'no_leak' => true, 'no_abnormal' => true, 'fail_reason' => 'Mati'], $this->owner);
    $this->service->saveUnits($receipt, $this->owner);
    $this->service->submitForApproval($receipt, $this->owner);

    expect(fn () => $this->service->approve($receipt, $this->owner))->toThrow(ReceiptRuleViolation::class, 'tidak boleh menyetujui sendiri');
    expect($receipt->refresh()->status)->toBe('awaiting_approval');

    $other = User::factory()->admin()->create();
    $done = $this->service->approve($receipt, $other);
    expect($done)->status->toBe('completed')->approved_by_id->toBe($other->id)->and($done->approved_at)->not->toBeNull();
});

it('does not let the receiver approve their own receipt when a finding was kept open', function () {
    $receipt = ($this->receipt)(by: $this->owner);
    $this->service->setCheck($receipt, 'documents_match', false);
    $finding = $this->service->addFinding($receipt, ['type' => 'documents_incomplete', 'description' => 'x'], $this->owner);
    $this->service->waiveFinding($finding, $this->owner, 'menyusul');
    ($this->pass)(($this->item)($receipt));
    $this->service->saveUnits($receipt, $this->owner);
    $this->service->submitForApproval($receipt, $this->owner);

    expect(fn () => $this->service->approve($receipt, $this->owner))->toThrow(ReceiptRuleViolation::class, 'tidak boleh menyetujui sendiri');
});

it('lets an admin approve their own clean receipt, and nobody else but admins approve', function () {
    $receipt = ($this->receipt)(by: $this->owner);
    ($this->pass)(($this->item)($receipt));
    $this->service->saveUnits($receipt, $this->owner);
    $this->service->submitForApproval($receipt, $this->owner);

    expect(fn () => $this->service->approve($receipt, $this->warehouse))->toThrow(ReceiptRuleViolation::class, 'pemilik');
    expect($this->service->approve($receipt, $this->owner)->status)->toBe('completed');
});

it('refuses to approve a receipt that was never submitted, and freezes a completed one', function () {
    $receipt = ($this->receipt)(by: $this->owner);
    ($this->pass)(($this->item)($receipt));

    expect(fn () => $this->service->approve($receipt, $this->owner))->toThrow(ReceiptRuleViolation::class, 'belum diajukan');

    $this->service->saveUnits($receipt, $this->owner);
    $this->service->submitForApproval($receipt, $this->owner);
    expect(fn () => ($this->item)($receipt))->toThrow(ReceiptRuleViolation::class, 'menunggu persetujuan');

    $this->service->approve($receipt, $this->owner);
    expect(fn () => $this->service->setCheck($receipt, 'documents_match', true))->toThrow(ReceiptRuleViolation::class, 'sudah selesai')
        ->and(fn () => $this->service->addFinding($receipt, ['type' => 'unit_defect', 'description' => 'x'], $this->owner))->toThrow(ReceiptRuleViolation::class, 'sudah selesai');
});

// ---------- summary and log ----------
it('summarises received, passed, failed, held and findings', function () {
    $receipt = ($this->receipt)();
    ($this->pass)(($this->item)($receipt));
    $bad = ($this->item)($receipt);
    $this->service->recordTest($bad, ['powers_on' => false, 'outputs' => true, 'no_leak' => true, 'no_abnormal' => true, 'fail_reason' => 'Mati'], $this->warehouse);
    $this->service->addFinding($receipt, ['type' => 'failed_test', 'description' => 'Mati', 'goods_receipt_item_id' => $bad->id], $this->warehouse);

    expect($this->service->summary($receipt))->toBe(['received' => 2, 'passed' => 1, 'failed' => 1, 'held' => 0, 'findings_open' => 1, 'findings_total' => 1]);
});

it('writes every status change, finding and approval to the append-only log', function () {
    $receipt = ($this->receipt)(by: $this->owner);
    $this->service->setCheck($receipt, 'documents_match', false);
    $finding = $this->service->addFinding($receipt, ['type' => 'quantity_model_mismatch', 'description' => 'x'], $this->owner);
    $this->service->waiveFinding($finding, $this->owner, 'menyusul');
    ($this->pass)(($this->item)($receipt));
    $this->service->saveUnits($receipt, $this->owner);
    $this->service->submitForApproval($receipt, User::factory()->admin()->create());
    $this->service->approve($receipt, User::factory()->admin()->create());

    $actions = ActivityLog::where('subject_type', 'goods_receipt')->where('subject_id', $receipt->id)->pluck('action')->all();

    expect($actions)->toContain('receipt.created', 'receipt.finding_added', 'receipt.finding_waived', 'receipt.item_tested', 'receipt.units_saved', 'receipt.status_changed');
    expect(ActivityLog::where('subject_type', 'goods_receipt')->where('action', 'receipt.status_changed')->pluck('to')->all())->toContain('held', 'draft', 'awaiting_approval', 'completed');
    expect(ActivityLog::where('subject_type', 'unit')->where('action', 'unit.created')->exists())->toBeTrue()
        ->and(ActivityLog::where('subject_type', 'unit')->where('to', 'held')->exists())->toBeTrue();
    expect(fn () => ActivityLog::first()->update(['notes' => 'ubah']))->toThrow(LogicException::class);
});
