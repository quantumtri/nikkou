<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

#[Fillable(['goods_receipt_id', 'goods_receipt_item_id', 'goods_receipt_finding_id', 'kind', 'document_type', 'file_path', 'original_name'])]
class GoodsReceiptFile extends Model
{
    public const PACKAGE_PHOTO = 'package_photo';

    public const UNIT_PHOTO = 'unit_photo';

    public const DELIVERY_NOTE = 'delivery_note';

    public const IMPORT_DOCUMENT = 'import_document';

    public const PAPER_PHOTO = 'paper_photo';

    public const FINDING_PHOTO = 'finding_photo';

    public function signedUrl(): string
    {
        return URL::temporarySignedRoute('files.receipt-file', now()->addMinutes(15), ['file' => $this->id]);
    }
}
