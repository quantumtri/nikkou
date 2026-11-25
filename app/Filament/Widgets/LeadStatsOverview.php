<?php

namespace App\Filament\Widgets;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Summary cards for the leads list: totals per stage. */
class LeadStatsOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected function getColumns(): int
    {
        return 5;
    }

    protected function getStats(): array
    {
        $leads = Lead::query()->visibleTo(auth()->user());
        $byStatus = (clone $leads)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $total = (int) $byStatus->sum();
        $count = fn (LeadStatus $s) => (int) ($byStatus[$s->value] ?? 0);

        $newThisWeek = (clone $leads)->where('created_at', '>=', today()->subDays(7))->count();
        $noForm = (clone $leads)->where('symptom_form_filled', false)->count();
        $purchased = $count(LeadStatus::Purchased);

        return [
            Stat::make('Total leads', $total)->description("{$newThisWeek} baru minggu ini | {$noForm} belum isi form gejala"),
            Stat::make('Trial berjalan', $count(LeadStatus::TrialActive)),
            Stat::make('Tawaran dikirim', $count(LeadStatus::OfferSent)),
            Stat::make('Total pembelian', $purchased)
                ->description($total > 0 ? round($purchased / $total * 100).'% dari total lead' : 'Belum ada lead')
                ->color('success'),
            Stat::make('Batal', $count(LeadStatus::Cancelled)),
        ];
    }
}
