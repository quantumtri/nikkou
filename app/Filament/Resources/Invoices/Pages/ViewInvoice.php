<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string
    {
        return 'Invoice '.$this->getRecord()->code;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->statusAction('markPaid', 'Tandai lunas', InvoiceStatus::Paid, 'success', 'heroicon-o-check-badge',
                'Tandai invoice ini lunas?', 'Lead terkait otomatis berstatus Membeli. Status tidak bisa diubah lagi.'),
            $this->statusAction('cancel', 'Batalkan invoice', InvoiceStatus::Cancelled, 'danger', 'heroicon-o-x-circle',
                'Batalkan invoice ini?', 'Invoice batal tidak bisa dibuka lagi. Buat invoice baru bila perlu.'),
        ];
    }

    private function statusAction(string $name, string $label, InvoiceStatus $to, string $color, string $icon, string $heading, string $description): Action
    {
        return Action::make($name)->label($label)->color($color)->icon($icon)
            ->visible(fn (Invoice $record) => $record->status === InvoiceStatus::Unpaid && auth()->user()->can('changeStatus', $record))
            ->requiresConfirmation()->modalHeading($heading)->modalDescription($description)
            ->action(function (Invoice $record) use ($to) {
                try {
                    app(InvoiceService::class)->changeStatus($record, $to, auth()->user());
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();

                    return;
                }
                $this->record->refresh();
                Notification::make()->success()->title('Status invoice diperbarui')->send();
            });
    }
}
