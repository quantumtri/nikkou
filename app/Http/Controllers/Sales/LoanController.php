<?php

namespace App\Http\Controllers\Sales;

use App\Enums\LoanType;
use App\Exceptions\UnitNotLoanable;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLoanRequest;
use App\Models\Loan;
use App\Models\Unit;
use App\Services\LoanService;
use App\Services\UnitStatusService;
use App\Support\WhatsApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoanController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Loan::class);

        // "Riwayat" in the sales app is always the user's own loans.
        $loans = Loan::query()
            ->where('sales_id', $request->user()->id)
            ->with(['unit:id,code', 'customer:id,name'])
            ->latest('starts_on')
            ->latest('id')
            ->simplePaginate(20)
            ->through(fn (Loan $loan) => self::row($loan));

        return Inertia::render('Sales/Loans/Index', ['loans' => $loans]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Loan::class);

        return Inertia::render('Sales/Loans/Create', [
            'today' => today()->toDateString(),
            'defaultDue' => today()->addDays(3)->toDateString(),
            'types' => LoanType::options(),
            'canOverride' => $request->user()->can('override', Loan::class),
            'salesName' => $request->user()->name,
        ]);
    }

    public function store(StoreLoanRequest $request, LoanService $loans): RedirectResponse
    {
        $data = $request->validated();
        $unit = Unit::query()->where('code', $data['unit_code'])->firstOrFail();

        if (filled($data['override_reason'] ?? null)) {
            Gate::authorize('override', Loan::class);
        }

        try {
            $loan = $loans->create([
                'unit_id' => $unit->id,
                'type' => $data['type'],
                'starts_on' => $data['starts_on'],
                'due_on' => $data['due_on'],
                'event_name' => $data['event_name'] ?? null,
                'customer' => filled($data['customer_name'] ?? null) ? [
                    'name' => $data['customer_name'],
                    'whatsapp_number' => $data['whatsapp_number'],
                    'address' => $data['address'] ?? null,
                    'city' => $data['city'] ?? null,
                    'district' => $data['district'] ?? null,
                ] : null,
                'consent_data' => (bool) ($data['consent_data'] ?? false),
                'consent_whatsapp' => (bool) ($data['consent_whatsapp'] ?? false),
                'override_reason' => $data['override_reason'] ?? null,
            ], $request->user());
        } catch (UnitNotLoanable $e) {
            throw ValidationException::withMessages(['unit_code' => $e->getMessage()]);
        }

        return redirect()->route('sales.loans.show', $loan)->with('success', 'Peminjaman tersimpan. Status unit: Dipinjam.');
    }

    public function show(Loan $loan, LoanService $loans): Response
    {
        Gate::authorize('view', $loan);

        $loan->load(['unit.type', 'customer', 'sales']);
        $date = fn ($value) => $value->locale('id')->translatedFormat('j F Y');

        return Inertia::render('Sales/Loans/Show', [
            'loan' => [
                ...self::row($loan),
                'unit_type' => $loan->unit->type->name,
                'event_name' => $loan->event_name,
                'customer' => $loan->customer?->only(['name', 'whatsapp_number', 'address', 'city', 'district']),
                'starts_on' => $date($loan->starts_on),
                'due_on_long' => $date($loan->due_on),
                'sales' => $loan->sales->name,
                'override_reason' => $loan->override_reason,
            ],
            'whatsappUrl' => WhatsApp::chatUrl($loan->customer?->whatsapp_number, $loans->summaryText($loan)),
        ]);
    }

    /** Scan result: unit status and, if it is on loan, who has it and since when. */
    public function lookup(Request $request, UnitStatusService $status): JsonResponse
    {
        $code = strtoupper(trim((string) $request->query('code')));
        $unit = Unit::query()->with(['type', 'components', 'activeLoan.customer'])->where('code', $code)->first();

        if (! $unit) {
            return response()->json(['message' => 'Kode unit tidak ditemukan.'], 404);
        }

        Gate::authorize('view', $unit);
        $loan = $unit->activeLoan;

        return response()->json([
            'code' => $unit->code,
            'type' => $unit->type->name,
            'status' => $unit->status->value,
            'status_label' => $unit->statusLabel(),
            'location' => $unit->location,
            'components' => $unit->components->where('is_present', true)->pluck('name')->values(),
            'loanable' => $status->isLoanable($unit),
            'warning' => $status->isLoanable($unit) ? null : UnitNotLoanable::describe($unit, $loan),
            'active_loan' => $loan ? [
                'id' => $loan->id,
                'borrower' => $loan->borrowerName(),
                'type_label' => $loan->type->label(),
                'starts_on' => $loan->starts_on->locale('id')->translatedFormat('j M'),
                'due_on' => $loan->due_on->locale('id')->translatedFormat('j M'),
            ] : null,
        ]);
    }

    /** @return array<string, mixed> */
    public static function row(Loan $loan): array
    {
        return [
            'id' => $loan->id,
            'borrower' => $loan->borrowerName(),
            'unit_code' => $loan->unit->code,
            'type_label' => $loan->type->label(),
            'due_on' => $loan->due_on->locale('id')->translatedFormat('j M'),
            'status_label' => $loan->isOverdue() ? 'Lewat jatuh tempo' : $loan->status->label(),
            'is_active' => $loan->isActive(),
        ];
    }
}
