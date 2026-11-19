<?php

use App\Filament\Resources\Receipts\Pages\CreateReceipt;
use App\Filament\Resources\Receipts\Pages\EditReceipt;
use App\Filament\Resources\Receipts\Pages\ListReceipts;
use App\Filament\Resources\Receipts\Pages\ViewReceipt;
use App\Filament\Resources\Receipts\ReceiptResource;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptFile;
use App\Models\GoodsReceiptItem;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Services\ReceiptService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->warehouse = User::factory()->warehouse()->create();
    $this->owner = User::factory()->admin()->create();
    $this->type = UnitType::factory()->create(['name' => 'H2 Home', 'default_components' => ['Unit utama', 'Selang', 'Kanula']]);
    $this->service = app(ReceiptService::class);
});

it('lets admin and warehouse staff into the receipt pages but not sales', function () {
    $receipt = $this->service->create($this->warehouse, ['received_on' => today()->toDateString()]);

    foreach ([$this->owner, $this->warehouse] as $user) {
        $this->actingAs($user);
        $this->get(ReceiptResource::getUrl('index'))->assertOk();
        $this->get(ReceiptResource::getUrl('create'))->assertOk();
        $this->get(ReceiptResource::getUrl('edit', ['record' => $receipt]))->assertOk();
        $this->get(ReceiptResource::getUrl('view', ['record' => $receipt]))->assertOk();
    }

    $sales = User::factory()->sales()->create();
    foreach (['index', 'create'] as $page) {
        $this->actingAs($sales)->get(ReceiptResource::getUrl($page))->assertForbidden();
    }
    $this->actingAs($sales)->get(ReceiptResource::getUrl('view', ['record' => $receipt]))->assertForbidden();
});

it('creates a numbered draft from the first step and lists it', function () {
    $this->actingAs($this->warehouse);

    Livewire::test(CreateReceipt::class)
        ->fillForm(['received_on' => today()->toDateString(), 'po_number' => 'PO-9', 'supplier' => 'PT Hidrogen Asia', 'packages_expected' => 3])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $receipt = GoodsReceipt::sole();
    expect($receipt)->number->toBe('PB-'.today()->format('Ym').'-0001')->status->toBe('draft')->created_by_id->toBe($this->warehouse->id);

    Livewire::test(ListReceipts::class)->assertCanSeeTableRecords([$receipt])->assertSee($receipt->number);
});

