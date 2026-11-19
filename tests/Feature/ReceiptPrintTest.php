<?php

use App\Filament\Pages\Settings;
use App\Filament\Resources\Receipts\Pages\EditReceipt;
use App\Filament\Resources\Receipts\Pages\ListReceipts;
use App\Filament\Resources\Receipts\Pages\ViewReceipt;
use App\Models\AppSetting;
use App\Models\GoodsReceiptFile;
use App\Models\UnitType;
use App\Models\User;
use App\Services\ReceiptPrinter;
use App\Services\ReceiptService;

beforeEach(function () {
    $this->service = app(ReceiptService::class);
    $this->warehouse = User::factory()->warehouse()->create(['name' => 'Agus Gudang']);
    $this->owner = User::factory()->admin()->create();
    $this->receipt = $this->service->create($this->warehouse, ['received_on' => '2026-11-19', 'supplier' => 'PT Hidrogen Asia', 'po_number' => 'PO-5', 'packages_expected' => 2]);
});

it('ships the Outfit fonts with the app', function () {
    foreach (['Outfit-400.ttf', 'Outfit-600.ttf', 'OFL.txt'] as $file) {
        expect(file_exists(storage_path("fonts/{$file}")))->toBeTrue($file);
    }
});

it('builds the filled check sheet with receipt data, the unit table padded to seven rows and the three signatures', function () {
    $type = UnitType::factory()->create();
    $item = $this->service->addItem($this->receipt, ['unit_type_id' => $type->id, 'serial_number' => 'SN-77', 'components' => [['name' => 'Unit utama', 'present' => true]], 'physical_ok' => true, 'shelf' => 'A1']);
    $this->service->recordTest($item, ['powers_on' => true, 'outputs' => true, 'no_leak' => true, 'no_abnormal' => true], $this->warehouse);
    $this->service->setCheck($this->receipt, 'documents_match', true);

    $data = app(ReceiptPrinter::class)->data($this->receipt);
    $html = view('receipts.sheet', $data)->render();

    expect($data['rows'])->toBe(7)
        ->and($html)->toContain($this->receipt->number)->toContain('PT Hidrogen Asia')->toContain('SN-77')->toContain('Lolos')
        ->toContain('Admin gudang (penerima)')->toContain('Penguji')->toContain('Pemilik / manajer')
        ->toContain('[Nama Perusahaan]')->toContain('[kode form, revisi]')->toContain('Outfit');
});

it('grows the unit table beyond seven rows and shows findings and failure reasons', function () {
    $type = UnitType::factory()->create();
    foreach (range(1, 8) as $i) {
        $item = $this->service->addItem($this->receipt, ['unit_type_id' => $type->id, 'serial_number' => "S{$i}", 'components' => [['name' => 'Unit utama', 'present' => true]], 'physical_ok' => true]);
        $this->service->recordTest($item, ['powers_on' => $i !== 3, 'outputs' => true, 'no_leak' => true, 'no_abnormal' => true, 'fail_reason' => $i === 3 ? 'Tidak menyala' : null], $this->warehouse);
    }
    $this->service->addFinding($this->receipt, ['type' => 'failed_test', 'description' => 'Unit 3 mati', 'due_on' => '2026-11-21'], $this->warehouse);

    $data = app(ReceiptPrinter::class)->data($this->receipt);
    $html = view('receipts.sheet', $data)->render();

    expect($data['rows'])->toBe(8)
        ->and($html)->toContain('Alasan gagal: Tidak menyala')->toContain('Gagal uji')->toContain('Unit 3 mati')->toContain('21/11/2026');
});

it('uses the company name and form code from the settings', function () {
    AppSetting::write(ReceiptPrinter::SETTING_COMPANY, 'PT Nikkou Hidrogen Indonesia');
    AppSetting::write(ReceiptPrinter::SETTING_FORM_CODE, 'FM-GDG-01 rev. 2');

    $html = view('receipts.sheet', app(ReceiptPrinter::class)->data(null))->render();

    expect($html)->toContain('PT Nikkou Hidrogen Indonesia')->toContain('FM-GDG-01 rev. 2')->not->toContain('[Nama Perusahaan]');
});

it('marks data copied from paper', function () {
    $paper = $this->service->create($this->warehouse, ['received_on' => '2026-11-19', 'from_paper' => true]);
    GoodsReceiptFile::create(['goods_receipt_id' => $paper->id, 'kind' => 'paper_photo', 'file_path' => 'p.jpg']);

    expect(view('receipts.sheet', app(ReceiptPrinter::class)->data($paper))->render())->toContain('Disalin dari kertas');
});

it('serves real PDF files for a receipt and the blank form', function () {
    $this->actingAs($this->warehouse);

    $filled = $this->get("/admin/receipts/{$this->receipt->id}/print")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $blank = $this->get('/admin/receipt-forms/blank')->assertOk()->assertHeader('Content-Type', 'application/pdf');

    expect($filled->getContent())->toStartWith('%PDF-')->and($blank->getContent())->toStartWith('%PDF-')
        // A4 in points.
        ->and($filled->getContent())->toContain('595.28')->toContain('841.89');
});

it('keeps the sheet away from sales and guests', function () {
    $this->get("/admin/receipts/{$this->receipt->id}/print")->assertRedirect();
    $this->get('/admin/receipt-forms/blank')->assertRedirect();

    $sales = User::factory()->sales()->create();
    $this->actingAs($sales)->get("/admin/receipts/{$this->receipt->id}/print")->assertForbidden();
    $this->actingAs($sales)->get('/admin/receipt-forms/blank')->assertForbidden();
});

it('lets only admins change the sheet settings', function () {
    $page = Settings::class;

    $this->actingAs($this->owner)->get($page::getUrl())->assertOk();
    $this->actingAs($this->warehouse)->get($page::getUrl())->assertForbidden();

    $this->actingAs($this->owner);
    Livewire\Livewire::test($page)
        ->fillForm(['company_name' => 'PT Nikkou Demo', 'form_code' => 'FM-01 rev. 1'])
        ->call('save');

    expect(AppSetting::read(ReceiptPrinter::SETTING_COMPANY))->toBe('PT Nikkou Demo')->and(AppSetting::read(ReceiptPrinter::SETTING_FORM_CODE))->toBe('FM-01 rev. 1');
});

it('shows the print buttons on the receipt pages', function () {
    $this->actingAs($this->warehouse);

    Livewire\Livewire::test(ListReceipts::class)->assertActionVisible('blank');
    Livewire\Livewire::test(ViewReceipt::class, ['record' => $this->receipt->getRouteKey()])->assertActionVisible('print');
    Livewire\Livewire::test(EditReceipt::class, ['record' => $this->receipt->getRouteKey()])->assertActionVisible('print');
});
