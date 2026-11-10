<?php

use App\Enums\LeadStatus;
use App\Enums\LoanStatus;
use App\Enums\UnitStatus;
use App\Exceptions\UnitNotLoanable;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Loan;
use App\Models\Unit;
use App\Models\User;
use App\Services\LoanService;
use App\Support\WhatsApp;
use Illuminate\Auth\Access\AuthorizationException;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->dewi = User::factory()->sales()->create(['name' => 'Dewi Lestari']);
    $this->admin = User::factory()->admin()->create();
    $this->unit = Unit::factory()->create(['code' => 'NK-0012']);

    $this->payload = fn (array $overrides = []) => [
        'type' => 'home_trial',
        'unit_code' => 'NK-0012',
        'customer_name' => 'Sari Wulandari',
        'whatsapp_number' => '0812 3000 0101',
        'address' => 'Jl. Rungkut Asri Timur 12',
        'city' => 'Surabaya',
        'district' => 'Rungkut',
        'starts_on' => today()->toDateString(),
        'due_on' => today()->addDays(3)->toDateString(),
        'consent_data' => true,
        'consent_whatsapp' => true,
        ...$overrides,
    ];
});

it('saves a home trial: unit on loan, customer, lead, consent and logs', function () {
    $this->actingAs($this->dewi)->post('/sales/loans', ($this->payload)())->assertRedirect();

    $loan = Loan::sole();
    $customer = Customer::sole();
    $lead = Lead::sole();

    expect($loan->sales_id)->toBe($this->dewi->id)
        ->and($loan->status)->toBe(LoanStatus::Active)
        ->and($loan->customer_id)->toBe($customer->id)
        ->and($loan->lead_id)->toBe($lead->id)
        ->and($this->unit->refresh()->status)->toBe(UnitStatus::OnLoan)
        ->and($customer->whatsapp_number)->toBe('6281230000101')
        ->and($customer->data_consent_at)->not->toBeNull()
        ->and($customer->whatsapp_consent_at)->not->toBeNull()
        ->and($lead->sales_id)->toBe($this->dewi->id)
        ->and($lead->status)->toBe(LeadStatus::TrialActive);

    expect(ActivityLog::where('subject_type', 'unit')->where('action', 'loan.created')->sole())
        ->from->toBe('available')->to->toBe('on_loan')->user_id->toBe($this->dewi->id);
    expect(ActivityLog::where('subject_type', 'lead')->where('action', 'lead.status_changed')->exists())->toBeTrue()
        ->and(ActivityLog::where('subject_type', 'loan')->where('action', 'loan.created')->exists())->toBeTrue();
});

it('always records the acting user as sales, whatever the request says', function () {
    $other = User::factory()->sales()->create();

    $this->actingAs($this->dewi)->post('/sales/loans', ($this->payload)(['sales_id' => $other->id]));

    expect(Loan::sole()->sales_id)->toBe($this->dewi->id);
});

it('does not store WhatsApp opt-in when the box is not ticked', function () {
    $this->actingAs($this->dewi)->post('/sales/loans', ($this->payload)(['consent_whatsapp' => false]));

    expect(Customer::sole()->whatsapp_consent_at)->toBeNull();
});

it('blocks a unit that is still on loan and names the borrower', function () {
    $this->actingAs($this->dewi)->post('/sales/loans', ($this->payload)());

    $this->actingAs(User::factory()->sales()->create())
        ->post('/sales/loans', ($this->payload)(['customer_name' => 'Orang Lain', 'whatsapp_number' => '081200000999']))
        ->assertSessionHasErrors('unit_code');

    expect(session('errors')->first('unit_code'))->toContain('masih tercatat dipinjam Sari Wulandari');
    expect(Loan::count())->toBe(1);
});

