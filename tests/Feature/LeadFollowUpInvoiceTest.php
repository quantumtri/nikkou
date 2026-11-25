<?php

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\Leads\Pages\CreateLead;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Filament\Resources\Leads\Pages\ViewLead;
use App\Filament\Resources\Leads\RelationManagers\FollowUpsRelationManager;
use App\Filament\Resources\Leads\RelationManagers\InvoicesRelationManager;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Product;
use App\Models\User;
use App\Services\FollowUpService;
use App\Services\InvoiceService;
use App\Services\LeadService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->dewi = User::factory()->sales()->create(['name' => 'Dewi']);
    $this->rizky = User::factory()->sales()->create(['name' => 'Rizky']);
    $this->home = Product::create(['name' => 'H2 Home', 'category' => 'main', 'price' => 8900000]);
    $this->kanula = Product::create(['name' => 'Kanula hidung', 'category' => 'accessory', 'price' => 150000]);
    $this->tas = Product::create(['name' => 'Tas pembawa', 'category' => 'accessory', 'price' => 450000]);

    $this->payload = fn (array $o = []) => [
        'name' => 'Siti Rahmawati', 'city' => 'Surabaya', 'province' => 'Jawa Timur', 'gender' => 'female', 'age_range' => '40-49',
        'path' => 'trial', 'product_id' => $this->home->id, 'trial_started_on' => '2026-11-10', 'accessories' => [],
        'symptom_form_filled' => false, 'status' => 'new', ...$o,
    ];
    $this->makeLead = fn (array $o = [], ?User $sales = null) => app(LeadService::class)->create(
        app(LeadService::class)->validate(($this->payload)($o)), $sales ?? $this->dewi
    );
    $this->followUp = fn (Lead $lead, string $day, string $response, ?User $by = null) => app(FollowUpService::class)->add(
        $lead, ['followed_up_on' => $day, 'channel' => 'whatsapp', 'response' => $response], $by ?? $this->dewi
    );
});

describe('ID numbers', function () {
    it('numbers leads and invoices per year, in order', function () {
        $a = ($this->makeLead)();
        $b = ($this->makeLead)(['name' => 'Budi']);
        $year = now()->format('Y');

        expect($a->code)->toBe("LD-$year-0001")->and($b->code)->toBe("LD-$year-0002");

        $invoice = app(InvoiceService::class)->createForLead($a, [['product_id' => $this->home->id, 'quantity' => 1, 'unit_price' => 100]], $this->dewi);
        expect($invoice->code)->toBe("INV-$year-0001");
    });
});

describe('follow-ups', function () {
    it('counts the follow-ups added and takes the newest response as overall response', function () {
        $lead = ($this->makeLead)();
        expect($lead->followup_count)->toBe(0)->and($lead->overall_response)->toBeNull();

        ($this->followUp)($lead, '2026-11-12', 'neutral');
        ($this->followUp)($lead, '2026-11-16', 'positive');
        // Added later but dated earlier: must not become the overall response.
        ($this->followUp)($lead, '2026-11-14', 'negative');

        $lead->refresh();
        expect($lead->followup_count)->toBe(3)
            ->and($lead->overall_response->value)->toBe('positive')
            ->and(ActivityLog::where('action', 'lead.followup_added')->count())->toBe(3);
    });

    it('validates the follow-up', function (array $override, string $field) {
        $lead = ($this->makeLead)();
        $base = ['followed_up_on' => '2026-11-12', 'channel' => 'call', 'response' => 'neutral'];

        expect(fn () => app(FollowUpService::class)->validate($override + $base))->toThrow(ValidationException::class);
    })->with([
        'no response' => [['response' => ''], 'response'],
        'bad response' => [['response' => 'great'], 'response'],
        'bad channel' => [['channel' => 'pigeon'], 'channel'],
        'future date' => [['followed_up_on' => '2999-01-01'], 'followed_up_on'],
    ]);

    it('lets sales add follow-ups only on their own leads', function () {
        $mine = ($this->makeLead)();
        $theirs = ($this->makeLead)([], $this->rizky);
        $data = ['followed_up_on' => today()->toDateString(), 'channel' => 'whatsapp', 'response' => 'positive', 'notes' => 'Tidur lebih nyenyak'];

        $this->actingAs($this->dewi)->post("/sales/leads/{$mine->id}/followups", $data)->assertRedirect();
        expect($mine->refresh()->followup_count)->toBe(1)->and($mine->overall_response->value)->toBe('positive');

        $this->actingAs($this->dewi)->post("/sales/leads/{$theirs->id}/followups", $data)->assertForbidden();
        expect($theirs->refresh()->followup_count)->toBe(0);
    });

    it('shows the lead detail with its follow-ups in the sales app', function () {
        $lead = ($this->makeLead)(['accessories' => [['product_id' => $this->kanula->id, 'quantity' => 2]]]);
        ($this->followUp)($lead, '2026-11-12', 'positive');

        $this->actingAs($this->dewi)->get("/sales/leads/{$lead->id}")->assertInertia(fn (AssertableInertia $p) => $p
            ->component('Sales/Leads/Show')->where('lead.code', $lead->code)->where('lead.followup_count', 1)
            ->where('lead.accessories.0', 'Kanula hidung x2')->has('followUps', 1)->where('followUps.0.response', 'positive'));

        $this->actingAs($this->rizky)->get("/sales/leads/{$lead->id}")->assertForbidden();
    });
});

