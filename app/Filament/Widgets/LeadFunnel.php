<?php

namespace App\Filament\Widgets;

use App\Enums\LeadCancelReason;
use App\Enums\LeadStatus;
use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use App\Models\Lead;
use Filament\Widgets\Widget;

class LeadFunnel extends Widget
{
    use UsesDashboardFilters;

    protected static ?int $sort = 3;

    protected string $view = 'filament.widgets.lead-funnel';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $leads = Lead::query()
            ->when($this->salesFilter(), fn ($q, $id) => $q->where('sales_id', $id))
            ->when($this->periodStart(), fn ($q, $since) => $q->where('created_at', '>=', $since));

        $byStatus = (clone $leads)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $total = (int) $byStatus->sum();

        // Funnel stages are cumulative: a lead that bought also had a trial and an offer.
        $trial = $total - ($byStatus[LeadStatus::New->value] ?? 0);
        $stages = [
            'Lead masuk' => $total,
            'Trial / dipinjam' => max(0, $trial - ($byStatus[LeadStatus::Cancelled->value] ?? 0)),
            'Tawaran dikirim' => ($byStatus[LeadStatus::OfferSent->value] ?? 0) + ($byStatus[LeadStatus::Purchased->value] ?? 0),
            'Membeli' => (int) ($byStatus[LeadStatus::Purchased->value] ?? 0),
        ];

        $reasons = (clone $leads)->where('status', LeadStatus::Cancelled)->selectRaw('cancel_reason, count(*) as total')
            ->groupBy('cancel_reason')->pluck('total', 'cancel_reason');

        return [
            'stages' => $stages,
            'max' => max(1, $total),
            'reasons' => collect(LeadCancelReason::cases())->mapWithKeys(fn (LeadCancelReason $r) => [$r->label() => (int) ($reasons[$r->value] ?? 0)]),
            'cancelled' => (int) ($byStatus[LeadStatus::Cancelled->value] ?? 0),
        ];
    }
}
