<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use App\Services\ReceiptPrinter;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ReceiptPrintController extends Controller
{
    public function show(GoodsReceipt $receipt, ReceiptPrinter $printer): Response
    {
        Gate::authorize('view', $receipt);

        return $this->pdf($printer->render($receipt), "lembar-periksa-{$receipt->number}.pdf");
    }

    /** Blank form for use when the web app cannot be used in the warehouse. */
    public function blank(ReceiptPrinter $printer): Response
    {
        Gate::authorize('viewAny', GoodsReceipt::class);

        return $this->pdf($printer->render(null), 'formulir-penerimaan-kosong.pdf');
    }

    private function pdf(string $content, string $name): Response
    {
        return response($content, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => "inline; filename=\"{$name}\""]);
    }
}
