<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Services\LabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LabelController extends Controller
{
    public function pdf(Request $request, LabelService $labels): Response
    {
        Gate::authorize('printLabel', Unit::class);

        $data = $request->validate([
            'units' => ['required', 'string', 'regex:/^\d+(,\d+)*$/'],
            'size' => ['required', Rule::in(array_keys(LabelService::SIZES))],
            'mode' => ['required', Rule::in(array_keys(LabelService::CODE_MODES))],
        ]);

        $units = Unit::query()->with('type')->whereKey(explode(',', $data['units']))->orderBy('code')->get();
        abort_if($units->isEmpty(), 404);

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($labels->pdf($units, $data['size'], $data['mode']), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="label-nikkou-'.now()->format('Ymd-His').'.pdf"',
        ]);
    }

    /** QR image download for a single unit (unit detail page). */
    public function qr(Unit $unit, LabelService $labels): Response
    {
        Gate::authorize('printLabel', Unit::class);

        return response($labels->qrPng($unit->code, 1000), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="qr-'.$unit->code.'.png"',
        ]);
    }
}
