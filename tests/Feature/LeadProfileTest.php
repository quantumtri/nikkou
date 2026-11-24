<?php

use App\Filament\Resources\Leads\Pages\CreateLead;
use App\Filament\Resources\Leads\Pages\EditLead;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Models\ActivityLog;
use App\Models\Lead;
use App\Models\Product;
use App\Models\User;
use App\Services\LeadService;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->dewi = User::factory()->sales()->create(['name' => 'Dewi']);
    $this->rizky = User::factory()->sales()->create(['name' => 'Rizky']);
    $this->home = Product::create(['name' => 'H2 Home', 'category' => 'main']);
    $this->kanula = Product::create(['name' => 'Kanula hidung', 'category' => 'accessory']);

    $this->payload = fn (array $o = []) => [
        'name' => 'Siti Rahmawati', 'whatsapp_number' => '0812 3000 0201', 'city' => 'Surabaya', 'province' => 'Jawa Timur',
        'gender' => 'female', 'age_range' => '40-49', 'product_id' => $this->home->id, 'trial_started_on' => '2026-11-10',
        'symptom_form_filled' => true, 'status' => 'trial_active', 'followup_count' => 2, 'overall_response' => 'positive',
        ...$o,
    ];
});

it('creates a lead with all ten profile fields and logs it', function () {
    $service = app(LeadService::class);
    $lead = $service->create($service->validate(($this->payload)()), $this->dewi);

    expect($lead->sales_id)->toBe($this->dewi->id)
        ->and($lead->customer->name)->toBe('Siti Rahmawati')
        ->and($lead->customer->city)->toBe('Surabaya')
        ->and($lead->customer->province)->toBe('Jawa Timur')
        ->and($lead->customer->gender->value)->toBe('female')
        ->and($lead->customer->age_range->value)->toBe('40-49')
        ->and($lead->customer->whatsapp_number)->toBe('081230000201')
        ->and($lead->product->name)->toBe('H2 Home')
        ->and($lead->trial_started_on->toDateString())->toBe('2026-11-10')
        ->and($lead->symptom_form_filled)->toBeTrue()
        ->and($lead->status->value)->toBe('trial_active')
        ->and($lead->followup_count)->toBe(2)
        ->and($lead->overall_response->value)->toBe('positive')
        ->and(ActivityLog::where('subject_type', 'lead')->where('action', 'lead.created')->count())->toBe(1);
});

it('accepts an accessory as the product tried and no WhatsApp number', function () {
    $service = app(LeadService::class);
    $lead = $service->create($service->validate(($this->payload)(['product_id' => $this->kanula->id, 'whatsapp_number' => null])), $this->dewi);

    expect($lead->product->category->value)->toBe('accessory')->and($lead->customer->whatsapp_number)->toBeNull();
});

it('validates required fields, enums and inactive products', function (array $override, string $field) {
    expect(fn () => app(LeadService::class)->validate(($this->payload)($override)))
        ->toThrow(ValidationException::class);

    try {
        app(LeadService::class)->validate(($this->payload)($override));
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }
})->with([
    'no name' => [['name' => ''], 'name'],
    'no province' => [['province' => ''], 'province'],
    'bad gender' => [['gender' => 'x'], 'gender'],
    'bad age' => [['age_range' => '99'], 'age_range'],
    'bad response' => [['overall_response' => 'great'], 'overall_response'],
    'negative followups' => [['followup_count' => -1], 'followup_count'],
    'cancelled needs reason' => [['status' => 'cancelled', 'cancel_reason' => null], 'cancel_reason'],
    'unknown product' => [['product_id' => 9999], 'product_id'],
]);

it('rejects an inactive product', function () {
    $this->home->update(['is_active' => false]);

    expect(fn () => app(LeadService::class)->validate(($this->payload)()))->toThrow(ValidationException::class);
});

it('logs status changes and clears the cancel reason when reopened', function () {
    $service = app(LeadService::class);
    $lead = $service->create($service->validate(($this->payload)()), $this->dewi);

    $lead = $service->update($lead, $service->validate(($this->payload)(['status' => 'cancelled', 'cancel_reason' => 'price_too_high'])), $this->dewi);
    expect($lead->cancel_reason->value)->toBe('price_too_high');

    $lead = $service->update($lead, $service->validate(($this->payload)(['status' => 'offer_sent'])), $this->dewi);
    expect($lead->cancel_reason)->toBeNull()
        ->and(ActivityLog::where('action', 'lead.status_changed')->count())->toBe(2);
});

