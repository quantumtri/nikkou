<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['goods_receipt_id', 'code', 'passed', 'notes'])]
class GoodsReceiptCheck extends Model
{
    protected function casts(): array
    {
        return ['passed' => 'boolean'];
    }
}
