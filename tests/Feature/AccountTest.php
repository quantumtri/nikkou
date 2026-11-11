<?php

use App\Enums\UserRole;
use App\Exceptions\AccountRuleViolation;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\AccessMatrix;
use App\Models\ActivityLog;
use App\Models\Loan;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Rose Admin']);
    $this->service = app(UserService::class);
});

it('lets only admins manage accounts', function () {
    $this->actingAs($this->admin)->get(UserResource::getUrl('index'))->assertOk();
    $this->actingAs($this->admin)->get(UserResource::getUrl('create'))->assertOk();
    $this->actingAs(User::factory()->warehouse()->create())->get(UserResource::getUrl('index'))->assertForbidden();
    $this->actingAs(User::factory()->sales()->create())->get(UserResource::getUrl('index'))->assertForbidden();
});

it('never deletes accounts', function () {
    $user = User::factory()->sales()->create();
    $this->actingAs($this->admin);

    expect(UserResource::canDelete($user))->toBeFalse()
        ->and($this->admin->can('delete', $user))->toBeFalse();
});

it('creates an invited account that cannot sign in yet', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'Agus Setiawan', 'email' => 'Agus@Nikkou.test', 'role' => 'warehouse', 'whatsapp_number' => '0812 3000 0555'])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'agus@nikkou.test')->sole();

    expect($user->role)->toBe(UserRole::Warehouse)
        ->and($user->whatsapp_number)->toBe('6281230000555')
        ->and($user->is_active)->toBeTrue()
        ->and($user->invited_at)->not->toBeNull()
        ->and($user->email_verified_at)->toBeNull()
        ->and($this->service->status($user))->toBe('invited');

    // Nobody knows the placeholder password, so the account is unusable until the link is used.
    auth()->logout();
    $this->post('/login', ['email' => 'agus@nikkou.test', 'password' => 'password'])->assertSessionHasErrors('email');
    expect(ActivityLog::where('action', 'user.invited')->where('subject_id', $user->id)->exists())->toBeTrue();
});

it('validates the account form', function () {
    User::factory()->create(['email' => 'dewi@nikkou.test']);
    $this->actingAs($this->admin);

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => '', 'email' => 'dewi@nikkou.test', 'role' => 'sales', 'whatsapp_number' => '12345'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'email' => 'unique', 'whatsapp_number' => 'regex']);

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'X', 'email' => 'not-an-email', 'role' => 'sales'])
        ->call('create')
        ->assertHasFormErrors(['email' => 'email']);

    expect(User::count())->toBe(2);
});

it('lets an invited person set a password through the link, once', function () {
    $user = $this->service->invite(['name' => 'Agus', 'email' => 'agus@nikkou.test', 'role' => 'warehouse'], $this->admin);
    $link = $this->service->linkFor($user);

    $this->get($link)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Auth/Invite')->where('email', 'agus@nikkou.test')->where('isInvite', true));

    $this->post($link, ['password' => 'Rahasia12345', 'password_confirmation' => 'Rahasia12345'])->assertRedirect('/login');

    $user->refresh();
    expect(Hash::check('Rahasia12345', $user->password))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->invited_at)->toBeNull()
        ->and($this->service->status($user))->toBe('active');

    $this->post('/login', ['email' => 'agus@nikkou.test', 'password' => 'Rahasia12345'])->assertRedirect('/admin');

    // The same link cannot be reused.
    $this->get($link)->assertStatus(410);
    $this->post($link, ['password' => 'Lainnya12345', 'password_confirmation' => 'Lainnya12345'])->assertStatus(410);
    expect(Hash::check('Rahasia12345', $user->refresh()->password))->toBeTrue();
});

it('enforces the password rules on the invite page', function (string $password) {
    $user = $this->service->invite(['name' => 'Agus', 'email' => 'agus@nikkou.test', 'role' => 'sales'], $this->admin);
    $link = $this->service->linkFor($user);

    $this->post($link, ['password' => $password, 'password_confirmation' => $password])->assertSessionHasErrors('password');
    expect($user->refresh()->invited_at)->not->toBeNull();
})->with(['too short' => 'Abc123', 'no digits' => 'AbcdefghijKL', 'no letters' => '12345678901']);