it('blocks units that need service, are in service or are sold', function (UnitStatus $status) {
    $unit = Unit::factory()->status($status)->create();

    expect(fn () => app(LoanService::class)->create([
        'unit_id' => $unit->id, 'type' => 'event', 'event_name' => 'Pameran', 'starts_on' => today(), 'due_on' => today(),
    ], $this->dewi))->toThrow(UnitNotLoanable::class);

    expect(Loan::count())->toBe(0)->and($unit->refresh()->status)->toBe($status);
})->with([UnitStatus::NeedsService, UnitStatus::InService, UnitStatus::Sold]);

it('ignores an override reason sent by sales', function () {
    $this->actingAs($this->dewi)->post('/sales/loans', ($this->payload)());

    $this->actingAs($this->dewi)
        ->post('/sales/loans', ($this->payload)(['override_reason' => 'Sudah kembali kok']))
        ->assertForbidden();

    expect(fn () => app(LoanService::class)->create([
        'unit_id' => $this->unit->id, 'type' => 'event', 'event_name' => 'Pameran', 'starts_on' => today(), 'due_on' => today(),
        'override_reason' => 'Sudah kembali kok',
    ], $this->dewi))->toThrow(AuthorizationException::class);

    expect(Loan::count())->toBe(1);
});

it('lets an admin override with a reason, closing the stale loan and logging it', function () {
    $this->actingAs($this->dewi)->post('/sales/loans', ($this->payload)());
    $stale = Loan::sole();

    $this->actingAs($this->admin)
        ->post('/sales/loans', ($this->payload)([
            'customer_name' => 'Budi Santoso',
            'whatsapp_number' => '081230000102',
            'override_reason' => 'Unit sudah diterima gudang, pengembalian belum dicatat',
        ]))
        ->assertRedirect();

    $new = Loan::latest('id')->first();

    expect($stale->refresh()->status)->toBe(LoanStatus::Returned)
        ->and($stale->return_notes)->toContain('override admin')
        ->and($new->override_by_id)->toBe($this->admin->id)
        ->and($new->override_reason)->toContain('diterima gudang')
        ->and($this->unit->refresh()->status)->toBe(UnitStatus::OnLoan)
        ->and(Loan::where('unit_id', $this->unit->id)->where('status', LoanStatus::Active)->count())->toBe(1)
        ->and(ActivityLog::where('action', 'loan.created_by_override')->exists())->toBeTrue()
        ->and(ActivityLog::where('action', 'loan.closed_by_override')->exists())->toBeTrue();
});

it('saves an event loan without a customer or lead', function () {
    $this->actingAs($this->dewi)->post('/sales/loans', [
        'type' => 'event',
        'unit_code' => 'nk-0012',
        'event_name' => 'Pameran Kesehatan Surabaya',
        'starts_on' => today()->toDateString(),
        'due_on' => today()->addDays(2)->toDateString(),
    ])->assertRedirect();

    $loan = Loan::sole();

    expect($loan->customer_id)->toBeNull()
        ->and($loan->lead_id)->toBeNull()
        ->and($loan->borrowerName())->toBe('Pameran Kesehatan Surabaya')
        ->and(Customer::count())->toBe(0);
});

it('validates the loan form', function (array $overrides, string $field) {
    $this->actingAs($this->dewi)->post('/sales/loans', ($this->payload)($overrides))->assertSessionHasErrors($field);

    expect(Loan::count())->toBe(0)->and($this->unit->refresh()->status)->toBe(UnitStatus::Available);
})->with([
    'unknown unit' => [['unit_code' => 'NK-9999'], 'unit_code'],
    'missing name' => [['customer_name' => ''], 'customer_name'],
    'bad number' => [['whatsapp_number' => '12345'], 'whatsapp_number'],
    'missing address' => [['address' => ''], 'address'],
    'no data consent' => [['consent_data' => false], 'consent_data'],
    'due before start' => [['due_on' => '2020-01-01'], 'due_on'],
    'future start' => [['starts_on' => '2099-01-01', 'due_on' => '2099-01-05'], 'starts_on'],
    'event without name' => [['type' => 'event', 'event_name' => ''], 'event_name'],
    'bad type' => [['type' => 'rental'], 'type'],
]);

