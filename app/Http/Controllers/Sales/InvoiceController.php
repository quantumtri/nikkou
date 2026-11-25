<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\Rupiah;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Sales app: read-only list of the invoices on my leads. Admin confirms payment in the panel. */
class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $invoices = Invoice::query()->visibleTo($request->user())
            ->with(['lead.customer', 'items.unit'])->latest('id')->limit(100)->get();

        return Inertia::render('Sales/Invoices/Index', [
            'invoices' => $invoices->map(fn (Invoice $i) => [
                'id' => $i->id,
                'code' => $i->code,
                'lead_id' => $i->lead_id,
                'lead_code' => $i->lead->code,
                'customer' => $i->lead->customer->name,
                'issued_on' => $i->issued_on->locale('id')->translatedFormat('j M Y'),
                'paid_on' => $i->paid_on?->locale('id')->translatedFormat('j M Y'),
                'total' => Rupiah::format($i->total),
                'status' => $i->status->value,
                'status_label' => $i->status->label(),
                'items' => $i->items->map(fn ($item) => [
                    'id' => $item->id,
                    'label' => $item->quantity.' x '.$item->name,
                    'unit_code' => $item->unit?->code,
                ])->values(),
            ])->values(),
        ]);
    }
}
