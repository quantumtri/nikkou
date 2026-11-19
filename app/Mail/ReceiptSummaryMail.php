<?php

namespace App\Mail;

use App\Models\GoodsReceipt;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ReceiptSummaryMail extends Mailable
{
    public function __construct(public GoodsReceipt $receipt, public string $summary, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Penerimaan {$this->receipt->number} menunggu persetujuan");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.receipt-summary');
    }
}