it('requires matching confirmation', function () {
    $user = $this->service->invite(['name' => 'Agus', 'email' => 'agus@nikkou.test', 'role' => 'sales'], $this->admin);

    $this->post($this->service->linkFor($user), ['password' => 'Rahasia12345', 'password_confirmation' => 'Beda12345678'])->assertSessionHasErrors('password');
});

it('rejects tampered, expired and replaced links', function () {
    $user = $this->service->invite(['name' => 'Agus', 'email' => 'agus@nikkou.test', 'role' => 'sales'], $this->admin);
    $old = $this->service->linkFor($user);

    // Tampered signature or other user id.
    $this->get($old.'x')->assertForbidden();
    $someoneElse = User::factory()->sales()->create();
    $this->get(str_replace("/invite/{$user->id}", "/invite/{$someoneElse->id}", $old))->assertForbidden();

    // Expired.
    $this->travel(UserService::LINK_DAYS + 1)->days();
    $this->get($old)->assertForbidden();
    $this->travelBack();

    // A newer link replaces the old one.
    $this->travel(1)->minute();
    $new = $this->service->issueLink($user->refresh(), $this->admin);
    $this->get($old)->assertStatus(410);
    $this->get($new)->assertOk();
});

it('issues a reset link for an active account without logging it out of the active state', function () {
    $user = User::factory()->sales()->create();

    $link = $this->service->issueLink($user, $this->admin);

    expect($this->service->status($user->refresh()))->toBe('active');
    $this->get($link)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('isInvite', false));
    $this->post($link, ['password' => 'BaruSekali123', 'password_confirmation' => 'BaruSekali123'])->assertRedirect('/login');
    $this->post('/login', ['email' => $user->email, 'password' => 'BaruSekali123'])->assertRedirect('/sales');
});

it('does not issue links for inactive accounts', function () {
    $user = User::factory()->sales()->inactive()->create();

    expect(fn () => $this->service->issueLink($user, $this->admin))->toThrow(AccountRuleViolation::class);
});

it('deactivates an account, ends its sessions and blocks login, then reactivates it', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->sales()->create();
    DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);

    $this->service->deactivate($user, $this->admin);

    expect($user->refresh()->is_active)->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and($this->service->status($user))->toBe('inactive');
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');

    $this->service->activate($user, $this->admin);
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/sales');
    expect(ActivityLog::whereIn('action', ['user.deactivated', 'user.activated'])->count())->toBe(2);
});

it('protects the last active admin and the acting admin', function () {
    expect(fn () => $this->service->deactivate($this->admin, $this->admin))->toThrow(AccountRuleViolation::class, 'sendiri');

    $second = User::factory()->admin()->create();
    $this->service->deactivate($second, $this->admin);                       // allowed: another admin remains
    expect($second->refresh()->is_active)->toBeFalse();

    // Only $this->admin is left; another admin cannot remove them either.
    $third = User::factory()->admin()->create();
    $this->service->deactivate($this->admin, $third);                        // allowed: $third remains
    expect(fn () => $this->service->deactivate($third, $this->admin->refresh()))->toThrow(AccountRuleViolation::class);
});

it('refuses to demote the last admin or oneself', function () {
    $other = User::factory()->admin()->create();

    expect(fn () => $this->service->update($this->admin, ['role' => 'sales'], $this->admin))->toThrow(AccountRuleViolation::class, 'sendiri');

    $this->service->deactivate($other, $this->admin);
    $solo = User::factory()->admin()->create();
    $this->service->deactivate($this->admin, $solo);
    expect(fn () => $this->service->update($solo, ['role' => 'sales'], $this->admin->refresh()))->toThrow(AccountRuleViolation::class);
});

it('changes a role, logs it and signs the person out', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->sales()->create();
    DB::table('sessions')->insert(['id' => 'xyz', 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);

    $this->service->update($user, ['role' => 'warehouse', 'name' => 'Agus'], $this->admin);

    expect($user->refresh()->role)->toBe(UserRole::Warehouse)->and($user->name)->toBe('Agus')
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
    expect(ActivityLog::where('action', 'user.role_changed')->sole())->from->toBe('sales')->to->toBe('warehouse');
});

