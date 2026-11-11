<?php

use App\Enums\LoanStatus;
use App\Enums\UnitStatus;
use App\Exceptions\LoanNotReturnable;
use App\Models\ActivityLog;
use App\Models\Loan;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Services\LoanService;
use App\Services\ReturnService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('local');
    $this->admin = User::factory()->admin()->create();
    $this->dewi = User::factory()->sales()->create(['name' => 'Dewi Lestari']);
    $this->rizky = User::factory()->sales()->create(['name' => 'Rizky Pratama']);
    $this->warehouse = User::factory()->warehouse()->create();

    $this->unit = Unit::factory()->create(['code' => 'NK-0012', 'unit_type_id' => UnitType::factory()]);
    foreach (['Unit utama', 'Selang', 'Kanula'] as $name) {
        $this->unit->components()->create(['name' => $name, 'is_present' => true]);
    }
    $this->unit->components()->create(['name' => 'Kartu garansi', 'is_present' => false]);

    $this->loan = app(LoanService::class)->create([
        'unit_id' => $this->unit->id, 'type' => 'event', 'event_name' => 'Pameran Surabaya',
        'starts_on' => today()->toDateString(), 'due_on' => today()->addDays(2)->toDateString(),
    ], $this->dewi);

    $this->payload = fn (array $overrides = []) => [
        'unit_code' => 'NK-0012',
        'checks' => [
            ['component' => 'Unit utama', 'is_complete' => true],
            ['component' => 'Selang', 'is_complete' => true],
            ['component' => 'Kanula', 'is_complete' => true],
        ],
        'photos' => [UploadedFile::fake()->image('depan.jpg')],
        'has_damage' => false,
        'status' => 'available',
        ...$overrides,
    ];
});

it('returns a complete unit to available and records checks, photos and logs', function () {
    $this->actingAs($this->dewi)->post('/sales/returns', ($this->payload)(['notes' => 'Kondisi baik']))
        ->assertRedirect(route('sales.loans.show', $this->loan));

    $loan = $this->loan->refresh();

    expect($loan->status)->toBe(LoanStatus::Returned)
        ->and($loan->returned_at)->not->toBeNull()
        ->and($loan->returned_by_id)->toBe($this->dewi->id)
        ->and($loan->return_notes)->toBe('Kondisi baik')
        ->and($loan->returnChecks)->toHaveCount(3)
        ->and($loan->returnChecks->every->is_complete)->toBeTrue()
        ->and($this->unit->refresh()->status)->toBe(UnitStatus::Available)
        ->and($loan->photos)->toHaveCount(1)
        ->and($loan->photos->first()->stage->value)->toBe('return');

    Storage::disk('local')->assertExists($loan->photos->first()->file_path);
    expect(ActivityLog::where('action', 'loan.returned')->count())->toBe(2)
        ->and(DB::table('notifications')->count())->toBe(0);
});

it('sends the unit to service and notifies admins when a component is missing', function () {
    $other = User::factory()->admin()->create();
    User::factory()->admin()->inactive()->create();

    $checks = [
        ['component' => 'Unit utama', 'is_complete' => true],
        ['component' => 'Selang', 'is_complete' => true],
        ['component' => 'Kanula', 'is_complete' => false],
    ];

    // The person chose "available", but the missing part overrides that.
    $this->actingAs($this->dewi)->post('/sales/returns', ($this->payload)(['checks' => $checks, 'status' => 'available']))->assertRedirect();

    expect($this->unit->refresh()->status)->toBe(UnitStatus::NeedsService)
        ->and($this->loan->refresh()->returnChecks->where('is_complete', false)->pluck('component')->all())->toBe(['Kanula'])
        // Every active admin is told (the unit factory also created one), the inactive admin is not.
        ->and(DB::table('notifications')->pluck('notifiable_id')->sort()->values()->all())
        ->toBe(User::where('role', 'admin')->where('is_active', true)->pluck('id')->sort()->values()->all())
        ->and(DB::table('notifications')->pluck('notifiable_id'))->toContain($this->admin->id, $other->id)
        ->and(DB::table('notifications')->first()->data)->toContain('NK-0012')->toContain('Kanula');
    expect(ActivityLog::where('action', 'loan.returned')->where('subject_type', 'unit')->sole()->notes)->toContain('Kelengkapan kurang: Kanula');
});

it('notifies admins without needing a queue worker', function () {
    Queue::fake();

    $this->actingAs($this->dewi)->post('/sales/returns', ($this->payload)(['status' => 'needs_service']))->assertRedirect();

    expect(DB::table('notifications')->where('notifiable_id', $this->admin->id)->count())->toBe(1);
    Queue::assertNothingPushed();
});

it('sends the unit to service when damage is reported, and requires a note', function () {
    $this->actingAs($this->dewi)->post('/sales/returns', ($this->payload)(['has_damage' => true, 'notes' => '']))
        ->assertSessionHasErrors('notes');
    expect($this->loan->refresh()->isActive())->toBeTrue();

    $this->actingAs($this->dewi)->post('/sales/returns', ($this->payload)(['has_damage' => true, 'notes' => 'Selang bocor']))->assertRedirect();

    expect($this->unit->refresh()->status)->toBe(UnitStatus::NeedsService)
        ->and($this->loan->refresh()->return_notes)->toBe('Selang bocor')
        ->and(DB::table('notifications')->where('notifiable_id', $this->admin->id)->count())->toBe(1);
});

it('honors a manual choice of needs service for an otherwise complete return', function () {
    $this->actingAs($this->dewi)->post('/sales/returns', ($this->payload)(['status' => 'needs_service']))->assertRedirect();

    expect($this->unit->refresh()->status)->toBe(UnitStatus::NeedsService)
        ->and(DB::table('notifications')->where('notifiable_id', $this->admin->id)->count())->toBe(1);
});