it('reuses the customer and open lead for a repeat loan by the same sales', function () {
    $this->actingAs($this->dewi)->post('/sales/loans', ($this->payload)());
    Unit::factory()->create(['code' => 'NK-0013']);

    $this->actingAs($this->dewi)->post('/sales/loans', ($this->payload)(['unit_code' => 'NK-0013', 'whatsapp_number' => '+62 812-3000-0101']));

    expect(Customer::count())->toBe(1)->and(Lead::count())->toBe(1)->and(Loan::count())->toBe(2);
});

it('gives another sales their own lead for the same customer', function () {
    $this->actingAs($this->dewi)->post('/sales/loans', ($this->payload)());
    Unit::factory()->create(['code' => 'NK-0013']);
    $rizky = User::factory()->sales()->create();

    $this->actingAs($rizky)->post('/sales/loans', ($this->payload)(['unit_code' => 'NK-0013']));

    expect(Customer::count())->toBe(1)
        ->and(Lead::count())->toBe(2)
        ->and(Lead::visibleTo($rizky)->count())->toBe(1);
});

it('keeps warehouse staff and guests away from loans', function () {
    $this->post('/sales/loans', ($this->payload)())->assertRedirect('/login');
    $this->actingAs(User::factory()->warehouse()->create())->post('/sales/loans', ($this->payload)())->assertForbidden();

    expect(Loan::count())->toBe(0);
});

it('shows a loan summary only to its owner and admins', function () {
    $this->actingAs($this->dewi)->post('/sales/loans', ($this->payload)());
    $loan = Loan::sole();

    $this->actingAs($this->dewi)->get("/sales/loans/{$loan->id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Sales/Loans/Show')
        ->where('loan.unit_code', 'NK-0012')
        ->where('whatsappUrl', fn (string $url) => str_starts_with($url, 'https://wa.me/6281230000101?text=')));

    $this->actingAs($this->admin)->get("/sales/loans/{$loan->id}")->assertOk();
    $this->actingAs(User::factory()->sales()->create())->get("/sales/loans/{$loan->id}")->assertForbidden();
});

it('lists only the sales own loans in the history', function () {
    Loan::factory()->count(2)->for($this->dewi, 'sales')->create();
    Loan::factory()->count(3)->create();

    $this->actingAs($this->dewi)->get('/sales/loans')->assertInertia(fn (AssertableInertia $page) => $page->has('loans.data', 2));
});

it('looks up a unit for the scan result', function () {
    $this->actingAs($this->dewi);

    $this->getJson('/sales/units/lookup?code=nk-0012')->assertOk()->assertJson(['code' => 'NK-0012', 'loanable' => true, 'warning' => null]);
    $this->getJson('/sales/units/lookup?code=NK-9999')->assertNotFound();

    $this->post('/sales/loans', ($this->payload)());

    $this->getJson('/sales/units/lookup?code=NK-0012')->assertOk()
        ->assertJson(['loanable' => false, 'status' => 'on_loan', 'active_loan' => ['borrower' => 'Sari Wulandari']])
        ->assertJsonPath('warning', fn (string $warning) => str_contains($warning, 'masih tercatat dipinjam'));
});

it('writes a WhatsApp summary without health claims', function () {
    $this->actingAs($this->dewi)->post('/sales/loans', ($this->payload)());
    $text = app(LoanService::class)->summaryText(Loan::sole());

    expect($text)->toContain('NK-0012')->toContain('Sari Wulandari')->toContain('Dewi Lestari');

    foreach (['sembuh', 'menyembuhkan', 'obat', 'mencegah', 'terapi'] as $claim) {
        expect(str_contains(strtolower($text), $claim))->toBeFalse();
    }
});

it('normalizes WhatsApp numbers', function (string $input, string $expected) {
    expect(WhatsApp::normalize($input))->toBe($expected);
})->with([
    ['0812 3456 7890', '6281234567890'],
    ['+62 812-3456-7890', '6281234567890'],
    ['81234567890', '6281234567890'],
    ['6281234567890', '6281234567890'],
]);