it('does not move a sales with active loans to another role', function () {
    $sales = User::factory()->sales()->create();
    Loan::factory()->for($sales, 'sales')->create();

    expect(fn () => $this->service->update($sales, ['role' => 'warehouse'], $this->admin))->toThrow(AccountRuleViolation::class, 'memegang unit');
    expect($sales->refresh()->role)->toBe(UserRole::Sales);

    Loan::query()->update(['status' => 'returned']);
    $this->service->update($sales, ['role' => 'warehouse'], $this->admin);
    expect($sales->refresh()->role)->toBe(UserRole::Warehouse);
});

it('edits an account from the admin form and shows rule errors there', function () {
    $user = User::factory()->sales()->create(['name' => 'Dewi']);
    $this->actingAs($this->admin);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['name' => 'Dewi Lestari', 'whatsapp_number' => '0812-3000-0111'])
        ->call('save')
        ->assertHasNoFormErrors();
    expect($user->refresh())->name->toBe('Dewi Lestari')->whatsapp_number->toBe('6281230000111')->email->toBe($user->email);

    // The email cannot be changed from the form.
    $email = $user->email;
    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])->fillForm(['email' => 'lain@nikkou.test'])->call('save');
    expect($user->refresh()->email)->toBe($email);

    // Demoting yourself is rejected with a message.
    Livewire::test(EditUser::class, ['record' => $this->admin->getRouteKey()])
        ->fillForm(['role' => 'sales'])
        ->call('save')
        ->assertHasFormErrors(['role']);
    expect($this->admin->refresh()->role)->toBe(UserRole::Admin);
});

it('lists accounts with status and filters', function () {
    $active = User::factory()->sales()->create(['name' => 'Dewi']);
    $invited = $this->service->invite(['name' => 'Agus', 'email' => 'agus@nikkou.test', 'role' => 'warehouse'], $this->admin);
    $inactive = User::factory()->sales()->inactive()->create(['name' => 'Budi']);
    $this->actingAs($this->admin);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$active, $invited, $inactive, $this->admin])
        ->assertSee('Diundang')->assertSee('Nonaktif')->assertSee('Aktif')
        ->filterTable('status', 'invited')->assertCanSeeTableRecords([$invited])->assertCanNotSeeTableRecords([$active, $inactive])
        ->filterTable('status', 'inactive')->assertCanSeeTableRecords([$inactive])->assertCanNotSeeTableRecords([$active, $invited])
        ->filterTable('status', 'active')->assertCanSeeTableRecords([$active, $this->admin])->assertCanNotSeeTableRecords([$invited, $inactive]);

    Livewire::test(ListUsers::class)->filterTable('role', 'warehouse')->assertCanSeeTableRecords([$invited])->assertCanNotSeeTableRecords([$active]);
    Livewire::test(ListUsers::class)->searchTable('agus@')->assertCanSeeTableRecords([$invited])->assertCanNotSeeTableRecords([$active]);
});

it('builds a WhatsApp invite link without health claims', function () {
    $user = $this->service->invite(['name' => 'Agus', 'email' => 'agus@nikkou.test', 'role' => 'warehouse', 'whatsapp_number' => '081230000555'], $this->admin);
    $url = $this->service->whatsappInviteUrl($user, $this->service->linkFor($user));

    expect($url)->toStartWith('https://wa.me/6281230000555?text=')
        ->and(urldecode($url))->toContain('Staf gudang')->toContain('/invite/'.$user->id);
});

it('shows the access matrix on the accounts page', function () {
    $this->actingAs($this->admin)->get(UserResource::getUrl('index'))->assertOk();
    Livewire::test(AccessMatrix::class)->assertSee('Hak akses menu')->assertSee('Staf gudang')->assertSee('Milik sendiri');
});

it('shows the login page message after a password is set', function () {
    $this->withSession(['success' => 'Kata sandi tersimpan.'])->get('/login')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('flash.success', 'Kata sandi tersimpan.'));
});

it('signs out whoever is logged in on the browser when a link is used', function () {
    $user = $this->service->invite(['name' => 'Agus', 'email' => 'agus@nikkou.test', 'role' => 'sales'], $this->admin);

    $this->actingAs($this->admin)
        ->post($this->service->linkFor($user), ['password' => 'Rahasia12345', 'password_confirmation' => 'Rahasia12345'])
        ->assertRedirect('/login');

    $this->assertGuest();
});