describe('products and accessories', function () {
    it('keeps accessories out of the product tried and lists them on the lead', function () {
        $lead = ($this->makeLead)(['accessories' => [['product_id' => $this->kanula->id, 'quantity' => 1], ['product_id' => $this->tas->id, 'quantity' => 2]]]);

        expect($lead->product->name)->toBe('H2 Home')
            ->and($lead->accessories->pluck('name')->all())->toEqualCanonicalizing(['Kanula hidung', 'Tas pembawa'])
            ->and($lead->invoices)->toHaveCount(0);

        expect(fn () => app(LeadService::class)->validate(($this->payload)(['product_id' => $this->kanula->id])))->toThrow(ValidationException::class);
    });

    it('replaces the accessories when the lead is updated', function () {
        $lead = ($this->makeLead)(['accessories' => [['product_id' => $this->kanula->id, 'quantity' => 1]]]);
        app(LeadService::class)->update($lead, app(LeadService::class)->validate(($this->payload)(['accessories' => [['product_id' => $this->tas->id, 'quantity' => 1]]])), $this->dewi);

        expect($lead->refresh()->accessories->pluck('name')->all())->toBe(['Tas pembawa']);
    });
});

describe('direct purchase', function () {
    it('skips the trial and issues an invoice with the product and accessories', function () {
        $lead = ($this->makeLead)(['path' => 'direct', 'trial_started_on' => '2026-11-10', 'accessories' => [['product_id' => $this->kanula->id, 'quantity' => 2]]]);
        $invoice = $lead->invoices()->sole();

        expect($lead->path->value)->toBe('direct')
            ->and($lead->trial_started_on)->toBeNull()
            ->and($invoice->status->value)->toBe('unpaid')
            ->and($invoice->total)->toBe(8900000 + 2 * 150000)
            ->and($invoice->items->pluck('name')->all())->toBe(['H2 Home', 'Kanula hidung'])
            ->and($invoice->items->last()->quantity)->toBe(2);
    });

    it('keeps the invoice price when the catalog price changes later', function () {
        $lead = ($this->makeLead)(['path' => 'direct']);
        $this->home->update(['price' => 9900000]);

        expect($lead->invoices()->sole()->items->first()->unit_price)->toBe(8900000);
    });

    it('does not create an invoice for a trial lead', function () {
        expect(Invoice::count())->toBe(0);
        ($this->makeLead)();
        expect(Invoice::count())->toBe(0);
    });
});

describe('invoice status', function () {
    it('marks a paid invoice and turns the lead into a purchase', function () {
        $lead = ($this->makeLead)(['path' => 'direct']);
        $invoice = app(InvoiceService::class)->changeStatus($lead->invoices()->sole(), InvoiceStatus::Paid, $this->admin);

        expect($invoice->status->value)->toBe('paid')->and($invoice->paid_on->toDateString())->toBe(today()->toDateString())
            ->and($lead->refresh()->status->value)->toBe('purchased')
            ->and(ActivityLog::where('action', 'invoice.status_changed')->count())->toBe(1);
    });

    it('lets only admin change the status, and only once', function () {
        $lead = ($this->makeLead)(['path' => 'direct']);
        $invoice = $lead->invoices()->sole();

        expect(fn () => app(InvoiceService::class)->changeStatus($invoice, InvoiceStatus::Paid, $this->dewi))->toThrow(ValidationException::class);

        app(InvoiceService::class)->changeStatus($invoice, InvoiceStatus::Cancelled, $this->admin);
        expect(fn () => app(InvoiceService::class)->changeStatus($invoice, InvoiceStatus::Paid, $this->admin))->toThrow(ValidationException::class);
        expect($lead->refresh()->status->value)->toBe('new');
    });

    it('validates invoice items', function () {
        expect(fn () => app(InvoiceService::class)->validate(['items' => []]))->toThrow(ValidationException::class);
        expect(fn () => app(InvoiceService::class)->validate(['items' => [['product_id' => 999, 'quantity' => 1, 'unit_price' => 1]]]))->toThrow(ValidationException::class);
    });

    it('stops sales from invoicing someone else’s lead', function () {
        $lead = ($this->makeLead)();
        expect(fn () => app(InvoiceService::class)->createForLead($lead, [['product_id' => $this->home->id, 'quantity' => 1, 'unit_price' => 1]], $this->rizky))
            ->toThrow(ValidationException::class);
    });
});

