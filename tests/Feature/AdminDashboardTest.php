<?php

use App\Enums\LeadStatus;
use App\Enums\UnitStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\Loans\Pages\ListLoans;
use App\Filament\Resources\Loans\Tables\LoansTable;
use App\Filament\Widgets\DashboardStats;
use App\Filament\Widgets\LeadFunnel;
use App\Filament\Widgets\LoanStatsOverview;
use App\Filament\Widgets\SalesTable;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Loan;
use App\Models\Unit;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Rose Admin']);
    $this->dewi = User::factory()->sales()->create(['name' => 'Dewi Lestari']);
    $this->rizky = User::factory()->sales()->create(['name' => 'Rizky Pratama']);
});

it('lists loans for admins only', function () {
    $this->actingAs($this->admin)->get(LoanResource::getUrl('index'))->assertOk();
    $this->actingAs(User::factory()->warehouse()->create())->get(LoanResource::getUrl('index'))->assertForbidden();
    $this->actingAs($this->dewi)->get(LoanResource::getUrl('index'))->assertForbidden();
});

it('has no create, edit or delete for loans', function () {
    $loan = Loan::factory()->create();
    $this->actingAs($this->admin);

    expect(LoanResource::canCreate())->toBeFalse()
        ->and(LoanResource::canEdit($loan))->toBeFalse()
        ->and(LoanResource::canDelete($loan))->toBeFalse()
        ->and(array_keys(LoanResource::getPages()))->toBe(['index']);
});

it('shows active loans by default and filters by status, sales and type', function () {
    $active = Loan::factory()->for($this->dewi, 'sales')->create();
    $overdue = Loan::factory()->overdue()->for($this->rizky, 'sales')->create();
    $returned = Loan::factory()->returned()->for($this->dewi, 'sales')->create();
    $event = Loan::factory()->for($this->rizky, 'sales')->create(['type' => 'event', 'event_name' => 'Pameran Surabaya', 'customer_id' => null]);
    $this->actingAs($this->admin);

    Livewire::test(ListLoans::class)
        ->assertCanSeeTableRecords([$active, $overdue, $event])
        ->assertCanNotSeeTableRecords([$returned])
        ->filterTable('status', 'overdue')
        ->assertCanSeeTableRecords([$overdue])
        ->assertCanNotSeeTableRecords([$active, $event])
        ->filterTable('status', 'returned')
        ->assertCanSeeTableRecords([$returned])
        ->removeTableFilter('status')
        ->filterTable('sales_id', $this->dewi->id)
        ->assertCanSeeTableRecords([$active, $returned])
        ->assertCanNotSeeTableRecords([$overdue, $event])
        ->removeTableFilter('sales_id')
        ->filterTable('type', 'event')
        ->assertCanSeeTableRecords([$event])
        ->assertCanNotSeeTableRecords([$active]);
});

it('searches by unit code, customer name and event name', function () {
    $unit = Unit::factory()->create(['code' => 'NK-0777']);
    $byCode = Loan::factory()->for($unit)->create();
    $byName = Loan::factory()->create(['customer_id' => Customer::factory()->create(['name' => 'Sari Wulandari'])->id]);
    $byEvent = Loan::factory()->create(['type' => 'event', 'event_name' => 'Pameran Kesehatan', 'customer_id' => null]);
    $this->actingAs($this->admin);

    Livewire::test(ListLoans::class)->searchTable('NK-0777')->assertCanSeeTableRecords([$byCode])->assertCanNotSeeTableRecords([$byName, $byEvent]);
    Livewire::test(ListLoans::class)->searchTable('wulandari')->assertCanSeeTableRecords([$byName])->assertCanNotSeeTableRecords([$byCode, $byEvent]);
    Livewire::test(ListLoans::class)->searchTable('kesehatan')->assertCanSeeTableRecords([$byEvent])->assertCanNotSeeTableRecords([$byCode, $byName]);
});

