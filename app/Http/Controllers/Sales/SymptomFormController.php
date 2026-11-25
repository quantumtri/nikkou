<?php

namespace App\Http\Controllers\Sales;

use App\Enums\Gender;
use App\Filament\Resources\Leads\Schemas\SymptomFormSchema;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Unit;
use App\Services\SymptomFormService;
use App\Support\HealthSurvey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Sales app: fill or update the initial symptom form of one of my leads. */
class SymptomFormController extends Controller
{
    public function __construct(private SymptomFormService $forms) {}

    public function edit(Lead $lead): Response
    {
        Gate::authorize('update', $lead);
        $lead->load('customer', 'symptomForm');

        $values = SymptomFormSchema::defaults($lead);
        // Every group needs an array and every "Lainnya" line a string for v-model.
        foreach (HealthSurvey::GROUPS as $key => [, , $hasOther]) {
            $values['conditions'][$key] ??= [];
            if ($hasOther) {
                $values['other_conditions'][$key] ??= '';
            }
        }

        return Inertia::render('Sales/Leads/SymptomForm', [
            'lead' => ['id' => $lead->id, 'code' => $lead->code, 'name' => $lead->customer->name, 'has_form' => $lead->symptomForm !== null,
                'consent_given' => (bool) $lead->customer->data_consent_at],
            'values' => array_map(fn ($v) => $v ?? '', $values),
            'groups' => collect(HealthSurvey::GROUPS)->map(fn ($g, $key) => [
                'key' => $key, 'label' => $g[0], 'has_other' => $g[2],
                'items' => collect($g[1])->map(fn ($label, $item) => ['key' => $item, 'label' => $label])->values(),
            ])->values(),
            'genders' => Gender::options(),
            'units' => Unit::orderBy('code')->get(['id', 'code'])->map(fn ($u) => ['id' => $u->id, 'code' => $u->code])->values(),
        ]);
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('update', $lead);
        $lead->load('customer');

        $this->forms->save($lead, $this->forms->validate($request->all(), $lead), $request->user());

        return redirect()->route('sales.leads.show', $lead)->with('success', 'Form gejala awal disimpan.');
    }
}
