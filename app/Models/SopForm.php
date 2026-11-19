<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sop_version_id', 'sop_step_id', 'name', 'type', 'route_or_file'])]
class SopForm extends Model
{
    public const WEB = 'web';

    public const PAPER_PDF = 'paper_pdf';

    /** @var array<string, string> */
    public const TYPE_LABELS = [self::WEB => 'Formulir web', self::PAPER_PDF => 'Formulir kertas (PDF)'];

    public $timestamps = false;
}
