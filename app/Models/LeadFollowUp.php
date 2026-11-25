<?php

namespace App\Models;

use App\Enums\FollowUpChannel;
use App\Enums\OverallResponse;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One follow-up contact with a lead. The number of rows is the lead's follow-up count. */
#[Fillable(['lead_id', 'user_id', 'followed_up_on', 'channel', 'response', 'notes'])]
class LeadFollowUp extends Model
{
    protected $table = 'lead_followups';

    protected function casts(): array
    {
        return [
            'followed_up_on' => 'date',
            'channel' => FollowUpChannel::class,
            'response' => OverallResponse::class,
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
