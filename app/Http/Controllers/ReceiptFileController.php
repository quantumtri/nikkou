<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Receipt photos and documents are private: signed, short-lived links plus a policy check. */
class ReceiptFileController extends Controller
{
    public function show(GoodsReceiptFile $file): StreamedResponse
    {
        Gate::authorize('view', GoodsReceipt::query()->findOrFail($file->goods_receipt_id));

        return Storage::disk('local')->response($file->file_path, $file->original_name);
    }
}
