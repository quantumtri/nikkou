<?php

namespace App\Http\Controllers\Sales;

use App\Enums\LoanStatus;
use App\Http\Controllers\Controller;
use App\Models\Loan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        // Sales home is always "my loans", including when an admin opens it.
        $mine = fn () => Loan::query()->where('sales_id', $user->id);

        return Inertia::render('Sales/Home', [
            'today' => now()->locale('id')->translatedFormat('l, j F Y'),
            'firstName' => str($user->name)->before(' ')->toString(),
            'stats' => [
                'active' => $mine()->where('status', LoanStatus::Active)->count(),
                'due_today' => $mine()->where('status', LoanStatus::Active)->whereDate('due_on', '<=', today())->count(),
            ],
            'loans' => $mine()
                ->with(['unit:id,code', 'customer:id,name'])
                ->orderByRaw("case when status = 'active' then 0 else 1 end")
                ->orderBy('due_on')
                ->limit(10)
                ->get()
                ->map(fn (Loan $loan) => [
                    'id' => $loan->id,
                    'borrower' => $loan->borrowerName(),
                    'unit_code' => $loan->unit->code,
                    'type_label' => $loan->type->label(),
                    'due_on' => $loan->due_on->locale('id')->translatedFormat('j M'),
                    'status_label' => $loan->isOverdue() ? 'Lewat jatuh tempo' : $loan->status->label(),
                    'is_active' => $loan->isActive(),
                ]),
        ]);
    }
}
