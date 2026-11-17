<?php

use App\Models\Customer;
use App\Models\ExperienceMessage;
use App\Models\FollowupSchedule;
use App\Models\FollowupTemplate;
use App\Models\Unit;
use App\Models\User;
use App\Services\FollowupRenderer;
use App\Services\LoanService;
use App\Services\ReturnService;
use Illuminate\Support\Facades\DB;

const TOKEN = 'test-token-123';

function approve(string $step, string $group = 'borrower'): FollowupTemplate
{
    $template = FollowupTemplate::where('group', $group)->where('step_key', $step)->firstOrFail();
    $template->update(['is_active' => true, 'approved_at' => now(), 'approved_by_id' => User::factory()->admin()->create()->id]);

    return $template;
}

function api(object $test, string $method, string $uri, array $data = [], ?string $token = TOKEN)
{
    return $test->withHeaders($token ? ['Authorization' => "Bearer {$token}"] : [])->{$method.'Json'}($uri, $data);
}

beforeEach(function () {
    config(['services.n8n.token' => TOKEN]);
    $this->admin = User::factory()->admin()->create();
    $this->dewi = User::factory()->sales()->create(['name' => 'Dewi Lestari']);
    $this->unit = Unit::factory()->create(['code' => 'NK-0012']);

    // Creates a loan through the real service; the customer is created by the service from the number.
    $this->loan = fn (array $overrides = [], string $number = '6281230000101') => app(LoanService::class)->create([
        'unit_id' => Unit::factory()->create()->id, 'type' => 'home_trial',
        'starts_on' => today()->toDateString(), 'due_on' => today()->addDays(3)->toDateString(),
        'customer' => ['name' => 'Sari Wulandari', 'whatsapp_number' => $number, 'address' => 'Jl. Contoh 1'],
        'consent_data' => true, 'consent_whatsapp' => true, ...$overrides,
    ], $this->dewi);
});

// ---------- authentication ----------
it('keeps the webhook endpoints closed without the right token', function () {
    api($this, 'get', '/api/webhooks/n8n/followups/due', token: null)->assertUnauthorized();
    api($this, 'get', '/api/webhooks/n8n/followups/due', token: 'salah')->assertUnauthorized();
    api($this, 'post', '/api/webhooks/n8n/replies', ['chat_id' => 'x@c.us'], token: 'salah')->assertUnauthorized();
    api($this, 'post', '/api/webhooks/n8n/followups/1/result', ['status' => 'sent'], token: null)->assertUnauthorized();
    api($this, 'get', '/api/webhooks/n8n/followups/due')->assertOk();
});

it('refuses everything when no token is configured on the server', function () {
    config(['services.n8n.token' => '']);

    api($this, 'get', '/api/webhooks/n8n/followups/due', token: '')->assertStatus(503);
    api($this, 'get', '/api/webhooks/n8n/followups/due', token: 'apa-saja')->assertStatus(503);
});

// ---------- planning ----------
it('plans nothing while the wording is not approved', function () {
    ($this->loan)();

    expect(FollowupSchedule::count())->toBe(0);
});

