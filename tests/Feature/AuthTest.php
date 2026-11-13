<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Loan;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

it('redirects guests to the login page', function () {
    $this->get('/sales')->assertRedirect('/login');
    $this->get('/admin')->assertRedirect();
});

it('sends each role to its own home after login', function (string $role, string $home) {
    $user = User::factory()->{$role}()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect($home);
    $this->assertAuthenticatedAs($user);
})->with([
    ['sales', '/sales'],
    ['admin', '/admin'],
    ['warehouse', '/admin'],
]);

it('rejects wrong passwords and inactive accounts with the same message', function () {
    $active = User::factory()->create();
    $inactive = User::factory()->inactive()->create();

    $this->post('/login', ['email' => $active->email, 'password' => 'salah'])->assertSessionHasErrors(['email' => 'Email atau kata sandi salah.']);
    $this->post('/login', ['email' => $inactive->email, 'password' => 'password'])->assertSessionHasErrors(['email' => 'Email atau kata sandi salah.']);
    $this->assertGuest();
});

it('rate limits repeated failed logins', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $_) {
        $this->post('/login', ['email' => $user->email, 'password' => 'salah']);
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('logs out an account that was deactivated mid-session', function () {
    $user = User::factory()->sales()->create();
    $this->actingAs($user);
    $user->update(['is_active' => false]);

    $this->get('/sales')->assertRedirect('/login');
    $this->assertGuest();
});

it('keeps warehouse staff out of the sales app', function () {
    $this->actingAs(User::factory()->warehouse()->create())->get('/sales')->assertForbidden();
});

it('keeps sales out of the admin panel', function () {
    $this->actingAs(User::factory()->sales()->create())->get('/admin')->assertForbidden();
});

it('lets admin and warehouse into the admin panel', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
    $this->actingAs(User::factory()->warehouse()->create())->get('/admin')->assertRedirect('/admin/units');
    $this->actingAs(User::factory()->warehouse()->create())->get('/admin/units')->assertOk();
});

it('shows a sales only their own loans on the home page', function () {
    $dewi = User::factory()->sales()->create();
    Loan::factory()->for($dewi, 'sales')->create();
    Loan::factory()->overdue()->for($dewi, 'sales')->create();
    Loan::factory()->count(3)->create();

    $this->actingAs($dewi)->get('/sales')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Sales/Home')
        ->where('stats.active', 2)
        ->where('stats.due_today', 1)
        ->has('loans', 2));
});

it('stores passwords hashed', function () {
    $user = User::factory()->create(['password' => 'rahasia-123']);

    expect($user->password)->not->toBe('rahasia-123')
        ->and(Hash::check('rahasia-123', $user->password))->toBeTrue();
});

it('leaves Inertia with a full page load when an admin signs in, so the panel is not shown in a modal', function () {
    $admin = User::factory()->admin()->create();

    $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->post('/login', ['email' => $admin->email, 'password' => 'password'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', '/admin');
});

it('keeps sales inside Inertia when they sign in', function () {
    $sales = User::factory()->sales()->create();

    $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->post('/login', ['email' => $sales->email, 'password' => 'password'])
        ->assertRedirect(route('sales.home'));
});

it('does not render the admin panel in a modal when the login was reached from the site root', function () {
    $admin = User::factory()->admin()->create();

    // A guest opens "/" first: the intended URL becomes "/", which itself redirects to the panel.
    $this->get('/')->assertRedirect('/login');

    $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->post('/login', ['email' => $admin->email, 'password' => 'password'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', '/admin');
});

it('answers an Inertia visit to the site root with a full page load for panel users', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request())])
        ->get('/')
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', '/admin');
});
