<?php

namespace App\Filament\Widgets\Concerns;

use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

/** Reads the dashboard's period and sales filters (spec A1). */
trait UsesDashboardFilters
{
    use InteractsWithPageFilters;

    protected function periodStart(): ?Carbon
    {
        $days = (int) ($this->pageFilters['period'] ?? 30);

        return $days > 0 ? today()->subDays($days) : null;
    }

    protected function salesFilter(): ?int
    {
        $id = $this->pageFilters['sales_id'] ?? null;

        return filled($id) ? (int) $id : null;
    }
}
