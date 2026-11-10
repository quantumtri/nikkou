<?php

namespace App\Filament\Widgets;

use App\Enums\UnitStatus;
use App\Models\Unit;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UnitStatsOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $counts = Unit::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            Stat::make('Total unit', $counts->sum()),
            Stat::make('Tersedia', $counts[UnitStatus::Available->value] ?? 0),
            Stat::make('Dipinjam', $counts[UnitStatus::OnLoan->value] ?? 0),
            Stat::make('Perlu servis', $counts[UnitStatus::NeedsService->value] ?? 0),
        ];
    }
}
