<?php

namespace App\Http\Controllers\Sales;

use App\Enums\AgeRange;
use App\Enums\FollowUpChannel;
use App\Enums\Gender;
use App\Enums\LeadCancelReason;
use App\Enums\LeadPath;
use App\Enums\LeadStatus;
use App\Enums\OverallResponse;
use App\Enums\ProductCategory;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Product;
use App\Services\FollowUpService;
use App\Services\LeadService;
use App\Support\Provinces;
use App\Support\Rupiah;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Sales app: add and update my own leads (lead profile). */
class LeadController extends Controller
{
    public function __construct(private LeadService $leads, private FollowUpService $followUps) {}

    public function index(Request $request): Response
    {
        $leads = Lead::query()->visibleTo($request->user())
            ->with(['customer', 'product', 'loans.unit.type'])
            ->latest('id')->limit(100)->get();

        return Inertia::render('Sales/Leads/Index', [
            'leads' => $leads->map(fn (Lead $l) => [
                'id' => $l->id,
                'code' => $l->code,
                'path_label' => $l->path->label(),
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

    public function show(Lead $lead): Response
    {
        Gate::authorize('view', $lead);
        $lead->load(['customer', 'product', 'accessories', 'loans.unit.type', 'followUps.user:id,name', 'invoices']);
        $c = $lead->customer;

        return Inertia::render('Sales/Leads/Show', [
            'lead' => [
                'id' => $lead->id,
                'code' => $lead->code,
                'name' => $c->name,
                'location' => collect([$c->city, $c->province])->filter()->implode(', '),
                'gender' => $c->gender?->label(),
                'age_range' => $c->age_range?->value,
                'path' => $lead->path->value,
                'path_label' => $lead->path->label(),
                'product' => $lead->productName(),
                'trial_start' => $lead->trialStart()?->locale('id')->translatedFormat('j M Y'),
                'accessories' => $lead->accessories->map(fn ($p) => $p->name.' x'.$p->pivot->quantity)->values(),
                'symptom_form_filled' => $lead->symptom_form_filled,
                'status' => $lead->status->value,
                'status_label' => $lead->status->label(),
                'followup_count' => $lead->followup_count,
                'response' => $lead->overall_response?->value,
                'response_label' => $lead->overall_response?->label(),
            ],
            'followUps' => $lead->followUps->map(fn ($f) => [
                'id' => $f->id,
                'date' => $f->followed_up_on->locale('id')->translatedFormat('j M Y'),
                'channel' => $f->channel->label(),
                'response' => $f->response->value,
                'response_label' => $f->response->label(),
                'notes' => $f->notes,
                'by' => $f->user->name,
            ])->values(),
            'invoices' => $lead->invoices->map(fn ($i) => [
                'id' => $i->id, 'code' => $i->code, 'status' => $i->status->value, 'status_label' => $i->status->label(),
                'total' => Rupiah::format($i->total), 'issued_on' => $i->issued_on->locale('id')->translatedFormat('j M Y'),
            ])->values(),
            'responses' => OverallResponse::options(),
            'channels' => FollowUpChannel::options(),
            'today' => today()->toDateString(),
        ]);
    }

    public function storeFollowUp(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $this->followUps->add($lead, $this->followUps->validate($request->all()), $request->user());

        return back()->with('success', 'Follow-up ditambahkan.');
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
        $lead->load('customer', 'accessories');
        $c = $lead->customer;

        return Inertia::render('Sales/Leads/Form', ['lead' => [
            'id' => $lead->id,
            'code' => $lead->code,
            'path' => $lead->path->value,
            'accessories' => $lead->accessories->map(fn ($p) => ['product_id' => $p->id, 'quantity' => $p->pivot->quantity])->values(),
            'name' => $c->name, 'whatsapp_number' => $c->whatsapp_number ?? '', 'city' => $c->city ?? '', 'province' => $c->province ?? '',
            'gender' => $c->gender?->value ?? '', 'age_range' => $c->age_range?->value ?? '',
            'product_id' => $lead->product_id, 'trial_started_on' => $lead->trial_started_on?->toDateString() ?? '',
            'symptom_form_filled' => $lead->symptom_form_filled, 'status' => $lead->status->value,
            'cancel_reason' => $lead->cancel_reason?->value ?? '', 'notes' => $lead->notes ?? '',
        ]] + $this->options());
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $this->leads->update($lead, $this->leads->validate($request->all() + ['path' => $lead->path->value]), $request->user());

        return redirect()->route('sales.leads.show', $lead)->with('success', 'Lead diperbarui.');
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'genders' => Gender::options(),
            'ageRanges' => AgeRange::options(),
            'statuses' => LeadStatus::options(),
            'cancelReasons' => LeadCancelReason::options(),
            'paths' => LeadPath::options(),
            'provinces' => array_values(Provinces::ALL),
            'products' => Product::active()->ofCategory(ProductCategory::Main)->orderBy('name')->get()
                ->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name, 'price' => Rupiah::format($p->price)])->values(),
            'accessoryProducts' => Product::active()->ofCategory(ProductCategory::Accessory)->orderBy('name')->get()
                ->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name, 'price' => Rupiah::format($p->price)])->values(),
            'today' => today()->toDateString(),
        ];
    }
}
