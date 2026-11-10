<?php

use App\Enums\UnitStatus;
use App\Filament\Resources\Units\Pages\CreateUnit;
use App\Filament\Resources\Units\Pages\EditUnit;
use App\Filament\Resources\Units\Pages\ListUnits;
use App\Filament\Resources\Units\UnitResource;
use App\Models\ActivityLog;
use App\Models\Loan;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Services\UnitService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Rose Admin']);
    $this->type = UnitType::factory()->create(['name' => 'H2 Home']);
});

it('creates a unit as available with the acting user as creator and a log entry', function () {
    $warehouse = User::factory()->warehouse()->create();

    $unit = app(UnitService::class)->create([
        'code' => ' nk-0101 ',
        'unit_type_id' => $this->type->id,
        'received_on' => '2026-11-09',
        'forwarder' => 'PT Samudra Kargo Nusantara',
        'components' => [
            ['name' => 'Unit utama', 'is_present' => true],
            ['name' => 'Buku panduan', 'is_present' => false],
        ],
        'documents' => [['type' => 'invoice', 'file_path' => 'units/documents/a.pdf', 'original_name' => 'invoice.pdf']],
        'photos' => ['units/photos/a.jpg'],
    ], $warehouse);

    expect($unit->code)->toBe('NK-0101')
        ->and($unit->status)->toBe(UnitStatus::Available)
        ->and($unit->created_by_id)->toBe($warehouse->id)
        ->and($unit->components)->toHaveCount(2)
        ->and($unit->components->firstWhere('name', 'Buku panduan')->is_present)->toBeFalse()
        ->and($unit->documents)->toHaveCount(1)
        ->and($unit->photos)->toHaveCount(1);

    expect(ActivityLog::where('subject_type', 'unit')->where('subject_id', $unit->id)->sole())
        ->action->toBe('unit.created')
        ->to->toBe('available')
        ->user_id->toBe($warehouse->id);
});

it('suggests the next unit code', function () {
    expect(app(UnitService::class)->suggestNextCode())->toBe('NK-0001');

    Unit::factory()->create(['code' => 'NK-0024']);
    Unit::factory()->create(['code' => 'NK-0007']);

    expect(app(UnitService::class)->suggestNextCode())->toBe('NK-0025');
});

it('lets admin and warehouse open the inventory pages but not sales', function () {
    $unit = Unit::factory()->create();

    foreach ([$this->admin, User::factory()->warehouse()->create()] as $user) {
        $this->actingAs($user);
        $this->get(UnitResource::getUrl('index'))->assertOk();
        $this->get(UnitResource::getUrl('create'))->assertOk();
        $this->get(UnitResource::getUrl('view', ['record' => $unit]))->assertOk();
    }

    $this->actingAs(User::factory()->sales()->create())->get(UnitResource::getUrl('index'))->assertForbidden();
});

