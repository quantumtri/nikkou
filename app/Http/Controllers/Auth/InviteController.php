<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/** Set-password page opened from an invite or reset link (signed, single use, latest link only). */
class InviteController extends Controller
{
    public function show(Request $request, User $user, UserService $users): Response|\Symfony\Component\HttpFoundation\Response
    {
        if (! $users->linkIsCurrent($user, (int) $request->query('t'))) {
            return Inertia::render('Auth/InviteInvalid')->toResponse($request)->setStatusCode(410);
        }

        return Inertia::render('Auth/Invite', [
            'name' => $user->name,
            'email' => $user->email,
            'isInvite' => $user->email_verified_at === null,
        ]);
    }

    public function store(Request $request, User $user, UserService $users): RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        if (! $users->linkIsCurrent($user, (int) $request->query('t'))) {
            return Inertia::render('Auth/InviteInvalid')->toResponse($request)->setStatusCode(410);
        }

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ], [
            'password.confirmed' => 'Konfirmasi kata sandi tidak sama.',
            'password.min' => 'Kata sandi minimal 10 karakter.',
            'password.letters' => 'Kata sandi harus mengandung huruf.',
            'password.numbers' => 'Kata sandi harus mengandung angka.',
        ], ['password' => 'kata sandi']);

        $users->acceptLink($user, $data['password']);

        // Someone else may be signed in on this browser (an admin testing the link): sign them out so the
        // new person logs in cleanly, and so the login page is not skipped by the guest redirect.
        if (Auth::check()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login')->with('success', 'Kata sandi tersimpan. Silakan masuk dengan email Anda.');
    }
}
