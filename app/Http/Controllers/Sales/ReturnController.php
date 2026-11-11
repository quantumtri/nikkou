<?php

namespace App\Http\Controllers\Sales;

use App\Exceptions\LoanNotReturnable;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReturnRequest;
use App\Services\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReturnController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Sales/Returns/Create', [
            'isWarehouse' => $request->user()->isWarehouse(),
            'prefillCode' => strtoupper(substr((string) $request->query('code'), 0, 40)),
        ]);
    }

    public function store(StoreReturnRequest $request, ReturnService $returns): RedirectResponse
    {
        $loan = $request->loan;
        $stored = [];

        try {
            // Photos are private files; they are only served through signed URLs.
            foreach ($request->file('photos') as $photo) {
                $stored[] = $photo->store('units/returns', 'local');
            }

            $result = $returns->process(
                $loan,
                $request->user(),
                collect($request->validated('checks'))->map(fn (array $check) => [
                    'component' => $check['component'],
                    'is_complete' => (bool) $check['is_complete'],
                ])->all(),
                $stored,
                $request->validated('notes'),
                $request->boolean('has_damage'),
                $request->validated('status'),
            );
        } catch (LoanNotReturnable $e) {
            Storage::disk('local')->delete($stored);

            throw ValidationException::withMessages(['unit_code' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($stored);

            throw $e;
        }

        $message = $result->needsService()
            ? "Pengembalian {$result->unit->code} tersimpan. Unit ditandai Perlu servis dan admin sudah diberi tahu."
            : "Pengembalian {$result->unit->code} tersimpan. Unit kembali Tersedia.";

        return $request->user()->isWarehouse()
            ? redirect()->route('sales.returns.create')->with('success', $message)
            : redirect()->route('sales.loans.show', $result->loan)->with('success', $message);
    }
}
