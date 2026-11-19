<?php

namespace App\Services;

use App\Mail\ReceiptSummaryMail;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptFinding;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Tells the owners about a receipt: in the app, by email, and as a queued WhatsApp message
 * (WhatsApp for admins is only queued until it is connected).
 */
class ReceiptNotifier
{
    public function __construct(private readonly ReceiptService $receipts) {}

    /** @return Collection<int, User> */
    private function owners()
    {
        return User::query()->where('role', 'admin')->where('is_active', true)->get();
    }

    public function summaryText(GoodsReceipt $receipt): string
    {
        $s = $this->receipts->summary($receipt);

        return "Penerimaan {$receipt->number} menunggu persetujuan.\n"
            .'Pemasok: '.($receipt->supplier ?: '-').($receipt->po_number ? " (PO {$receipt->po_number})" : '')."\n"
            ."Diterima: {$s['received']} unit | Lolos: {$s['passed']} | Gagal: {$s['failed']} | Ditahan: {$s['held']}\n"
            ."Temuan: {$s['findings_total']} (terbuka: {$s['findings_open']})\n"
            .'Penerima: '.$receipt->creator->name;
    }

    /** Called when a receipt is submitted for approval. Failures here never block the receipt. */
    public function submitted(GoodsReceipt $receipt): void
    {
        $receipt->loadMissing('creator');
        $text = $this->summaryText($receipt);
        $owners = $this->owners();
        $url = url('/admin/receipts/'.$receipt->id);

        $notification = Notification::make()
            ->title("Penerimaan {$receipt->number} menunggu persetujuan")
            ->body($text)
            ->warning()
            ->actions([Action::make('open')->label('Buka penerimaan')->url($url)->markAsRead()])
            ->toDatabase();

        foreach ($owners as $owner) {
            $owner->notifyNow($notification);

            try {
                Mail::to($owner)->send(new ReceiptSummaryMail($receipt, $text, $url));
            } catch (\Throwable $e) {
                report($e);
            }

            if ($owner->whatsapp_number) {
                DB::table('admin_message_queue')->insert([
                    'channel' => 'whatsapp', 'user_id' => $owner->id, 'body' => $text."\n".$url,
                    'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        $receipt->forceFill(['summary_sent_at' => now()])->save();
    }

    /** Findings past their due date go to the admins as a high-level alert, once a day per finding. */
    public function notifyOverdueFindings(): int
    {
        $findings = GoodsReceiptFinding::query()
            ->with(['receipt', 'responsible'])
            ->where('status', '!=', GoodsReceiptFinding::CLOSED)
            ->whereNull('waived_at')
            ->whereDate('due_on', '<', today())
            ->where(fn ($q) => $q->whereNull('overdue_notified_at')->orWhere('overdue_notified_at', '<', now()->subDay()))
            ->get();

        $owners = $this->owners();

        foreach ($findings as $finding) {
            $days = (int) $finding->due_on->diffInDays(today());
            $notification = Notification::make()
                ->title('Tinggi: temuan penerimaan lewat batas waktu')
                ->body(GoodsReceiptFinding::typeLabel($finding->type).' di '.$finding->receipt->number.', lewat '.$days.' hari.'.($finding->responsible ? ' Penanggung jawab: '.$finding->responsible->name.'.' : ''))
                ->danger()
                ->actions([Action::make('open')->label('Buka penerimaan')->url(url('/admin/receipts/'.$finding->goods_receipt_id))->markAsRead()])
                ->toDatabase();

            foreach ($owners as $owner) {
                $owner->notifyNow($notification);
            }

            $finding->forceFill(['overdue_notified_at' => now()])->save();
        }

        return $findings->count();
    }
}
