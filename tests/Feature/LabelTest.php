<?php

use App\Filament\Pages\Labels;
use App\Filament\Resources\Units\Pages\CreateUnit;
use App\Filament\Resources\Units\UnitResource;
use App\Models\ActivityLog;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Services\LabelService;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->units = Unit::factory()->count(3)->create();
});

it('renders a PNG QR code and a Code 128 barcode for a unit code', function () {
    $service = app(LabelService::class);

    expect(getimagesizefromstring($service->qrPng('NK-0012'))['mime'])->toBe('image/png')
        ->and(getimagesizefromstring($service->barcodePng('NK-0012'))['mime'])->toBe('image/png');
});

it('builds a PDF with one page per label at the chosen size', function () {
    $pdf = app(LabelService::class)->pdf($this->units->load('type'), '50x30');

    expect($pdf)->toStartWith('%PDF-')
        ->and(preg_match_all('/\/Type\s*\/Page\b/', $pdf))->toBe(3)
        // 50 x 30 mm in points.
        ->and($pdf)->toContain('141.73')
        ->and($pdf)->toContain('85.03');
});

it('rejects unknown sizes, modes and empty selections', function () {
    $service = app(LabelService::class);

    expect(fn () => $service->pdf($this->units, '10x10'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->pdf($this->units, '50x30', 'aztec'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->pdf(collect()))->toThrow(InvalidArgumentException::class);
});

it('leaves out the code that was not requested', function () {
    $service = app(LabelService::class);
    $unit = $this->units->first()->load('type');

    expect($service->labelData($unit, 'qr'))->barcode->toBeNull()->qr->not->toBeNull()
        ->and($service->labelData($unit, 'barcode'))->qr->toBeNull()->barcode->not->toBeNull();
});

it('marks units as printed and logs it when labels are printed', function () {
    $this->actingAs($this->admin);
    [$first, $second, $third] = $this->units->all();

    Livewire::test(Labels::class)
        ->fillForm(['unit_ids' => [$first->id, $second->id]])
        ->call('printLabels')
        ->assertHasNoFormErrors();

    expect($first->refresh()->label_printed_at)->not->toBeNull()
        ->and($second->refresh()->label_printed_at)->not->toBeNull()
        ->and($third->refresh()->label_printed_at)->toBeNull()
        ->and(ActivityLog::where('action', 'unit.label_printed')->count())->toBe(2);
});

it('requires at least one unit', function () {
    $this->actingAs($this->admin);

    Livewire::test(Labels::class)
        ->call('downloadLabels')
        ->assertHasFormErrors(['unit_ids' => 'required']);

    expect(ActivityLog::where('action', 'unit.label_printed')->count())->toBe(0);
});

it('preselects units passed from the inventory list', function () {
    $this->actingAs($this->admin);
    $ids = $this->units->take(2)->pluck('id');

    Livewire::withQueryParams(['units' => $ids->implode(',').',abc,999999'])
        ->test(Labels::class)
        ->assertSchemaStateSet(fn (array $state) => expect(collect($state['unit_ids'])->map(fn ($id) => (int) $id)->sort()->values()->all())->toBe($ids->sort()->values()->all()));
});

it('serves the label PDF to admin and warehouse only', function () {
    $url = route('labels.pdf', ['units' => $this->units->pluck('id')->implode(','), 'size' => '50x30', 'mode' => 'both']);

    $this->actingAs($this->admin)->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs(User::factory()->warehouse()->create())->get($url.'&download=1')->assertOk()->assertHeader('Content-Disposition');
    $this->actingAs(User::factory()->sales()->create())->get($url)->assertForbidden();
});

it('validates label PDF parameters', function () {
    $this->actingAs($this->admin);

    $this->get(route('labels.pdf', ['units' => '1;drop', 'size' => '50x30', 'mode' => 'both']))->assertSessionHasErrors('units');
    $this->get(route('labels.pdf', ['units' => '1', 'size' => 'huge', 'mode' => 'both']))->assertSessionHasErrors('size');
    $this->get(route('labels.pdf', ['units' => '999999', 'size' => '50x30', 'mode' => 'both']))->assertNotFound();
});

it('keeps sales out of the label page and QR download', function () {
    $sales = User::factory()->sales()->create();

    $this->actingAs($sales)->get(Labels::getUrl())->assertForbidden();
    $this->actingAs($sales)->get(route('labels.qr', $this->units->first()))->assertForbidden();
    $this->actingAs($this->admin)->get(route('labels.qr', $this->units->first()))->assertOk()->assertHeader('Content-Type', 'image/png');
});

it('shows the label on the unit detail page', function () {
    $this->actingAs($this->admin)
        ->get(UnitResource::getUrl('view', ['record' => $this->units->first()]))
        ->assertOk()
        ->assertSee('Label QR / barcode')
        ->assertSee('Label belum pernah dicetak.');
});

it('can save a unit and go straight to label printing', function () {
    $this->actingAs($this->admin);
    $type = UnitType::factory()->create();

    Livewire::test(CreateUnit::class)
        ->fillForm(['code' => 'NK-0500', 'unit_type_id' => $type->id, 'received_on' => '2026-11-09'])
        ->call('createAndPrint')
        ->assertHasNoFormErrors()
        ->assertRedirect(Labels::getUrl(['units' => Unit::where('code', 'NK-0500')->sole()->id]));
});