describe('admin screens', function () {
    it('lists leads with ID and gender icons, and a see-more action instead of edit', function () {
        $lead = ($this->makeLead)();
        $this->actingAs($this->admin);

        Livewire::test(ListLeads::class)->assertCanSeeTableRecords([$lead])->assertSee($lead->code)
            ->assertTableActionExists('view')->assertTableActionDoesNotExist('edit');
    });

    it('shows the lead detail with follow-up and invoice tabs', function () {
        $lead = ($this->makeLead)(['accessories' => [['product_id' => $this->kanula->id, 'quantity' => 1]]]);
        ($this->followUp)($lead, '2026-11-12', 'positive');
        $this->actingAs($this->admin);

        Livewire::test(ViewLead::class, ['record' => $lead->id])->assertOk()->assertSee($lead->code)->assertSee('Kanula hidung x1');
        Livewire::test(FollowUpsRelationManager::class, ['ownerRecord' => $lead, 'pageClass' => ViewLead::class])->assertOk()->assertSee('Positif');
    });

    it('adds a follow-up from the lead detail', function () {
        $lead = ($this->makeLead)();
        $this->actingAs($this->admin);

        Livewire::test(FollowUpsRelationManager::class, ['ownerRecord' => $lead, 'pageClass' => ViewLead::class])
            ->callAction(TestAction::make('create')->table(), ['followed_up_on' => today()->toDateString(), 'channel' => 'call', 'response' => 'negative', 'notes' => 'Masker kurang pas'])
            ->assertHasNoFormErrors();

        expect($lead->refresh()->followup_count)->toBe(1)->and($lead->overall_response->value)->toBe('negative');
    });

    it('creates an invoice from the lead detail, prefilled from the lead', function () {
        $lead = ($this->makeLead)(['accessories' => [['product_id' => $this->kanula->id, 'quantity' => 1]]]);
        $this->actingAs($this->admin);

        Livewire::test(InvoicesRelationManager::class, ['ownerRecord' => $lead, 'pageClass' => ViewLead::class])
            ->callAction(TestAction::make('create')->table(), ['items' => [
                ['product_id' => $this->home->id, 'quantity' => 1, 'unit_price' => 8500000],
                ['product_id' => $this->kanula->id, 'quantity' => 1, 'unit_price' => 150000],
            ]])->assertHasNoFormErrors();

        expect($lead->invoices()->sole()->total)->toBe(8650000);
    });

    it('lists invoices and lets admin confirm payment', function () {
        $lead = ($this->makeLead)(['path' => 'direct']);
        $invoice = $lead->invoices()->sole();
        $this->actingAs($this->admin);

        Livewire::test(ListInvoices::class)->assertCanSeeTableRecords([$invoice])->assertSee($invoice->code)->assertSee('Belum dibayar');

        Livewire::test(ViewInvoice::class, ['record' => $invoice->id])->assertSee('H2 Home')->callAction('markPaid');
        expect($invoice->refresh()->status->value)->toBe('paid');
    });

    it('creates a direct-purchase lead in the admin form with an invoice', function () {
        $this->actingAs($this->admin);

        Livewire::test(CreateLead::class)
            ->fillForm(($this->payload)(['path' => 'direct', 'sales_id' => $this->rizky->id, 'accessories' => [['product_id' => $this->tas->id, 'quantity' => 1]]]))
            ->call('create')->assertHasNoFormErrors();

        $lead = Lead::sole();
        expect($lead->path->value)->toBe('direct')->and($lead->invoices)->toHaveCount(1)->and($lead->invoices->first()->total)->toBe(8900000 + 450000);
    });

    it('scopes invoices to the sales who own the lead', function () {
        $mine = ($this->makeLead)(['path' => 'direct']);
        ($this->makeLead)(['path' => 'direct'], $this->rizky);

        expect(Invoice::query()->visibleTo($this->dewi)->pluck('lead_id')->all())->toBe([$mine->id])
            ->and(Invoice::query()->visibleTo($this->admin)->count())->toBe(2);
    });
});
