<?php

use App\Filament\Resources\Leads\Pages\ViewLead;
use App\Models\ActivityLog;
use App\Models\Lead;
use App\Models\Product;
use App\Models\SymptomForm;
use App\Models\Unit;
use App\Models\User;
use App\Services\LeadService;
use App\Services\SymptomFormService;
use App\Support\HealthSurvey;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->dewi = User::factory()->sales()->create(['name' => 'Dewi']);
    $this->rizky = User::factory()->sales()->create(['name' => 'Rizky']);
    $home = Product::create(['name' => 'H2 Home', 'category' => 'main', 'price' => 1]);
    $this->unit = Unit::factory()->create();

    $service = app(LeadService::class);
    $this->lead = $service->create($service->validate([
        'name' => 'Siti Rahmawati', 'city' => 'Surabaya', 'province' => 'Jawa Timur', 'gender' => 'female', 'age_range' => '40-49',
        'path' => 'trial', 'product_id' => $home->id, 'status' => 'new',
    ]), $this->dewi);

    $this->form = fn (array $o = []) => [
        'name' => 'Siti Rahmawati', 'gender' => 'female', 'birth_date' => '1980-05-12', 'whatsapp_number' => '0812 3000 0201',
        'referred_by' => 'Bu Ani', 'email' => 'siti@example.test', 'address' => 'Jl. Melati 5', 'city' => 'Surabaya',
        'consent_data' => true, 'consent_whatsapp' => true, 'filled_on' => today()->toDateString(),
        'activity_on' => '2026-11-20', 'session_location' => 'Aula RW 03', 'unit_id' => $this->unit->id, 'cup_serial' => 'GL-0042',
        'machine_taken_on' => '2026-11-21',
        'conditions' => ['symptoms' => ['sleep_difficulty'], 'cardiovascular' => ['high_bp'], 'tumor' => ['none']],
        'other_conditions' => ['cardiovascular' => 'Kolesterol borderline', 'metabolic' => ''],
        'sales_notes' => 'Antusias.', ...$o,
    ];
    $this->save = fn (array $o = [], ?User $by = null) => app(SymptomFormService::class)->save(
        $this->lead, app(SymptomFormService::class)->validate(($this->form)($o), $this->lead), $by ?? $this->dewi
    );
});

it('mirrors the printed survey: ten groups with the form wording', function () {
    expect(HealthSurvey::GROUPS)->toHaveCount(10)
        ->and(HealthSurvey::items('cardiovascular'))->toHaveKeys(['high_bp', 'stroke'])
        ->and(HealthSurvey::items('respiratory')['sleep_apnea'])->toBe('Henti napas saat tidur (sleep apnea)')
        ->and(HealthSurvey::hasOther('symptoms'))->toBeFalse()->and(HealthSurvey::hasOther('nervous'))->toBeTrue();
});

it('saves the form, updates the customer and flips the lead flag', function () {
    $form = ($this->save)();
    $lead = $this->lead->refresh();
    $customer = $lead->customer;

    expect($form->code)->toMatch('/^FG-\d{4}-\d{4}$/')
        ->and($lead->symptom_form_filled)->toBeTrue()
        ->and($lead->trial_started_on->toDateString())->toBe('2026-11-21')
        ->and($customer->birth_date->toDateString())->toBe('1980-05-12')
        ->and($customer->referred_by)->toBe('Bu Ani')->and($customer->email)->toBe('siti@example.test')
        ->and($customer->data_consent_at)->not->toBeNull()->and($customer->whatsapp_consent_at)->not->toBeNull()
        ->and($form->unit_id)->toBe($this->unit->id)
        ->and($form->conditions['cardiovascular'])->toBe(['high_bp'])
        ->and($form->other_conditions)->toBe(['cardiovascular' => 'Kolesterol borderline'])
        ->and($form->summaryLines())->toContain('Sistem kardiovaskular: Tekanan darah tinggi, Lainnya: Kolesterol borderline')
        ->and(ActivityLog::where('action', 'lead.symptom_form_saved')->count())->toBe(1);
});

it('keeps one form per lead and updates it on a second save', function () {
    ($this->save)();
    $second = ($this->save)(['conditions' => ['tumor' => ['had_cancer']], 'cancer_type' => 'Payudara', 'cancer_treatment' => 'Kemoterapi']);

    expect($this->lead->refresh()->symptomForm->id)->toBe($second->id)
        ->and(SymptomForm::count())->toBe(1)
        ->and($second->summaryLines())->toContain('Tumor / kanker: Pernah menderita kanker (Payudara, pengobatan: Kemoterapi)')
        ->and(ActivityLog::where('action', 'lead.symptom_form_saved')->count())->toBe(2);
});

