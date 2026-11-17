<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\ExperienceMessage;
use App\Models\FollowupTemplate;
use App\Models\Loan;
use App\Models\User;
use App\Support\WhatsApp;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

/**
 * Decides what to do with a message a customer sent on WhatsApp (spec section 7).
 * "handled" means the web app owns the message: n8n sends the returned reply (if any) and the bot stays out.
 */
class InboundMessageService
{
    /** How long after a return a customer's replies still belong to the follow-up. */
    public const WINDOW_DAYS = 30;

    private const BOT_WORDS = ['0', 'menu', 'hi', 'hai', 'halo', 'hello', 'mulai', 'start', 'p', 'assalamualaikum'];

    public function __construct(private readonly SafetyScanner $scanner, private readonly FollowupPlanner $planner) {}

    /**
     * @param  array{message_id?: ?string, phone?: ?string, text?: ?string, has_media?: bool}  $data
     * @return array{handled: bool, reply?: ?string}
     */
    public function handle(array $data): array
    {
        $key = $data['message_id'] ?? null;

        // The same WhatsApp message delivered twice gives the same answer and stores nothing new.
        if ($key && ($existing = ExperienceMessage::query()->where('message_key', $key)->first())) {
            return $this->answer($existing->handled, $existing->reply_text);
        }

        $phone = WhatsApp::normalize($data['phone'] ?? null);
        $customer = $phone ? Customer::query()->where('whatsapp_number', $phone)->first() : null;

        if (! $customer) {
            return ['handled' => false];
        }

        $text = trim((string) ($data['text'] ?? ''));
        $hasMedia = (bool) ($data['has_media'] ?? false);

        // Menu words and digits belong to the bot.
        if ($text !== '' && $this->isBotCommand($text)) {
            return ['handled' => false];
        }

        $loan = $customer->loans()->with(['lead', 'sales', 'unit'])->latest('id')->first();
        $serious = $text !== '' && $this->scanner->scan($text) !== [];

        if (! $serious && ! $this->inFollowup($customer, $loan)) {
            return ['handled' => false];
        }

        return DB::transaction(function () use ($customer, $loan, $text, $key, $serious) {
            $stop = ! $serious && $this->isStopRequest($text);
            $reply = null;

            if ($stop) {
                $this->optOut($customer);
                $reply = FollowupTemplate::systemText(FollowupTemplate::STOP_CONFIRM);
                $reply = $reply?->isSendable() ? $reply->body : null;
            } elseif ($serious) {
                $this->flagSerious($customer, $loan, $text);
                $template = FollowupTemplate::systemText(FollowupTemplate::SAFETY_REPLY);
                // The standard wording needs health-professional review before it is sent to customers.
                $reply = $template?->isSendable() ? $template->body : null;
            }

            ExperienceMessage::create([
                'customer_id' => $customer->id,
                'lead_id' => $loan?->lead_id,
                'loan_id' => $loan?->id,
                'direction' => 'in',
                'body' => $text !== '' ? $text : '[lampiran tanpa teks]',
                'message_key' => $key,
                'is_serious' => $serious,
                'handled' => true,
                'reply_text' => $reply,
            ]);

            return $this->answer(true, $reply);
        });
    }

    /** @return array{handled: bool, reply?: ?string} */
    private function answer(bool $handled, ?string $reply): array
    {
        return $reply !== null && $reply !== '' ? ['handled' => $handled, 'reply' => $reply] : ['handled' => $handled];
    }

    private function isBotCommand(string $text): bool
    {
        $key = preg_replace('/[\s.。、,，]/u', '', mb_strtolower($text));

        return in_array($key, self::BOT_WORDS, true) || preg_match('/^[0-9]$/', $key) === 1;
    }

    private function isStopRequest(string $text): bool
    {
        return preg_match('/^\s*(berhenti|stop|unsubscribe|jangan\s+(kirim|hubungi))\b/iu', $text) === 1;
    }

    /** The customer has an active loan, returned one recently, or still has messages queued. */
    private function inFollowup(Customer $customer, ?Loan $loan): bool
    {
        if (! $loan || ! $customer->canReceiveFollowups()) {
            return false;
        }

        return $loan->isActive() || ($loan->returned_at !== null && $loan->returned_at->gt(now()->subDays(self::WINDOW_DAYS)));
    }

    private function optOut(Customer $customer): void
    {
        $customer->forceFill(['whatsapp_opted_out_at' => now()])->save();
        $this->planner->cancelAllForCustomer($customer->id);
        ActivityLog::record($customer, 'customer.whatsapp_opted_out', null, notes: 'Pelanggan meminta berhenti dari pesan otomatis.');
    }

    /** Marks the lead, stops automatic messages, and tells admins and the sales right away. */
    private function flagSerious(Customer $customer, ?Loan $loan, string $text): void
    {
        $this->planner->cancelAllForCustomer($customer->id);

        if ($loan?->lead) {
            $loan->lead->forceFill(['serious_complaint_at' => now()])->save();
            ActivityLog::record($loan->lead, 'lead.serious_complaint', null, notes: mb_substr($text, 0, 200));
        }

        $recipients = User::query()->where('role', 'admin')->where('is_active', true)->get();

        if ($loan?->sales && $loan->sales->is_active) {
            $recipients = $recipients->push($loan->sales)->unique('id');
        }

        $notification = Notification::make()
            ->title("Keluhan serius dari {$customer->name}")
            ->body('"'.mb_substr($text, 0, 200).'" Hubungi pelanggan segera. Pesan otomatis ke pelanggan ini sudah dihentikan.')
            ->danger()
            ->persistent()
            ->actions([
                Action::make('open')->label('Lihat pesan')->url(url('/admin/customer-messages'))->markAsRead(),
            ])
            ->toDatabase();

        // Sent immediately: this alert must not wait for a queue worker.
        foreach ($recipients as $user) {
            $user->notifyNow($notification);
        }
    }
}
