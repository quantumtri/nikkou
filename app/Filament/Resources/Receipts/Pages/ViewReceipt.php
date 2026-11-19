<?php

namespace App\Filament\Resources\Receipts\Pages;

use App\Exceptions\ReceiptRuleViolation;
use App\Filament\Resources\Receipts\ReceiptResource;
use App\Models\GoodsReceipt;
use App\Services\ReceiptService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

class ViewReceipt extends ViewRecord
{
    protected static string $resource = ReceiptResource::class;

    protected string $view = 'filament.receipts.view';

    public function getTitle(): string
    {
        return 'Penerimaan '.$this->getRecord()->number;
    }

    public function getSubheading(): ?string
    {
        $receipt = $this->getRecord();

        return $receipt->statusLabel().' | Diterima '.$receipt->received_on->locale('id')->translatedFormat('j F Y').' | '.($receipt->supplier ?: 'pemasok belum diisi');
    }

    #[On('refreshReceipt')]
    public function refreshReceipt(): void
    {
        $this->record->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('continue')
                ->label('Lanjutkan pengisian')
                ->icon('heroicon-o-pencil-square')
                ->url(fn (GoodsReceipt $record) => ReceiptResource::getUrl('edit', ['record' => $record]))
                ->visible(fn (GoodsReceipt $record) => auth()->user()->can('update', $record)),
            Action::make('approve')
                ->label('Setujui penerimaan')
                ->icon('heroicon-o-check-badge')
                ->color('primary')
                ->visible(fn (GoodsReceipt $record) => auth()->user()->can('approve', $record))
                ->requiresConfirmation()
                ->modalHeading('Setujui penerimaan ini?')
                ->modalDescription('Setelah disetujui penerimaan berstatus Selesai dan tidak bisa diubah.')
                ->action(function (GoodsReceipt $record) {
                    try {
                        app(ReceiptService::class)->approve($record, auth()->user());
                    } catch (ReceiptRuleViolation $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Penerimaan disetujui.')->success()->send();
                    $this->record->refresh();
                }),
        ];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $receipt = $this->getRecord()->load(['items.unit', 'items.tester', 'findings.item', 'checks', 'creator', 'approver']);
        $service = app(ReceiptService::class);

        return [
            'receipt' => $receipt,
            'summary' => $service->summary($receipt),
            'problems' => $receipt->status === GoodsReceipt::COMPLETED ? [] : $service->readinessProblems($receipt),
        ];
    }
}
