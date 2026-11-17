<?php

namespace App\Services;

use App\Enums\LoanType;
use App\Models\FollowupSchedule;
use App\Models\FollowupTemplate;
use App\Models\Loan;
use Illuminate\Support\Carbon;

/**
 * Builds the follow-up schedule for a home trial (spec section 7). Only steps whose wording is
 * switched on and approved get scheduled, and only for customers who opted in to WhatsApp.
 * Daytime sends: messages are due at 10:00 Jakarta time on their day.
 */
class FollowupPlanner
{
    public const TIMEZONE = 'Asia/Jakarta';

    public const SEND_HOUR = 10;

    /** Hours after the loan is saved, for the "first session" check-in. */
    public const FIRST_SESSION_HOURS = 4;

    /** Hours after the return is recorded. */
    public const SURVEY_HOURS = 2;

    public const OFFER_HOURS = 26;

    public function eligible(Loan $loan): bool
    {
        $loan->loadMissing('customer');

        return $loan->type === LoanType::HomeTrial
            && $loan->customer !== null
            && $loan->customer->canReceiveFollowups();
    }

    /** Called when a loan is saved: first session, daily check-ins, and the day-before pick-up reminder. */
    public function planForLoan(Loan $loan): int
    {
        if (! $this->eligible($loan)) {
            return 0;
        }

        $created = 0;
        $templates = $this->sendableTemplates();
        $now = now();

        if ($template = $templates['first_session'] ?? null) {
            $created += $this->add($loan, $template, 1, $now->copy()->addHours(self::FIRST_SESSION_HOURS));
        }

        // One check-in per day between the day after the loan starts and the day before it is due.
        if ($template = $templates['daily'] ?? null) {
            $sequence = 0;
            $day = $loan->starts_on->copy()->addDay();

            while ($day->lt($loan->due_on)) {
                $sequence++;
                $created += $this->add($loan, $template, $sequence, $this->at($day), skipIfPast: true);
                $day->addDay();
            }
        }

        if ($template = $templates['reminder_h1'] ?? null) {
            $created += $this->add($loan, $template, 1, $this->at($loan->due_on->copy()->subDay()), skipIfPast: true);
        }

        return $created;
    }

    /** Called when the unit is returned: stop the daily messages, then queue the survey and the purchase offer. */
    public function planAfterReturn(Loan $loan): int
    {
        $this->cancelPending($loan);

        if (! $this->eligible($loan)) {
            return 0;
        }

        $templates = $this->sendableTemplates();
        $at = $loan->returned_at ?? now();
        $created = 0;

        if ($template = $templates['survey'] ?? null) {
            $created += $this->add($loan, $template, 1, $at->copy()->addHours(self::SURVEY_HOURS));
        }

        if ($template = $templates['offer'] ?? null) {
            $created += $this->add($loan, $template, 1, $at->copy()->addHours(self::OFFER_HOURS));
        }

        return $created;
    }

    /** Cancel everything not yet sent for a loan (returned, or the customer asked to stop). */
    public function cancelPending(Loan $loan, ?array $onlySteps = ['first_session', 'daily', 'reminder_h1']): int
    {
        return FollowupSchedule::query()
            ->where('loan_id', $loan->id)
            ->whereIn('status', [FollowupSchedule::PENDING, FollowupSchedule::SENDING])
            ->when($onlySteps, fn ($q) => $q->whereIn('step_key', $onlySteps))
            ->update(['status' => FollowupSchedule::CANCELLED, 'claimed_at' => null]);
    }

    public function cancelAllForCustomer(int $customerId): int
    {
        return FollowupSchedule::query()
            ->where('customer_id', $customerId)
            ->whereIn('status', [FollowupSchedule::PENDING, FollowupSchedule::SENDING])
            ->update(['status' => FollowupSchedule::CANCELLED, 'claimed_at' => null]);
    }

    /** @return array<string, FollowupTemplate> */
    private function sendableTemplates(): array
    {
        return FollowupTemplate::query()
            ->where('group', FollowupTemplate::GROUP_BORROWER)
            ->get()
            ->filter(fn (FollowupTemplate $template) => $template->isSendable())
            ->keyBy('step_key')
            ->all();
    }

    private function at(Carbon $day): Carbon
    {
        // The app runs in Asia/Jakarta (config/app.php), so this is 10:00 local time.
        return Carbon::parse($day->toDateString())->setTime(self::SEND_HOUR, 0);
    }

    private function add(Loan $loan, FollowupTemplate $template, int $sequence, Carbon $sendAt, bool $skipIfPast = false): int
    {
        if ($skipIfPast && $sendAt->isPast()) {
            return 0;
        }

        return FollowupSchedule::query()->firstOrCreate(
            ['loan_id' => $loan->id, 'step_key' => $template->step_key, 'sequence' => $sequence],
            ['customer_id' => $loan->customer_id, 'template_id' => $template->id, 'send_at' => $sendAt, 'status' => FollowupSchedule::PENDING],
        )->wasRecentlyCreated ? 1 : 0;
    }
}