it('creates a unit from the admin form with private uploads', function () {
    Storage::fake('local');
    $this->actingAs($this->admin);

    Livewire::test(CreateUnit::class)
        ->assertSchemaStateSet(['code' => 'NK-0001'])
        ->fillForm([
            'code' => 'NK-0025',
            'unit_type_id' => $this->type->id,
            'received_on' => '2026-11-09',
            'forwarder' => 'PT Samudra Kargo Nusantara',
            'photos' => [UploadedFile::fake()->image('depan.jpg')],
            'doc_invoice' => UploadedFile::fake()->create('invoice.pdf', 120, 'application/pdf'),
            'initial_notes' => 'Kemasan penyok di sudut',
            'components' => [
                ['name' => 'Unit utama', 'is_present' => true],
                ['name' => 'Kanula', 'is_present' => false],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $unit = Unit::where('code', 'NK-0025')->sole();

    expect($unit->status)->toBe(UnitStatus::Available)
        ->and($unit->created_by_id)->toBe($this->admin->id)
        ->and($unit->components()->count())->toBe(2)
        ->and($unit->photos()->count())->toBe(1)
        ->and($unit->documents()->sole()->type->value)->toBe('invoice')
        ->and($unit->documents()->sole()->original_name)->toBe('invoice.pdf');

    Storage::disk('local')->assertExists($unit->photos()->sole()->file_path);
    Storage::disk('local')->assertExists($unit->documents()->sole()->file_path);
});

it('validates the unit form', function () {
    Unit::factory()->create(['code' => 'NK-0001']);
    $this->actingAs($this->admin);

    Livewire::test(CreateUnit::class)
        ->fillForm(['code' => 'nk-0001', 'unit_type_id' => null, 'received_on' => null])
        ->call('create')
        ->assertHasFormErrors(['code', 'unit_type_id' => 'required', 'received_on' => 'required'])
        ->assertSee('Kode produk ini sudah dipakai unit lain.');

    Livewire::test(CreateUnit::class)
        ->fillForm(['code' => 'NK 00<script>', 'unit_type_id' => $this->type->id])
        ->call('create')
        ->assertHasFormErrors(['code' => 'regex']);
});

it('prefills the completeness list from the unit type', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateUnit::class)
        ->fillForm(['unit_type_id' => $this->type->id])
        ->assertSchemaStateSet(function (array $state) {
            expect(collect($state['components'])->pluck('name')->values()->all())
                ->toBe(['Unit utama', 'Adaptor dan kabel daya', 'Selang', 'Kanula', 'Buku panduan']);
        });
});

it('logs edits and never changes status through the edit form', function () {
    $unit = Unit::factory()->status(UnitStatus::OnLoan)->create();
    $this->actingAs($this->admin);

    Livewire::test(EditUnit::class, ['record' => $unit->getRouteKey()])
        ->fillForm(['forwarder' => 'PT Baru', 'location' => 'Gudang Sidoarjo'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($unit->refresh())
        ->forwarder->toBe('PT Baru')
        ->location->toBe('Gudang Sidoarjo')
        ->status->toBe(UnitStatus::OnLoan);
    expect(ActivityLog::where('action', 'unit.updated')->where('subject_id', $unit->id)->exists())->toBeTrue();
});

it('lists units with position, overdue status and filters', function () {
    $available = Unit::factory()->create(['code' => 'NK-0003']);
    $onLoan = Unit::factory()->status(UnitStatus::OnLoan)->create(['code' => 'NK-0019']);
    $sales = User::factory()->sales()->create(['name' => 'Rizky Pratama']);
    Loan::factory()->overdue()->for($onLoan)->for($sales, 'sales')->create();
    $this->actingAs($this->admin);

    expect($onLoan->refresh()->statusLabel())->toBe('Lewat jatuh tempo')
        ->and($onLoan->currentPosition())->toEndWith('(Rizky)')
        ->and($available->currentPosition())->toBe('Gudang Surabaya');

    Livewire::test(ListUnits::class)
        ->assertCanSeeTableRecords([$available, $onLoan])
        ->assertSee('Lewat jatuh tempo')
        ->filterTable('status', 'on_loan')
        ->assertCanSeeTableRecords([$onLoan])
        ->assertCanNotSeeTableRecords([$available])
        ->resetTableFilters()
        ->searchTable('NK-0003')
        ->assertCanSeeTableRecords([$available])
        ->assertCanNotSeeTableRecords([$onLoan]);
});

it('shows unit data and history on the detail page', function () {
    $unit = app(UnitService::class)->create([
        'code' => 'NK-0012',
        'unit_type_id' => $this->type->id,
        'received_on' => '2026-10-12',
        'components' => [['name' => 'Selang', 'is_present' => true]],
    ], $this->admin);

    $this->actingAs($this->admin)
        ->get(UnitResource::getUrl('view', ['record' => $unit]))
        ->assertOk()
        ->assertSee('Unit NK-0012')
        ->assertSee('Rose Admin')
        ->assertSee('Selang')
        ->assertSee('Masuk gudang')
        ->assertSee('Unit ini belum pernah dipinjamkan.');
});

it('serves private files only through signed URLs to allowed roles', function () {
    Storage::fake('local');
    Storage::disk('local')->put('units/documents/invoice.pdf', 'pdf');
    Storage::disk('local')->put('units/photos/depan.jpg', 'jpg');

    $unit = Unit::factory()->create();
    $document = $unit->documents()->create(['type' => 'invoice', 'file_path' => 'units/documents/invoice.pdf']);
    $photo = $unit->photos()->create(['stage' => 'initial', 'file_path' => 'units/photos/depan.jpg']);

    // Unsigned or tampered URLs are rejected.
    $this->actingAs($this->admin)->get("/files/unit-documents/{$document->id}")->assertForbidden();

    $this->actingAs($this->admin)->get($document->signedUrl())->assertOk();
    $this->actingAs(User::factory()->warehouse()->create())->get($document->signedUrl())->assertOk();

    // Sales may see unit photos but not import documents.
    $sales = User::factory()->sales()->create();
    $this->actingAs($sales)->get($document->signedUrl())->assertForbidden();
    $this->actingAs($sales)->get($photo->signedUrl())->assertOk();

    auth()->logout();
    $this->get($photo->signedUrl())->assertRedirect('/login');
});
