<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ExperienceMessage;
use App\Models\FollowupSchedule;
use Illuminate\Support\Facades\DB;

/** Hands due follow-ups to n8n and records what happened (spec section 7). */
class FollowupDispatcher
{
    public const MAX_ATTEMPTS = 3;

    /** A message claimed but never reported back is offered again after this many minutes. */
    public const CLAIM_MINUTES = 30;

    /** Messages that are this many hours overdue are dropped rather than sent late. */
    public const STALE_HOURS = 12;

    private const LOAN_STEPS = ['first_session', 'daily', 'reminder_h1'];

    public function __construct(private readonly FollowupRenderer $renderer) {}

    /** @return list<array{id: int, chat_id: string, text: string, consent_whatsapp: bool}> */
    public function due(int $limit = 20): array
    {
        return DB::transaction(function () use ($limit) {
            $rows = FollowupSchedule::query()
                ->with(['template', 'customer', 'loan.unit', 'loan.customer', 'loan.sales'])
                ->where(fn ($q) => $q
                    ->where(fn ($p) => $p->where('status', FollowupSchedule::PENDING)->where('send_at', '<=', now()))
                    ->orWhere(fn ($s) => $s->where('status', FollowupSchedule::SENDING)->where('claimed_at', '<', now()->subMinutes(self::CLAIM_MINUTES))))
                ->orderBy('send_at')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            $items = [];
            $customersInBatch = [];

            foreach ($rows as $schedule) {
                // One message per customer per poll: a customer never gets a burst of messages at once.
                // The rest stay pending and go out on later polls (or are dropped as stale).
                if (isset($customersInBatch[$schedule->customer_id]) && $schedule->status === FollowupSchedule::PENDING) {
                    continue;
                }

                $reason = $this->blockedReason($schedule);

                if ($reason) {
                    $status = $reason === 'loan_closed' || $reason === 'opted_out' ? FollowupSchedule::CANCELLED : FollowupSchedule::SKIPPED;
                    $schedule->update(['status' => $status, 'error' => $reason, 'claimed_at' => null]);

                    continue;
                }

                if ($schedule->attempts >= self::MAX_ATTEMPTS) {
                    $schedule->update(['status' => FollowupSchedule::FAILED, 'error' => 'Terlalu banyak percobaan.', 'claimed_at' => null]);

                    continue;
                }

                $text = $this->renderer->render($schedule->template, $schedule->loan);
                $schedule->update(['status' => FollowupSchedule::SENDING, 'claimed_at' => now(), 'attempts' => $schedule->attempts + 1, 'text' => $text]);

                $customersInBatch[$schedule->customer_id] = true;
                $items[] = [
                    'id' => $schedule->id,
                    'chat_id' => $schedule->customer->chatId(),
                    'text' => $text,
                    'consent_whatsapp' => true,
                ];
            }

            return $items;
        });
    }

    /** @param  array{status: string, waha_message_id?: ?string, sent_at?: ?string, error?: ?string}  $data */
    public function report(FollowupSchedule $schedule, array $data): FollowupSchedule
    {
        return DB::transaction(function () use ($schedule, $data) {
            $schedule = FollowupSchedule::query()->lockForUpdate()->findOrFail($schedule->id);

            // A result for something already finished changes nothing.
            if (in_array($schedule->status, [FollowupSchedule::SENT, FollowupSchedule::CANCELLED, FollowupSchedule::SKIPPED], true)) {
                return $schedule;
            }

            if ($data['status'] === 'sent') {
                $schedule->update([
                    'status' => FollowupSchedule::SENT,
                    'sent_at' => $data['sent_at'] ?? now(),
                    'waha_message_id' => $data['waha_message_id'] ?? null,
                    'error' => null,
                    'claimed_at' => null,
                ]);

                ExperienceMessage::query()->firstOrCreate(
                    ['message_key' => 'out:schedule:'.$schedule->id],
                    [
                        'customer_id' => $schedule->customer_id,
                        'lead_id' => $schedule->loan->lead_id,
                        'loan_id' => $schedule->loan_id,
                        'direction' => 'out',
                        'body' => (string) $schedule->text,
                        'handled' => true,
                    ],
                );

                return $schedule;
            }

            $final = $schedule->attempts >= self::MAX_ATTEMPTS;
            $schedule->update([
                'status' => $final ? FollowupSchedule::FAILED : FollowupSchedule::PENDING,
                'send_at' => $final ? $schedule->send_at : now()->addMinutes(15),
                'error' => mb_substr((string) ($data['error'] ?? 'Gagal terkirim.'), 0, 500),
                'claimed_at' => null,
            ]);

            if ($final) {
                ActivityLog::record($schedule->customer, 'followup.failed', null, notes: "Langkah {$schedule->step_key} gagal terkirim.");
            }

            return $schedule;
        });
    }

    private function blockedReason(FollowupSchedule $schedule): ?string
    {
        if (! $schedule->template->isSendable()) {
            return 'template_off';
        }

        if (! $schedule->customer->canReceiveFollowups()) {
            return 'opted_out';
        }

        if (in_array($schedule->step_key, self::LOAN_STEPS, true) && ! $schedule->loan->isActive()) {
            return 'loan_closed';
        }

        if ($schedule->send_at->lt(now()->subHours(self::STALE_HOURS))) {
            return 'stale';
        }

        return null;
    }
}
