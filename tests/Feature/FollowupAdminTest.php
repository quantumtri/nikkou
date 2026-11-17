<?php

use App\Filament\Resources\CustomerMessages\CustomerMessageResource;
use App\Filament\Resources\CustomerMessages\Pages\ListCustomerMessages;
use App\Filament\Resources\FollowupSchedules\FollowupScheduleResource;
use App\Filament\Resources\FollowupTemplates\FollowupTemplateResource;
use App\Filament\Resources\FollowupTemplates\Pages\EditFollowupTemplate;
use App\Filament\Resources\FollowupTemplates\Pages\ListFollowupTemplates;
use App\Filament\Resources\SafetyKeywords\Pages\CreateSafetyKeyword;
use App\Filament\Resources\SafetyKeywords\Pages\ListSafetyKeywords;
use App\Filament\Resources\SafetyKeywords\SafetyKeywordResource;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\ExperienceMessage;
use App\Models\FollowupTemplate;
use App\Models\Loan;
use App\Models\SafetyKeyword;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->daily = FollowupTemplate::where('step_key', 'daily')->firstOrFail();
});

it('limits the follow-up pages to admins', function () {
    $urls = [
        FollowupTemplateResource::getUrl('index'),
        FollowupScheduleResource::getUrl('index'),
        CustomerMessageResource::getUrl('index'),
        SafetyKeywordResource::getUrl('index'),
    ];

    foreach ($urls as $url) {
        $this->actingAs($this->admin)->get($url)->assertOk();
        $this->actingAs(User::factory()->warehouse()->create())->get($url)->assertForbidden();
        $this->actingAs(User::factory()->sales()->create())->get($url)->assertForbidden();
    }
});

it('rejects wording with health claims or unknown placeholders', function () {
    $this->actingAs($this->admin);

    Livewire::test(EditFollowupTemplate::class, ['record' => $this->daily->getRouteKey()])
        ->fillForm(['body' => 'Halo {nama}, alat ini bisa menyembuhkan dan mencegah penyakit.'])
        ->call('save')
        ->assertHasFormErrors(['body']);

    Livewire::test(EditFollowupTemplate::class, ['record' => $this->daily->getRouteKey()])
        ->fillForm(['body' => 'Halo {nama_lengkap}, bagaimana pemakaiannya?'])
        ->call('save')
        ->assertHasFormErrors(['body']);

    expect($this->daily->refresh()->body)->toContain('selamat pagi');
});

it('approves and activates wording, and sends it back to unapproved when the text changes', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListFollowupTemplates::class)
        ->assertSee('Belum disetujui')
        ->callTableAction('approve', $this->daily);

    expect($this->daily->refresh())->is_active->toBeTrue()->approved_by_id->toBe($this->admin->id)
        ->and($this->daily->isSendable())->toBeTrue();
    expect(ActivityLog::where('action', 'followup_template.approved')->exists())->toBeTrue();

    Livewire::test(EditFollowupTemplate::class, ['record' => $this->daily->getRouteKey()])
        ->fillForm(['body' => 'Halo {nama}, bagaimana kabar Anda dan unit {unit} hari ini?'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->daily->refresh())->is_active->toBeFalse()->approved_at->toBeNull()->and($this->daily->isSendable())->toBeFalse();

    Livewire::test(ListFollowupTemplates::class)->callTableAction('approve', $this->daily);
    Livewire::test(ListFollowupTemplates::class)->callTableAction('disable', $this->daily);
    expect($this->daily->refresh()->is_active)->toBeFalse();
});

it('refuses to activate wording that contains a health claim', function () {
    $this->daily->forceFill(['body' => 'Halo {nama}, terapi hidrogen membantu penyakit Anda'])->save();
    $this->actingAs($this->admin);

    Livewire::test(ListFollowupTemplates::class)->callTableAction('approve', $this->daily);

    expect($this->daily->refresh()->is_active)->toBeFalse();
});

it('manages safety keywords: add, reject duplicates, switch off and delete', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateSafetyKeyword::class)->fillForm(['keyword' => '  Sesak  '])->call('create')->assertHasFormErrors(['keyword']);
    Livewire::test(CreateSafetyKeyword::class)->fillForm(['keyword' => '  Gemetar  ', 'is_active' => true])->call('create')->assertHasNoFormErrors();

    $keyword = SafetyKeyword::where('keyword', 'gemetar')->sole();
    expect($keyword->is_active)->toBeTrue();

    Livewire::test(ListSafetyKeywords::class)->callTableAction('delete', $keyword);
    expect(SafetyKeyword::where('keyword', 'gemetar')->exists())->toBeFalse();
});

it('lets an admin resolve a serious complaint and counts open ones in the menu badge', function () {
    $customer = Customer::factory()->create();
    $message = ExperienceMessage::create(['customer_id' => $customer->id, 'direction' => 'in', 'body' => 'sesak napas', 'is_serious' => true, 'handled' => true]);
    ExperienceMessage::create(['customer_id' => $customer->id, 'direction' => 'in', 'body' => 'terima kasih', 'handled' => true]);
    $this->actingAs($this->admin);

    expect(CustomerMessageResource::getNavigationBadge())->toBe('1');

    Livewire::test(ListCustomerMessages::class)
        ->assertCanSeeTableRecords([$message])
        ->filterTable('open_serious')
        ->assertCanSeeTableRecords([$message])
        ->callTableAction('resolve', $message);

    expect($message->refresh()->resolved_at)->not->toBeNull()->and($message->resolved_by_id)->toBe($this->admin->id)
        ->and(CustomerMessageResource::getNavigationBadge())->toBeNull();
});

it('shows a sales only their own open serious complaints on the home page', function () {
    $dewi = User::factory()->sales()->create();
    $rizky = User::factory()->sales()->create();
    $mine = Loan::factory()->for($dewi, 'sales')->create();
    $theirs = Loan::factory()->for($rizky, 'sales')->create();

    $make = fn (Loan $loan, array $o = []) => ExperienceMessage::forceCreate([
        'customer_id' => $loan->customer_id, 'loan_id' => $loan->id, 'direction' => 'in', 'body' => 'pingsan tadi', 'is_serious' => true, 'handled' => true, ...$o,
    ]);
    $make($mine);
    $make($mine, ['resolved_at' => now()]);
    $make($theirs);

    $this->actingAs($dewi)->get('/sales')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('alerts', 1)
        ->where('alerts.0.customer', $mine->customer->name)
        ->where('alerts.0.whatsappUrl', fn (string $url) => str_starts_with($url, 'https://wa.me/')));

    $this->actingAs(User::factory()->sales()->create())->get('/sales')->assertInertia(fn (AssertableInertia $page) => $page->has('alerts', 0));
});