it('drops cancer details when cancer is not ticked', function () {
    $form = ($this->save)(['conditions' => ['tumor' => ['none']], 'cancer_type' => 'Sisa isian lama']);
    expect($form->cancer_type)->toBeNull();
});

it('validates the form', function (array $override) {
    expect(fn () => app(SymptomFormService::class)->validate(($this->form)($override), $this->lead))->toThrow(ValidationException::class);
})->with([
    'no name' => [['name' => '']],
    'bad gender' => [['gender' => 'x']],
    'future birth date' => [['birth_date' => '2999-01-01']],
    'bad email' => [['email' => 'bukan-email']],
    'unknown condition' => [['conditions' => ['symptoms' => ['flying']]]],
    'unknown group' => [['conditions' => ['alien' => ['x']]]],
    'none and cancer together' => [['conditions' => ['tumor' => ['none', 'had_cancer']]]],
    'unknown unit' => [['unit_id' => 9999]],
    'future form date' => [['filled_on' => '2999-01-01']],
]);

it('requires data consent once, and never withdraws it on edit', function () {
    expect(fn () => app(SymptomFormService::class)->validate(($this->form)(['consent_data' => false]), $this->lead))->toThrow(ValidationException::class);

    ($this->save)();
    $given = $this->lead->refresh()->customer->data_consent_at;

    // Later edit without the box ticked still passes, and keeps the original timestamp.
    ($this->save)(['consent_data' => false]);
    expect($this->lead->refresh()->customer->data_consent_at->equalTo($given))->toBeTrue();
});

it('does not overwrite an existing trial start date', function () {
    $this->lead->update(['trial_started_on' => '2026-11-01']);
    ($this->save)();
    expect($this->lead->refresh()->trial_started_on->toDateString())->toBe('2026-11-01');
});

it('lets only the owning sales or admin fill it', function () {
    expect(fn () => ($this->save)([], $this->rizky))->toThrow(ValidationException::class);
    expect($this->lead->refresh()->symptom_form_filled)->toBeFalse();

    ($this->save)([], $this->admin);
    expect($this->lead->refresh()->symptom_form_filled)->toBeTrue();
});

describe('sales app', function () {
    it('shows the form with the survey groups, prefilled from the customer', function () {
        $this->actingAs($this->dewi)->get("/sales/leads/{$this->lead->id}/symptom-form")->assertInertia(fn (AssertableInertia $p) => $p
            ->component('Sales/Leads/SymptomForm')->has('groups', 10)->where('values.name', 'Siti Rahmawati')
            ->where('values.city', 'Surabaya')->where('lead.has_form', false));

        $this->actingAs($this->rizky)->get("/sales/leads/{$this->lead->id}/symptom-form")->assertForbidden();
    });

    it('saves the form and shows the summary on the lead detail', function () {
        $this->actingAs($this->dewi)->put("/sales/leads/{$this->lead->id}/symptom-form", ($this->form)())
            ->assertRedirect("/sales/leads/{$this->lead->id}");

        $this->actingAs($this->dewi)->get("/sales/leads/{$this->lead->id}")->assertInertia(fn (AssertableInertia $p) => $p
            ->where('lead.symptom_form_filled', true)->where('lead.symptom_form_code', fn ($c) => str_starts_with($c, 'FG-'))
            ->has('lead.symptom_summary', 3));
    });

    it('rejects an invalid save and another sales’ lead', function () {
        $this->actingAs($this->dewi)->put("/sales/leads/{$this->lead->id}/symptom-form", ($this->form)(['name' => '']))->assertSessionHasErrors('name');
        $this->actingAs($this->rizky)->put("/sales/leads/{$this->lead->id}/symptom-form", ($this->form)())->assertForbidden();
    });
});

describe('admin', function () {
    it('fills the form from the lead detail and shows the summary', function () {
        $this->actingAs($this->admin);

        Livewire::test(ViewLead::class, ['record' => $this->lead->id])
            ->assertSee('Form belum diisi')
            ->callAction('symptomForm', ($this->form)())
            ->assertHasNoFormErrors();

        expect($this->lead->refresh()->symptom_form_filled)->toBeTrue();

        Livewire::test(ViewLead::class, ['record' => $this->lead->id])
            ->assertSee('Sistem kardiovaskular: Tekanan darah tinggi')->assertSee('Aula RW 03');
    });

    it('lists the lead with the symptom flag derived from the form', function () {
        ($this->save)();
        expect(Lead::query()->where('symptom_form_filled', true)->count())->toBe(1);
    });
});