it('requires at least one real photo, at most six', function () {
    $this->actingAs($this->dewi);

    $this->post('/sales/returns', ($this->payload)(['photos' => []]))->assertSessionHasErrors('photos');
    $this->post('/sales/returns', ($this->payload)(['photos' => [UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')]]))->assertSessionHasErrors('photos.0');
    $this->post('/sales/returns', ($this->payload)(['photos' => array_map(fn ($i) => UploadedFile::fake()->image("f{$i}.jpg"), range(1, 7))]))->assertSessionHasErrors('photos');

    expect($this->loan->refresh()->isActive())->toBeTrue()->and($this->unit->refresh()->status)->toBe(UnitStatus::OnLoan);
});

it('rejects a checklist that does not match the unit', function () {
    $this->actingAs($this->dewi)->post('/sales/returns', ($this->payload)(['checks' => [['component' => 'Unit utama', 'is_complete' => true]]]))
        ->assertSessionHasErrors('checks');
    $this->actingAs($this->dewi)->post('/sales/returns', ($this->payload)(['checks' => [
        ['component' => 'Unit utama', 'is_complete' => true], ['component' => 'Selang', 'is_complete' => true], ['component' => 'Mobil', 'is_complete' => true],
    ]]))->assertSessionHasErrors('checks');

    expect($this->loan->refresh()->isActive())->toBeTrue();
});

it('lets only the borrower, admins and warehouse staff return a unit', function () {
    $this->actingAs($this->rizky)->post('/sales/returns', ($this->payload)())->assertSessionHasErrors('unit_code');
    expect($this->loan->refresh()->isActive())->toBeTrue();

    foreach ([$this->warehouse, $this->admin] as $i => $user) {
        $unit = Unit::factory()->create(['code' => 'NK-90'.$i]);
        $unit->components()->create(['name' => 'Unit utama', 'is_present' => true]);
        $loan = app(LoanService::class)->create(['unit_id' => $unit->id, 'type' => 'event', 'event_name' => 'X', 'starts_on' => today(), 'due_on' => today()], $this->dewi);

        $this->actingAs($user)->post('/sales/returns', [
            'unit_code' => $unit->code,
            'checks' => [['component' => 'Unit utama', 'is_complete' => true]],
            'photos' => [UploadedFile::fake()->image('a.jpg')],
            'has_damage' => false,
            'status' => 'available',
        ])->assertRedirect();

        expect($loan->refresh()->returned_by_id)->toBe($user->id);
    }
});

it('cannot return a unit twice or one that is not on loan', function () {
    $this->actingAs($this->dewi)->post('/sales/returns', ($this->payload)())->assertRedirect();

    $this->post('/sales/returns', ($this->payload)())->assertSessionHasErrors('unit_code');
    expect(session('errors')->first('unit_code'))->toContain('tidak sedang dipinjam');
    expect(Loan::count())->toBe(1)->and($this->loan->refresh()->returnChecks)->toHaveCount(3);

    expect(fn () => app(ReturnService::class)->process($this->loan, $this->dewi, [], [], null, false, 'available'))
        ->toThrow(LoanNotReturnable::class);
});

it('lets a returned unit be lent again', function () {
    $this->actingAs($this->dewi)->post('/sales/returns', ($this->payload)())->assertRedirect();

    $this->actingAs($this->rizky)->post('/sales/loans', [
        'type' => 'event', 'unit_code' => 'NK-0012', 'event_name' => 'Showcase', 'starts_on' => today()->toDateString(), 'due_on' => today()->addDay()->toDateString(),
    ])->assertRedirect();

    expect($this->unit->refresh()->status)->toBe(UnitStatus::OnLoan);
});

it('does not return a unit that needs service to the lending pool', function () {
    $this->actingAs($this->dewi)->post('/sales/returns', ($this->payload)(['status' => 'needs_service']));

    $this->actingAs($this->rizky)->post('/sales/loans', [
        'type' => 'event', 'unit_code' => 'NK-0012', 'event_name' => 'Showcase', 'starts_on' => today()->toDateString(), 'due_on' => today()->addDay()->toDateString(),
    ])->assertSessionHasErrors('unit_code');
});

it('gives warehouse staff the return page and lookup but not the rest of the sales app', function () {
    $this->actingAs($this->warehouse);

    $this->get('/sales/returns/create')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Sales/Returns/Create')->where('isWarehouse', true));
    $this->getJson('/sales/units/lookup?code=NK-0012')->assertOk()->assertJsonPath('active_loan.can_return', true);
    $this->get('/sales')->assertForbidden();
    $this->get('/sales/loans/create')->assertForbidden();
    $this->post('/sales/loans', [])->assertForbidden();
});

it('tells the scanner whether the current user may return the unit', function () {
    $this->actingAs($this->dewi)->getJson('/sales/units/lookup?code=NK-0012')
        ->assertJsonPath('active_loan.can_return', true)
        ->assertJsonPath('component_names', ['Unit utama', 'Selang', 'Kanula']);

    $this->actingAs($this->rizky)->getJson('/sales/units/lookup?code=NK-0012')->assertJsonPath('active_loan.can_return', false);
});

it('prefills the return page from a loan summary link', function () {
    $this->actingAs($this->dewi)->get('/sales/returns/create?code=nk-0012')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('prefillCode', 'NK-0012'));
});

it('keeps guests out of returns', function () {
    $this->get('/sales/returns/create')->assertRedirect('/login');
    $this->post('/sales/returns', [])->assertRedirect('/login');
});
