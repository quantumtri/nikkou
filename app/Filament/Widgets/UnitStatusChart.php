<?php

namespace App\Filament\Widgets;

use App\Enums\UnitStatus;
use App\Models\Unit;
use Filament\Widgets\ChartWidget;

class UnitStatusChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Status unit';

    protected ?string $maxHeight = '260px';

    protected ?string $pollingInterval = null;

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $counts = Unit::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        // Brand colors; each status also has a text label in the legend, so color is never the only cue.
        $colors = [
            UnitStatus::Available->value => '#86B3E0',
            UnitStatus::OnLoan->value => '#C0261B',
            UnitStatus::NeedsService->value => '#727171',
            UnitStatus::InService->value => '#4A4543',
            UnitStatus::Sold->value => '#F49C94',
        ];

        $statuses = array_filter(UnitStatus::cases(), fn (UnitStatus $s) => ($counts[$s->value] ?? 0) > 0);

        return [
            'labels' => array_map(fn (UnitStatus $s) => $s->label().' '.$counts[$s->value], $statuses),
            'datasets' => [[
                'data' => array_map(fn (UnitStatus $s) => (int) $counts[$s->value], $statuses),
                'backgroundColor' => array_map(fn (UnitStatus $s) => $colors[$s->value], $statuses),
                'borderWidth' => 0,
            ]],
        ];
    }

    protected function getOptions(): array
    {
        return ['plugins' => ['legend' => ['position' => 'bottom']], 'scales' => ['x' => ['display' => false], 'y' => ['display' => false]]];
    }
}