it('runs a delivery through all seven steps to approval', function () {
    $this->actingAs($this->warehouse);
    $receipt = $this->service->create($this->warehouse, ['received_on' => today()->toDateString(), 'forwarder' => 'PT Samudra Kargo', 'packages_expected' => 2]);

    $wizard = Livewire::test(EditReceipt::class, ['record' => $receipt->getRouteKey()])
        ->assertSchemaStateSet(['number' => $receipt->number])
        ->goToWizardStep(2)                                      // step 1 saved
        ->fillForm([
            'packages_received' => 2,
            'package_photos' => [UploadedFile::fake()->image('kemasan.jpg')],
            'damage_noted' => false,
            'delivery_note_files' => [UploadedFile::fake()->image('surat-jalan.jpg')],
        ])
        ->goToWizardStep(3)                                      // step 2 saved
        ->fillForm(['documents_match' => '1', 'model_quantity_ok' => '1', 'doc_invoice' => [UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf')]])
        ->goToWizardStep(4)                                      // step 3 saved, unit check unlocked
        ->fillForm(['items' => [[
            'unit_type_id' => $this->type->id,
            'serial_number' => 'SN-0001',
            'components' => ['Unit utama', 'Selang', 'Kanula'],
            'physical_ok' => true,
            'unit_photos' => [UploadedFile::fake()->image('unit.jpg')],
            'notes' => 'Segel utuh',
        ]]])
        ->goToWizardStep(5);                                     // step 4 saved

    $item = GoodsReceiptItem::sole();
    expect($item)->unit_code->toMatch('/^NK-\d{4}$/')->serial_number->toBe('SN-0001')->is_complete->toBeTrue()
        ->and($receipt->refresh()->packages_received)->toBe(2)
        ->and($receipt->checks()->where('code', 'import_documents')->sole()->passed)->toBeTrue()
        ->and($receipt->files()->where('kind', GoodsReceiptFile::UNIT_PHOTO)->count())->toBe(1)
        ->and($receipt->files()->where('kind', GoodsReceiptFile::PACKAGE_PHOTO)->count())->toBe(1)
        ->and($receipt->files()->where('kind', GoodsReceiptFile::IMPORT_DOCUMENT)->where('document_type', 'invoice')->count())->toBe(1);
    Storage::disk('local')->assertExists($receipt->files()->where('kind', GoodsReceiptFile::UNIT_PHOTO)->first()->file_path);

    $wizard
        ->fillForm(['tests' => [['item_id' => $item->id, 'powers_on' => true, 'outputs' => true, 'no_leak' => true, 'no_abnormal' => true]]])
        ->goToWizardStep(6);                                     // step 5 saved

    expect($item->refresh())->result->toBe('pass')->tester_id->toBe($this->warehouse->id);

    $wizard->call('saveUnitsAction');
    $unit = Unit::sole();
    expect($unit->code)->toBe($item->unit_code)->and($unit->status->value)->toBe('available');

    $wizard
        ->fillForm(['shelves' => [['item_id' => $item->id, 'shelf' => 'A1-02']]])
        ->call('save');

    expect($receipt->refresh()->status)->toBe('awaiting_approval')->and($unit->refresh()->shelf)->toBe('A1-02');

    // The owner approves it on the read-only page.
    $this->actingAs($this->owner);
    Livewire::test(ViewReceipt::class, ['record' => $receipt->getRouteKey()])->callAction('approve');
    expect($receipt->refresh())->status->toBe('completed')->approved_by_id->toBe($this->owner->id);
});

it('blocks the move to step 4 while documents do not match and no finding exists', function () {
    $this->actingAs($this->warehouse);
    $receipt = $this->service->create($this->warehouse, ['received_on' => today()->toDateString()]);

    Livewire::test(EditReceipt::class, ['record' => $receipt->getRouteKey()])
        ->fillForm(['packages_received' => 1, 'package_photos' => [UploadedFile::fake()->image('k.jpg')]])
        ->goToWizardStep(3)
        ->fillForm(['documents_match' => '0', 'model_quantity_ok' => '1'])
        ->goToWizardStep(4)
        ->assertNotified('Ada dokumen atau jumlah yang tidak cocok. Catat temuan lebih dulu sebelum lanjut.');

    // Even if the unit rows are sent anyway, the service refuses to create them.
    Livewire::test(EditReceipt::class, ['record' => $receipt->getRouteKey()])
        ->fillForm(['items' => [['unit_type_id' => $this->type->id, 'serial_number' => 'SN-1', 'components' => ['Unit utama'], 'unit_photos' => [UploadedFile::fake()->image('u.jpg')]]]])
        ->goToWizardStep(5)
        ->assertNotified();

    expect(GoodsReceiptItem::count())->toBe(0);
});

it('keeps step 4 locked until the finding is closed', function () {
    $this->actingAs($this->warehouse);
    $receipt = $this->service->create($this->warehouse, ['received_on' => today()->toDateString()]);
    $this->service->setCheck($receipt, 'documents_match', false);
    $finding = $this->service->addFinding($receipt, ['type' => 'documents_incomplete', 'description' => 'PIB belum ada'], $this->warehouse);

    $rows = ['items' => [['unit_type_id' => $this->type->id, 'serial_number' => 'SN-LOCK', 'components' => ['Unit utama'], 'unit_photos' => [UploadedFile::fake()->image('u.jpg')]]]];

    // Locked: the unit row is not accepted.
    Livewire::test(EditReceipt::class, ['record' => $receipt->getRouteKey()])->fillForm($rows)->goToWizardStep(5)->assertNotified('Pemeriksaan unit terkunci sampai semua temuan dokumen ditutup atau disetujui pemilik untuk tetap terbuka.');
    expect(GoodsReceiptItem::count())->toBe(0);

    $this->service->closeFinding($finding, $this->owner);

    Livewire::test(EditReceipt::class, ['record' => $receipt->getRouteKey()])->fillForm($rows)->goToWizardStep(5);
    expect(GoodsReceiptItem::count())->toBe(1);
});

it('makes a receipt waiting for approval or finished read-only in the wizard', function () {
    $receipt = $this->service->create($this->warehouse, ['received_on' => today()->toDateString()]);
    $receipt->forceFill(['status' => 'awaiting_approval'])->save();

    $this->actingAs($this->warehouse);
    Livewire::test(EditReceipt::class, ['record' => $receipt->getRouteKey()])->assertRedirect(ReceiptResource::getUrl('view', ['record' => $receipt]));
});

it('lets only the owner approve, and not the receiver when something went wrong', function () {
    $receipt = $this->service->create($this->owner, ['received_on' => today()->toDateString()]);
    $item = $this->service->addItem($receipt, ['unit_type_id' => $this->type->id, 'serial_number' => 'SN-1', 'components' => [['name' => 'Unit utama', 'present' => true]], 'physical_ok' => true]);
    GoodsReceiptFile::create(['goods_receipt_id' => $receipt->id, 'goods_receipt_item_id' => $item->id, 'kind' => 'unit_photo', 'file_path' => 'u.jpg']);
    $this->service->recordTest($item, ['powers_on' => false, 'outputs' => true, 'no_leak' => true, 'no_abnormal' => true, 'fail_reason' => 'Mati'], $this->owner);
    $this->service->saveUnits($receipt, $this->owner);
    $this->service->setShelf($item->refresh(), 'B1', $this->owner);
    $this->service->submitForApproval($receipt, $this->owner);

    $this->actingAs($this->warehouse);
    Livewire::test(ViewReceipt::class, ['record' => $receipt->getRouteKey()])->assertActionHidden('approve');

    $this->actingAs($this->owner);
    Livewire::test(ViewReceipt::class, ['record' => $receipt->getRouteKey()])->callAction('approve');
    expect($receipt->refresh()->status)->toBe('awaiting_approval');
});
