<?php

namespace App\Services;

use App\Enums\FollowUpChannel;
use App\Enums\OverallResponse;
use App\Models\ActivityLog;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Follow-ups: each row counts as one follow-up, and the newest one sets the lead's overall response. */
class FollowUpService
{
    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'followed_up_on' => ['required', 'date', 'before_or_equal:today'],
            'channel' => ['required', Rule::enum(FollowUpChannel::class)],
            'response' => ['required', Rule::enum(OverallResponse::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @param  array<string, mixed>  $input
     * @return array<string, mixed> */
    public function validate(array $input): array
    {
        return Validator::make($input, self::rules(), [
            'followed_up_on.before_or_equal' => 'Tanggal follow-up tidak boleh di masa depan.',
        ])->validate();
    }

    /** @param  array<string, mixed>  $data  Validated by validate(). */
    public function add(Lead $lead, array $data, User $by): LeadFollowUp
    {
        if (! $by->isAdmin() && ! ($by->isSales() && $lead->sales_id === $by->id)) {
            throw ValidationException::withMessages(['followed_up_on' => 'Anda tidak boleh menambah follow-up untuk lead ini.']);
        }

        return DB::transaction(function () use ($lead, $data, $by) {
            $followUp = $lead->followUps()->create([
                'user_id' => $by->id,
                'followed_up_on' => $data['followed_up_on'],
                'channel' => $data['channel'],
                'response' => $data['response'],
                'notes' => $data['notes'] ?? null,
            ]);

            $lead->unsetRelation('followUps');
            $latest = $lead->followUps()->first();
            $lead->update([
                'followup_count' => $lead->followUps()->count(),
                'overall_response' => $latest->response,
            ]);

            ActivityLog::record($lead, 'lead.followup_added', $by, null, $followUp->response->value, "Follow-up ke-{$lead->followup_count} ({$followUp->channel->label()}).");

            return $followUp;
        });
    }
}