it('does not log a status change when only other fields change', function () {
    $service = app(LeadService::class);
    $lead = $service->create($service->validate(($this->payload)()), $this->dewi);
    $service->update($lead, $service->validate(($this->payload)(['followup_count' => 5])), $this->dewi);

    expect($lead->refresh()->followup_count)->toBe(5)->and(ActivityLog::where('action', 'lead.status_changed')->count())->toBe(0);
});

it('falls back to loan data for product and trial start', function () {
    $lead = Lead::factory()->create(['sales_id' => $this->dewi->id]);
    expect($lead->productName())->toBeNull()->and($lead->trialStart())->toBeNull();
});

describe('sales app', function () {
    it('adds a lead for the logged-in sales', function () {
        $this->actingAs($this->dewi)->post('/sales/leads', ($this->payload)())->assertRedirect('/sales/leads');

        expect(Lead::sole()->sales_id)->toBe($this->dewi->id);
    });

    it('shows only my leads with the profile columns', function () {
        $service = app(LeadService::class);
        $service->create($service->validate(($this->payload)()), $this->dewi);
        $service->create($service->validate(($this->payload)(['name' => 'Punya Rizky'])), $this->rizky);

        $this->actingAs($this->dewi)->get('/sales/leads')->assertInertia(fn (AssertableInertia $p) => $p
            ->component('Sales/Leads/Index')->has('leads', 1)
            ->where('leads.0.name', 'Siti Rahmawati')->where('leads.0.product', 'H2 Home')
            ->where('leads.0.followup_count', 2)->where('leads.0.response', 'positive')
            ->where('leads.0.symptom_form_filled', true));
    });

    it('lets sales edit their own lead but not someone else’s', function () {
        $service = app(LeadService::class);
        $mine = $service->create($service->validate(($this->payload)()), $this->dewi);
        $theirs = $service->create($service->validate(($this->payload)()), $this->rizky);

        $this->actingAs($this->dewi)->put("/sales/leads/{$mine->id}", ($this->payload)(['followup_count' => 7]))->assertRedirect();
        expect($mine->refresh()->followup_count)->toBe(7);

        $this->actingAs($this->dewi)->get("/sales/leads/{$theirs->id}/edit")->assertForbidden();
        $this->actingAs($this->dewi)->put("/sales/leads/{$theirs->id}", ($this->payload)())->assertForbidden();
    });

    it('shows validation errors', function () {
        $this->actingAs($this->dewi)->post('/sales/leads', ($this->payload)(['name' => '']))->assertSessionHasErrors('name');
    });
});

describe('admin', function () {
    it('lists all leads with the ten columns, and filters', function () {
        $service = app(LeadService::class);
        $a = $service->create($service->validate(($this->payload)()), $this->dewi);
        $b = $service->create($service->validate(($this->payload)(['name' => 'Budi Neg', 'overall_response' => 'negative'])), $this->rizky);

        $this->actingAs($this->admin);
        Livewire::test(ListLeads::class)
            ->assertCanSeeTableRecords([$a, $b])
            ->assertSee('Surabaya, Jawa Timur')->assertSee('2 kali')
            ->filterTable('overall_response', 'negative')
            ->assertCanSeeTableRecords([$b])->assertCanNotSeeTableRecords([$a])
            ->resetTableFilters()->searchTable('Siti')
            ->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b]);
    });

    it('adds and edits a lead for a chosen sales', function () {
        $this->actingAs($this->admin);

        Livewire::test(CreateLead::class)
            ->fillForm(($this->payload)(['sales_id' => $this->rizky->id]))
            ->call('create')->assertHasNoFormErrors();

        $lead = Lead::sole();
        expect($lead->sales_id)->toBe($this->rizky->id)->and($lead->customer->name)->toBe('Siti Rahmawati');

        Livewire::test(EditLead::class, ['record' => $lead->id])
            ->assertFormSet(['name' => 'Siti Rahmawati', 'province' => 'Jawa Timur', 'gender' => 'female'])
            ->fillForm(['status' => 'purchased', 'sales_id' => $this->dewi->id])
            ->call('save')->assertHasNoFormErrors();

        expect($lead->refresh()->status->value)->toBe('purchased')->and($lead->sales_id)->toBe($this->dewi->id);
    });

    it('keeps sales out of the admin lead pages', function () {
        $this->actingAs($this->dewi)->get('/admin/leads')->assertForbidden();
        $this->actingAs($this->dewi)->get('/admin/products')->assertForbidden();
    });

    it('manages the product catalog', function () {
        $this->actingAs($this->admin)->get('/admin/products')->assertOk()->assertSee('Kanula hidung')->assertSee('Aksesoris');
    });
});
