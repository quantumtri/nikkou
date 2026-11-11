<?php

namespace App\Filament\Widgets;

use App\Enums\LeadStatus;
use App\Enums\LoanStatus;
use App\Enums\UnitStatus;
use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use App\Models\Lead;
use App\Models\Loan;
use App\Models\Unit;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStats extends StatsOverviewWidget
{
    use UsesDashboardFilters;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $since = $this->periodStart();
        $sales = $this->salesFilter();

        $leads = Lead::query()
            ->when($sales, fn ($q) => $q->where('sales_id', $sales))
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since));
        $totalLeads = (clone $leads)->count();
        $purchased = (clone $leads)->where('status', LeadStatus::Purchased)->count();
        $newThisWeek = Lead::query()->when($sales, fn ($q) => $q->where('sales_id', $sales))->where('created_at', '>=', today()->subDays(7))->count();

        $loans = Loan::query()
            ->when($sales, fn ($q) => $q->where('sales_id', $sales))
            ->when($since, fn ($q) => $q->where('starts_on', '>=', $since));
        $overdue = Loan::query()->where('status', LoanStatus::Active)->whereDate('due_on', '<', today())
            ->when($sales, fn ($q) => $q->where('sales_id', $sales))->count();

        $units = Unit::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $service = ($units[UnitStatus::NeedsService->value] ?? 0) + ($units[UnitStatus::InService->value] ?? 0);

        return [
            Stat::make('Total leads', $totalLeads)->description("{$newThisWeek} baru minggu ini"),
            Stat::make('Peminjaman', $loans->count())
                ->description($overdue > 0 ? "{$overdue} lewat jatuh tempo" : 'Tidak ada yang lewat jatuh tempo')
                ->color($overdue > 0 ? 'danger' : 'gray'),
            Stat::make('Konversi lead ke pembelian', $totalLeads > 0 ? round($purchased / $totalLeads * 100).'%' : '-')
                ->description("{$purchased} dari {$totalLeads} lead"),
            Stat::make('Total unit', $units->sum())
                ->description(($units[UnitStatus::Available->value] ?? 0).' tersedia | '.($units[UnitStatus::OnLoan->value] ?? 0)." dipinjam | {$service} servis"),
        ];
    }
}