it('words the status by days overdue', function () {
    $overdue = Loan::factory()->overdue()->create(['due_on' => today()->subDays(4)]);

    expect(LoansTable::statusText($overdue))->toBe('Lewat 4 hari')
        ->and(LoansTable::statusText(Loan::factory()->create()))->toBe('Dipinjam')
        ->and(LoansTable::statusText(Loan::factory()->returned()->create()))->toStartWith('Kembali');
});

it('counts overdue loans and units on the loan summary cards', function () {
    Unit::factory()->count(2)->create();
    Unit::factory()->status(UnitStatus::NeedsService)->create();
    Loan::factory()->overdue()->count(2)->create();
    $this->actingAs($this->admin);

    Livewire::test(LoanStatsOverview::class)
        ->assertSee('Lewat jatuh tempo')
        ->assertSee('Perlu ditindaklanjuti');
});

it('renders the dashboard for admins and warehouse but not sales', function () {
    $this->actingAs($this->admin)->get(Dashboard::getUrl())->assertOk()->assertSee('Dasbor')->assertSeeLivewire(SalesTable::class)->assertSeeLivewire(DashboardStats::class)->assertSeeLivewire(LeadFunnel::class);
    $this->actingAs(User::factory()->warehouse()->create())->get(Dashboard::getUrl())->assertOk();
    $this->actingAs($this->dewi)->get(Dashboard::getUrl())->assertForbidden();
});

it('computes lead counts and conversion on the dashboard, honoring the sales filter', function () {
    Lead::factory()->count(3)->for($this->dewi, 'sales')->create();
    Lead::factory()->for($this->dewi, 'sales')->create(['status' => LeadStatus::Purchased]);
    Lead::factory()->count(2)->for($this->rizky, 'sales')->create();
    $this->actingAs($this->admin);

    Livewire::test(DashboardStats::class, ['pageFilters' => ['period' => 30]])
        ->assertSee('6')
        ->assertSee('17%')
        ->assertSee('1 dari 6 lead');

    Livewire::test(DashboardStats::class, ['pageFilters' => ['period' => 30, 'sales_id' => $this->dewi->id]])
        ->assertSee('25%')
        ->assertSee('1 dari 4 lead');
});

it('limits dashboard figures to the selected period', function () {
    Lead::factory()->for($this->dewi, 'sales')->create(['created_at' => now()->subDays(60)]);
    Lead::factory()->for($this->dewi, 'sales')->create();
    $this->actingAs($this->admin);

    Livewire::test(DashboardStats::class, ['pageFilters' => ['period' => 30]])->assertSee('0 dari 1 lead');
    Livewire::test(DashboardStats::class, ['pageFilters' => ['period' => 0]])->assertSee('0 dari 2 lead');
});

it('shows per-sales leads, loans and units held', function () {
    Lead::factory()->count(2)->for($this->dewi, 'sales')->create();
    Loan::factory()->count(2)->for($this->dewi, 'sales')->create();
    Loan::factory()->for($this->rizky, 'sales')->create();
    $this->actingAs($this->admin);

    Livewire::test(SalesTable::class, ['pageFilters' => ['period' => 30]])
        ->assertSee('Dewi')
        ->assertSee('Rizky')
        ->assertDontSee('Rose');

    Livewire::test(SalesTable::class, ['pageFilters' => ['period' => 30, 'sales_id' => $this->rizky->id]])
        ->assertSee('Rizky')
        ->assertDontSee('Dewi');
});

it('shows the lead funnel and cancel reasons', function () {
    Lead::factory()->count(2)->create();
    Lead::factory()->create(['status' => LeadStatus::Cancelled, 'cancel_reason' => 'price_too_high']);
    $this->actingAs($this->admin);

    Livewire::test(LeadFunnel::class, ['pageFilters' => ['period' => 30]])
        ->assertSee('Lead masuk')
        ->assertSee('Harga tinggi');
});