it('plans first session, daily check-ins and the day-before reminder for an opted-in home trial', function () {
    foreach (['first_session', 'daily', 'reminder_h1'] as $step) {
        approve($step);
    }

    $loan = ($this->loan)(['starts_on' => today()->toDateString(), 'due_on' => today()->addDays(4)->toDateString()]);
    $steps = FollowupSchedule::where('loan_id', $loan->id)->orderBy('send_at')->get();

    // Days +1, +2, +3 are daily check-ins; the reminder falls on the day before the due date (+3), at 10:00 Jakarta.
    expect($steps->where('step_key', 'first_session'))->toHaveCount(1)
        ->and($steps->where('step_key', 'daily'))->toHaveCount(3)
        ->and($steps->where('step_key', 'reminder_h1'))->toHaveCount(1)
        ->and($steps->every(fn ($s) => $s->status === 'pending'))->toBeTrue();

    $reminder = $steps->firstWhere('step_key', 'reminder_h1');
    expect($reminder->send_at->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'))->toBe(today()->addDays(3)->format('Y-m-d').' 10:00');
});

it('does not plan for events, customers without opt-in, or people who opted out', function () {
    approve('first_session');

    ($this->loan)(['type' => 'event', 'event_name' => 'Pameran', 'customer' => null]);
    ($this->loan)(['consent_whatsapp' => false], '6281230000102');
    Customer::factory()->create(['whatsapp_number' => '6281230000103', 'whatsapp_consent_at' => now(), 'whatsapp_opted_out_at' => now()]);
    ($this->loan)([], '6281230000103');

    expect(FollowupSchedule::count())->toBe(0);
});

it('cancels the remaining check-ins on return and queues the survey and offer', function () {
    foreach (['first_session', 'daily', 'reminder_h1', 'survey', 'offer'] as $step) {
        approve($step);
    }
    $loan = ($this->loan)(['due_on' => today()->addDays(5)->toDateString()]);
    $unit = $loan->unit;
    $unit->components()->create(['name' => 'Unit utama', 'is_present' => true]);

    app(ReturnService::class)->process($loan, $this->dewi, [['component' => 'Unit utama', 'is_complete' => true]], [], null, false, 'available');

    $byStatus = FollowupSchedule::where('loan_id', $loan->id)->get()->groupBy('status');
    expect($byStatus->get('cancelled')->pluck('step_key')->unique()->sort()->values()->all())->toBe(['daily', 'first_session', 'reminder_h1'])
        ->and($byStatus->get('pending')->pluck('step_key')->sort()->values()->all())->toBe(['offer', 'survey']);
});

// ---------- due list ----------
it('hands out only due, sendable messages for opted-in customers, with the text filled in', function () {
    approve('first_session');
    $loan = ($this->loan)();
    $schedule = FollowupSchedule::where('loan_id', $loan->id)->sole();

    // Not due yet.
    expect(api($this, 'get', '/api/webhooks/n8n/followups/due')->json('items'))->toBe([]);

    $this->travel(5)->hours();
    $items = api($this, 'get', '/api/webhooks/n8n/followups/due')->assertOk()->json('items');

    expect($items)->toHaveCount(1)
        ->and($items[0])->toMatchArray(['id' => $schedule->id, 'chat_id' => '6281230000101@c.us', 'consent_whatsapp' => true])
        ->and($items[0]['text'])->toContain('Sari Wulandari')->toContain($loan->unit->code)->not->toContain('{');
    expect($schedule->refresh())->status->toBe('sending')->attempts->toBe(1);

    // Already claimed: not offered again right away, only after the claim times out.
    expect(api($this, 'get', '/api/webhooks/n8n/followups/due')->json('items'))->toBe([]);
    $this->travel(31)->minutes();
    expect(api($this, 'get', '/api/webhooks/n8n/followups/due')->json('items'))->toHaveCount(1);
});

it('drops messages whose wording was switched off, whose customer opted out, or that are far overdue', function () {
    approve('first_session');
    $loan = ($this->loan)();
    $schedule = FollowupSchedule::where('loan_id', $loan->id)->sole();
    $this->travel(5)->hours();

    $schedule->template->update(['is_active' => false]);
    expect(api($this, 'get', '/api/webhooks/n8n/followups/due')->json('items'))->toBe([]);
    expect($schedule->refresh()->status)->toBe('skipped');

    $schedule->template->update(['is_active' => true]);
    $schedule->update(['status' => 'pending']);
    $loan->customer->update(['whatsapp_opted_out_at' => now()]);
    expect(api($this, 'get', '/api/webhooks/n8n/followups/due')->json('items'))->toBe([]);
    expect($schedule->refresh()->status)->toBe('cancelled');

    $loan->customer->update(['whatsapp_opted_out_at' => null]);
    $schedule->update(['status' => 'pending', 'send_at' => now()->subHours(13)]);
    expect(api($this, 'get', '/api/webhooks/n8n/followups/due')->json('items'))->toBe([]);
    expect($schedule->refresh()->status)->toBe('skipped');
});

it('does not send check-ins for a loan that has already been returned', function () {
    approve('daily');
    $loan = ($this->loan)(['starts_on' => today()->subDays(2)->toDateString(), 'due_on' => today()->addDays(2)->toDateString()]);
    $schedule = FollowupSchedule::where('loan_id', $loan->id)->first();
    $schedule->update(['send_at' => now()->subMinute()]);
    $loan->update(['status' => 'returned', 'returned_at' => now()]);

    expect(api($this, 'get', '/api/webhooks/n8n/followups/due')->json('items'))->toBe([]);
    expect($schedule->refresh()->status)->toBe('cancelled');
});

// ---------- results ----------
it('records a sent message on the customer timeline and ignores a repeated report', function () {
    approve('first_session');
    $loan = ($this->loan)();
    $schedule = FollowupSchedule::where('loan_id', $loan->id)->sole();
    $this->travel(5)->hours();
    api($this, 'get', '/api/webhooks/n8n/followups/due');

    api($this, 'post', "/api/webhooks/n8n/followups/{$schedule->id}/result", ['status' => 'sent', 'waha_message_id' => 'true_628@c.us_X1'])
        ->assertOk()->assertJson(['status' => 'sent']);
    api($this, 'post', "/api/webhooks/n8n/followups/{$schedule->id}/result", ['status' => 'sent', 'waha_message_id' => 'true_628@c.us_X1'])->assertOk();

    expect($schedule->refresh())->status->toBe('sent')->waha_message_id->toBe('true_628@c.us_X1');
    expect(ExperienceMessage::where('direction', 'out')->count())->toBe(1)
        ->and(ExperienceMessage::where('direction', 'out')->sole()->body)->toContain('Sari Wulandari');

    // A late "failed" report cannot undo a sent message.
    api($this, 'post', "/api/webhooks/n8n/followups/{$schedule->id}/result", ['status' => 'failed', 'error' => 'x'])->assertOk();
    expect($schedule->refresh()->status)->toBe('sent');
});

it('retries a failed message later and gives up after three attempts', function () {
    approve('first_session');
    $loan = ($this->loan)();
    $schedule = FollowupSchedule::where('loan_id', $loan->id)->sole();
    $this->travel(5)->hours();

    foreach ([1, 2, 3] as $attempt) {
        expect(api($this, 'get', '/api/webhooks/n8n/followups/due')->json('items'))->toHaveCount(1);
        api($this, 'post', "/api/webhooks/n8n/followups/{$schedule->id}/result", ['status' => 'failed', 'error' => 'WAHA timeout'])->assertOk();
        $this->travel(20)->minutes();
    }

    expect($schedule->refresh())->status->toBe('failed')->attempts->toBe(3)->error->toBe('WAHA timeout');
    expect(api($this, 'get', '/api/webhooks/n8n/followups/due')->json('items'))->toBe([]);
});

it('validates result reports', function () {
    api($this, 'post', '/api/webhooks/n8n/followups/999999/result', ['status' => 'sent'])->assertNotFound();
    api($this, 'post', '/api/webhooks/n8n/followups/1/result', ['status' => 'maybe'])->assertStatus(404);
});

// ---------- replies ----------
it('ignores messages from numbers that are not customers, and bot commands from customers', function () {
    ($this->loan)();

    api($this, 'post', '/api/webhooks/n8n/replies', ['chat_id' => '6289999999999@c.us', 'phone' => '6289999999999', 'text' => 'Halo, mau tanya'])
        ->assertOk()->assertExactJson(['handled' => false]);

    foreach (['menu', '3', '0', 'Halo'] as $word) {
        api($this, 'post', '/api/webhooks/n8n/replies', ['chat_id' => '6281230000101@c.us', 'phone' => '6281230000101', 'text' => $word])
            ->assertExactJson(['handled' => false]);
    }

    expect(ExperienceMessage::count())->toBe(0);
});

it('stores a follow-up reply on the lead timeline and keeps the bot out', function () {
    $loan = ($this->loan)();

    api($this, 'post', '/api/webhooks/n8n/replies', [
        'message_id' => 'true_628_A1', 'chat_id' => '6281230000101@c.us', 'phone' => '6281230000101', 'text' => 'Alatnya nyaman dipakai, terima kasih',
    ])->assertOk()->assertExactJson(['handled' => true]);

    $message = ExperienceMessage::sole();
    expect($message)->direction->toBe('in')->is_serious->toBeFalse()->lead_id->toBe($loan->lead_id)->loan_id->toBe($loan->id)
        ->body->toBe('Alatnya nyaman dipakai, terima kasih');
});

it('answers a redelivered message the same way without storing it twice', function () {
    ($this->loan)();
    $payload = ['message_id' => 'true_628_DUP', 'chat_id' => '6281230000101@c.us', 'phone' => '6281230000101', 'text' => 'sesak napas'];

    $first = api($this, 'post', '/api/webhooks/n8n/replies', $payload)->json();
    $second = api($this, 'post', '/api/webhooks/n8n/replies', $payload)->json();

    expect($second)->toBe($first)->and(ExperienceMessage::count())->toBe(1);
    expect(DB::table('notifications')->where('notifiable_id', $this->admin->id)->count())->toBe(1);
});

it('treats replies after the follow-up window as ordinary bot chat', function () {
    $loan = ($this->loan)();
    $loan->update(['status' => 'returned', 'returned_at' => now()->subDays(40)]);

    api($this, 'post', '/api/webhooks/n8n/replies', ['chat_id' => '6281230000101@c.us', 'phone' => '6281230000101', 'text' => 'apakah ada garansi?'])
        ->assertExactJson(['handled' => false]);
});

// ---------- stop ----------
it('honors a stop request: opts the customer out, cancels pending messages and confirms', function () {
    foreach (['first_session', 'daily'] as $step) {
        approve($step);
    }
    $loan = ($this->loan)(['due_on' => today()->addDays(4)->toDateString()]);
    expect(FollowupSchedule::where('status', 'pending')->count())->toBeGreaterThan(0);

    $response = api($this, 'post', '/api/webhooks/n8n/replies', ['message_id' => 'true_STOP', 'chat_id' => '6281230000101@c.us', 'phone' => '6281230000101', 'text' => 'Berhenti ya, jangan kirim lagi'])
        ->assertOk()->assertJson(['handled' => true]);

    expect($response->json('reply'))->toContain('tidak akan mengirim pesan follow-up lagi');
    expect($loan->customer->refresh()->whatsapp_opted_out_at)->not->toBeNull()
        ->and(FollowupSchedule::where('status', 'pending')->count())->toBe(0)
        ->and(FollowupSchedule::where('status', 'cancelled')->count())->toBeGreaterThan(0);

    // Opted out: later ordinary messages go to the bot.
    api($this, 'post', '/api/webhooks/n8n/replies', ['chat_id' => '6281230000101@c.us', 'phone' => '6281230000101', 'text' => 'oke terima kasih'])
        ->assertExactJson(['handled' => false]);
});

// ---------- serious complaints ----------
it('flags a serious complaint, alerts admins and the sales, and stops automatic messages', function () {
    approve('daily');
    $loan = ($this->loan)(['starts_on' => today()->toDateString(), 'due_on' => today()->addDays(4)->toDateString()]);

    $response = api($this, 'post', '/api/webhooks/n8n/replies', ['message_id' => 'true_SER', 'chat_id' => '6281230000101@c.us', 'phone' => '6281230000101', 'text' => 'Saya jadi sesak dan dada terasa nyeri'])
        ->assertOk()->assertJson(['handled' => true]);

    $message = ExperienceMessage::sole();
    expect($message->is_serious)->toBeTrue()
        ->and($loan->lead->refresh()->serious_complaint_at)->not->toBeNull()
        ->and(FollowupSchedule::where('status', 'pending')->count())->toBe(0);

    $notified = DB::table('notifications')->pluck('notifiable_id')->all();
    expect($notified)->toContain($this->admin->id)->toContain($this->dewi->id);
    expect(DB::table('notifications')->first()->data)->toContain('Keluhan serius')->toContain('Sari Wulandari');

    // The standard wording is not approved yet, so nothing medical is sent to the customer.
    expect($response->json())->not->toHaveKey('reply');
});

it('sends the standard safety wording only after it has been approved', function () {
    ($this->loan)();
    approve('safety_reply', 'system');

    $reply = api($this, 'post', '/api/webhooks/n8n/replies', ['message_id' => 'true_SER2', 'chat_id' => '6281230000101@c.us', 'phone' => '6281230000101', 'text' => 'pingsan tadi pagi'])
        ->assertOk()->json('reply');

    expect($reply)->toContain('hentikan pemakaian')->toContain('dokter');
});

it('flags a serious message even after the follow-up window or an opt-out', function () {
    $loan = ($this->loan)();
    $loan->update(['status' => 'returned', 'returned_at' => now()->subDays(60)]);
    $loan->customer->update(['whatsapp_opted_out_at' => now()]);

    api($this, 'post', '/api/webhooks/n8n/replies', ['chat_id' => '6281230000101@c.us', 'phone' => '6281230000101', 'text' => 'sakit dada sejak kemarin setelah pakai'])
        ->assertJson(['handled' => true]);

    expect(ExperienceMessage::sole()->is_serious)->toBeTrue();
});

it('matches keywords from the start of a word, any case, and ignores switched-off keywords', function () {
    ($this->loan)();
    DB::table('safety_keywords')->where('keyword', 'sesak')->update(['is_active' => false]);

    api($this, 'post', '/api/webhooks/n8n/replies', ['message_id' => 'a', 'chat_id' => '6281230000101@c.us', 'phone' => '6281230000101', 'text' => 'SESAKNYA parah'])->assertOk();
    expect(ExperienceMessage::sole()->is_serious)->toBeFalse();

    DB::table('safety_keywords')->where('keyword', 'sesak')->update(['is_active' => true]);
    api($this, 'post', '/api/webhooks/n8n/replies', ['message_id' => 'b', 'chat_id' => '6281230000101@c.us', 'phone' => '6281230000101', 'text' => 'SESAKNYA parah'])->assertOk();
    expect(ExperienceMessage::where('message_key', 'b')->sole()->is_serious)->toBeTrue();
});

it('validates reply payloads', function () {
    api($this, 'post', '/api/webhooks/n8n/replies', [])->assertUnprocessable();
    api($this, 'post', '/api/webhooks/n8n/replies', ['chat_id' => 'x', 'text' => str_repeat('a', 4001)])->assertUnprocessable();
});

// ---------- wording rules ----------
it('ships draft wording that is switched off, free of health claims, and uses known placeholders only', function () {
    $templates = FollowupTemplate::all();

    expect($templates)->toHaveCount(7)
        ->and($templates->where('group', 'borrower')->every(fn ($t) => ! $t->is_active && $t->approved_at === null))->toBeTrue()
        ->and(FollowupTemplate::where('step_key', 'safety_reply')->sole()->is_active)->toBeFalse();

    foreach ($templates as $template) {
        expect(FollowupTemplate::forbiddenClaimsIn($template->body))->toBe([], "claim in {$template->step_key}");
        preg_match_all('/\{[a-z_]+\}/', $template->body, $found);
        expect(array_diff($found[0], FollowupRenderer::PLACEHOLDERS))->toBe([], "placeholder in {$template->step_key}");
    }
});

it('detects forbidden health claims', function () {
    expect(FollowupTemplate::forbiddenClaimsIn('Alat ini bisa menyembuhkan dan mencegah'))->toBe(['menyembuhkan', 'mencegah'])
        ->and(FollowupTemplate::forbiddenClaimsIn('Terapi hidrogen untuk penyakit'))->toBe(['terapi', 'penyakit'])
        ->and(FollowupTemplate::forbiddenClaimsIn('Bagaimana pengalaman memakai unit?'))->toBe([]);
});

it('hands out at most one message per customer per poll', function () {
    approve('daily');
    $loan = ($this->loan)(['starts_on' => today()->subDays(3)->toDateString(), 'due_on' => today()->addDays(3)->toDateString()]);
    FollowupSchedule::where('loan_id', $loan->id)->update(['send_at' => now()->subHour()]);
    $pending = FollowupSchedule::where('loan_id', $loan->id)->count();

    expect($pending)->toBeGreaterThan(1)
        ->and(api($this, 'get', '/api/webhooks/n8n/followups/due')->json('items'))->toHaveCount(1)
        ->and(FollowupSchedule::where('loan_id', $loan->id)->where('status', 'pending')->count())->toBe($pending - 1);
});
