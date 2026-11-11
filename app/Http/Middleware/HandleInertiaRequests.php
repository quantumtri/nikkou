<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => ($user = $request->user()) ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'first_name' => str($user->name)->before(' ')->toString(),
                    'role' => $user->role->value,
                    'role_label' => $user->role->label(),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
            'nav' => fn () => $user && ! $user->isWarehouse() ? [
                ['label' => 'Beranda', 'icon' => 'home', 'href' => route('sales.home'), 'active' => $request->routeIs('sales.home')],
                ['label' => 'Pinjam', 'icon' => 'plus', 'href' => route('sales.loans.create'), 'active' => $request->routeIs('sales.loans.create')],
                ['label' => 'Riwayat', 'icon' => 'history', 'href' => route('sales.loans.index'), 'active' => $request->routeIs('sales.loans.index', 'sales.loans.show')],
            ] : [],
        ];
    }
}
