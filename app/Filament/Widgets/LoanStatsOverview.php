<?php

namespace App\Filament\Widgets;

use App\Enums\LoanStatus;
use App\Enums\UnitStatus;
use App\Models\Loan;
use App\Models\Unit;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Summary cards for the loans list (spec A3). */
class LoanStatsOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected function getColumns(): int
    {
        return 5;
    }

    protected function getStats(): array
    {
        $units = Unit::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $overdue = Loan::query()->where('status', LoanStatus::Active)->whereDate('due_on', '<', today())->count();

        return [
            Stat::make('Total unit', $units->sum()),
            Stat::make('Tersedia', $units[UnitStatus::Available->value] ?? 0),
            Stat::make('Sedang dipinjam', $units[UnitStatus::OnLoan->value] ?? 0),
            Stat::make('Lewat jatuh tempo', $overdue)
                ->description($overdue > 0 ? 'Perlu ditindaklanjuti' : 'Semua sesuai jadwal')
                ->color($overdue > 0 ? 'danger' : 'gray'),
            Stat::make('Perlu servis', $units[UnitStatus::NeedsService->value] ?? 0),
        ];
    }
}
