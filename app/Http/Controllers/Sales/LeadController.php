<?php

namespace App\Http\Controllers\Sales;

use App\Enums\AgeRange;
use App\Enums\Gender;
use App\Enums\LeadCancelReason;
use App\Enums\LeadStatus;
use App\Enums\OverallResponse;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Product;
use App\Services\LeadService;
use App\Support\Provinces;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Sales app: add and update my own leads (lead profile). */
class LeadController extends Controller
{
    public function __construct(private LeadService $leads) {}

    public function index(Request $request): Response
    {
        $leads = Lead::query()->visibleTo($request->user())
            ->with(['customer', 'product', 'loans.unit.type'])
            ->latest('id')->limit(100)->get();

        return Inertia::render('Sales/Leads/Index', [
            'leads' => $leads->map(fn (Lead $l) => [
                'id' => $l->id,
                'name' => $l->customer->name,
                'location' => collect([$l->customer->city, $l->customer->province])->filter()->implode(', '),
                'gender' => $l->customer->gender?->label(),
                'age_range' => $l->customer->age_range?->value,
                'product' => $l->productName(),
                'trial_start' => $l->trialStart()?->locale('id')->translatedFormat('j M Y'),
                'symptom_form_filled' => $l->symptom_form_filled,
                'status' => $l->status->value,
                'status_label' => $l->status->label(),
                'followup_count' => $l->followup_count,
                'response' => $l->overall_response?->value,
                'response_label' => $l->overall_response?->label(),
            ])->values(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Lead::class);

        return Inertia::render('Sales/Leads/Form', ['lead' => null] + $this->options());
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Lead::class);

        $this->leads->create($this->leads->validate($request->all()), $request->user());

        return redirect()->route('sales.leads.index')->with('success', 'Lead ditambahkan.');
    }

    public function edit(Lead $lead): Response
    {
        Gate::authorize('update', $lead);
        $lead->load('customer');
        $c = $lead->customer;

        return Inertia::render('Sales/Leads/Form', ['lead' => [
            'id' => $lead->id,
            'name' => $c->name, 'whatsapp_number' => $c->whatsapp_number ?? '', 'city' => $c->city ?? '', 'province' => $c->province ?? '',
            'gender' => $c->gender?->value ?? '', 'age_range' => $c->age_range?->value ?? '',
            'product_id' => $lead->product_id, 'trial_started_on' => $lead->trial_started_on?->toDateString() ?? '',
            'symptom_form_filled' => $lead->symptom_form_filled, 'status' => $lead->status->value,
            'cancel_reason' => $lead->cancel_reason?->value ?? '', 'followup_count' => $lead->followup_count,
            'overall_response' => $lead->overall_response?->value ?? '', 'notes' => $lead->notes ?? '',
        ]] + $this->options());
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $this->leads->update($lead, $this->leads->validate($request->all()), $request->user());

        return redirect()->route('sales.leads.index')->with('success', 'Lead diperbarui.');
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'genders' => Gender::options(),
            'ageRanges' => AgeRange::options(),
            'statuses' => LeadStatus::options(),
            'cancelReasons' => LeadCancelReason::options(),
            'responses' => OverallResponse::options(),
            'provinces' => array_values(Provinces::ALL),
            'products' => Product::active()->orderBy('name')->get()->map(fn (Product $p) => [
                'id' => $p->id, 'name' => $p->name, 'category' => $p->category->label(),
            ])->values(),
            'today' => today()->toDateString(),
        ];
    }
}
