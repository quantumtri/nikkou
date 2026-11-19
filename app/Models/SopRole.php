<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sop_version_id', 'name', 'position'])]
class SopRole extends Model
{
    public $timestamps = false;
}
