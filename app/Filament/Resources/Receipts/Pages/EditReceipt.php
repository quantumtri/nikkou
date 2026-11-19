<?php

namespace App\Filament\Resources\Receipts\Pages;

use App\Exceptions\ReceiptRuleViolation;
use App\Filament\Resources\Receipts\ReceiptResource;
use App\Filament\Resources\Receipts\Schemas\ReceiptWizard;
use App\Filament\Resources\Receipts\Support\ReceiptState;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptFile;
use App\Models\GoodsReceiptItem;
use App\Models\UnitType;
use App\Services\ReceiptService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Arr;
use Livewire\Attributes\On;

/** The receiving wizard. Each step saves as you move on, so a draft can be resumed from any device. */
class EditReceipt extends EditRecord
{
    protected static string $resource = ReceiptResource::class;

    /** Receipts waiting for approval or finished are read-only: send people to the view page instead of a 403. */
    protected function authorizeAccess(): void
    {
        $record = $this->getRecord();

        if (auth()->user()->can('update', $record)) {
            return;
        }

        abort_unless(auth()->user()->can('view', $record), 403);

        $this->redirect(ReceiptResource::getUrl('view', ['record' => $record]));
    }

    public function getTitle(): string
    {
        return 'Penerimaan '.$this->getRecord()->number;
    }

    public function getSubheading(): ?string
    {
        $receipt = $this->getRecord();

        return $receipt->statusLabel().' | Dibuat oleh '.$receipt->creator->name.'. Draf tersimpan setiap pindah langkah.';
    }

    public function form(Schema $schema): Schema
    {
        return ReceiptWizard::configure($schema);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return ReceiptState::load($this->getRecord());
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Cetak lembar periksa')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => route('receipts.print', $this->getRecord()), shouldOpenInNewTab: true),
        ];
    }

    #[On('refreshReceipt')]
    public function refreshReceipt(): void
    {
        $this->record->refresh();
    }

    // ------------------------------------------------------------------ saving each step

    /** @param  array<string, mixed>  $state */
    public function persistStep(int $step, array $state): void
    {
        $service = app(ReceiptService::class);
        $receipt = $this->getRecord()->refresh();

        try {
            match ($step) {
                1 => $this->persistHeader($service, $receipt, $state),
                2 => $this->persistPackaging($service, $receipt, $state),
                3 => $this->persistDocuments($service, $receipt, $state),
                4 => $this->persistItems($service, $receipt, $state),
                5 => $this->persistTests($service, $receipt, $state),
                7 => $this->persistShelves($service, $receipt, $state),
            };
        } catch (ReceiptRuleViolation $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            throw new Halt;
        }

        $receipt->forceFill(['current_step' => max($receipt->current_step, $step)])->save();
        $this->reloadState();
    }

    private function persistHeader(ReceiptService $service, GoodsReceipt $receipt, array $state): void
    {
        $service->updateHeader($receipt, Arr::only($state, ['received_on', 'po_number', 'supplier', 'forwarder', 'tracking_number', 'packages_expected', 'from_paper']));

        if (array_key_exists('paper_photos', $state) || ! ($state['from_paper'] ?? false)) {
            $service->syncFiles($receipt, GoodsReceiptFile::PAPER_PHOTO, ($state['from_paper'] ?? false) ? Arr::wrap($state['paper_photos'] ?? []) : []);
        }
    }

    private function persistPackaging(ReceiptService $service, GoodsReceipt $receipt, array $state): void
    {
        $service->updateHeader($receipt, ['packages_received' => $state['packages_received'] ?? null]);

        $photos = Arr::wrap($state['package_photos'] ?? []);
        $notes = Arr::wrap($state['delivery_note_files'] ?? []);

        $service->syncFiles($receipt, GoodsReceiptFile::PACKAGE_PHOTO, $photos);
        $service->syncFiles($receipt, GoodsReceiptFile::DELIVERY_NOTE, $notes);
        $service->setCheck($receipt, 'packaging_photographed', $photos !== []);
        $service->setCheck($receipt, 'damage_noted', (bool) ($state['damage_noted'] ?? false));
        $service->setCheck($receipt, 'delivery_note_signed', $notes !== []);
    }

    private function persistDocuments(ReceiptService $service, GoodsReceipt $receipt, array $state): void
    {
        $service->setCheck($receipt, 'documents_match', (string) ($state['documents_match'] ?? '1') === '1');
        $service->setCheck($receipt, 'model_quantity_ok', (string) ($state['model_quantity_ok'] ?? '1') === '1');

        $any = false;
        foreach (['doc_packing_list' => 'packing_list', 'doc_invoice' => 'invoice', 'doc_bl_awb' => 'bl_awb', 'doc_pib' => 'pib'] as $field => $type) {
            $paths = Arr::wrap($state[$field] ?? []);
            $any = $any || $paths !== [];
            $service->syncFiles($receipt, GoodsReceiptFile::IMPORT_DOCUMENT, $paths, null, $type);
        }
        $service->setCheck($receipt, 'import_documents', $any);

        $service->syncHold($receipt, auth()->user());
        $service->assertDocumentsHandled($receipt);

        if ($service->stepFourLocked($receipt)) {
            throw new ReceiptRuleViolation('Pemeriksaan unit terkunci sampai semua temuan dokumen ditutup oleh pemilik atau disetujui tetap terbuka.');
        }
    }

    private function persistItems(ReceiptService $service, GoodsReceipt $receipt, array $state): void
    {
        $kept = [];

        foreach (array_values($state['items'] ?? []) as $row) {
            $data = [
                'unit_type_id' => $row['unit_type_id'] ?? null,
                'serial_number' => $row['serial_number'] ?? null,
                'components' => $this->components($row['unit_type_id'] ?? null, Arr::wrap($row['components'] ?? [])),
                'physical_ok' => (bool) ($row['physical_ok'] ?? true),
                'notes' => $row['notes'] ?? null,
            ];

            $item = ! empty($row['item_id']) ? $receipt->items()->find($row['item_id']) : null;
            $item = $item
                ? ($item->unit_id ? $item : $service->updateItem($item, $data))
                : $service->addItem($receipt, $data);

            if (! $item->unit_id) {
                $service->syncFiles($receipt, GoodsReceiptFile::UNIT_PHOTO, Arr::wrap($row['unit_photos'] ?? []), $item->id);
            }

            $kept[] = $item->id;
        }

        foreach ($receipt->items()->whereNotIn('id', $kept)->whereNull('unit_id')->get() as $removed) {
            $service->removeItem($removed);
        }
    }

    /** @return array<int, array{name: string, present: bool}> every listed component, ticked or not */
    private function components(?int $unitTypeId, array $checked): array
    {
        $options = UnitType::find($unitTypeId)?->default_components ?? [];

        return collect($options)->map(fn ($name) => ['name' => $name, 'present' => in_array($name, $checked, true)])->values()->all();
    }

    private function persistTests(ReceiptService $service, GoodsReceipt $receipt, array $state): void
    {
        foreach (array_values($state['tests'] ?? []) as $row) {
            $item = $receipt->items()->find($row['item_id'] ?? 0);

            if (! $item || $item->unit_id) {
                continue;
            }

            $flags = [
                'powers_on' => (bool) ($row['powers_on'] ?? false),
                'outputs' => (bool) ($row['outputs'] ?? false),
                'no_leak' => (bool) ($row['no_leak'] ?? false),
                'no_abnormal' => (bool) ($row['no_abnormal'] ?? false),
                'fail_reason' => $row['fail_reason'] ?? null,
            ];

            ! empty($row['retest'])
                ? $service->retest($item, $flags, auth()->user())
                : $service->recordTest($item, $flags, auth()->user());
        }
    }

    private function persistShelves(ReceiptService $service, GoodsReceipt $receipt, array $state): void
    {
        foreach (array_values($state['shelves'] ?? []) as $row) {
            if ($item = $receipt->items()->find($row['item_id'] ?? 0)) {
                $service->setShelf($item, $row['shelf'] ?? null, auth()->user());
            }
        }
    }

    private function reloadState(): void
    {
        $fresh = ReceiptState::load($this->getRecord()->refresh());

        foreach (['items', 'tests', 'labels', 'shelves'] as $key) {
            $this->data[$key] = $fresh[$key];
        }
    }

    // ------------------------------------------------------------------ step 6 actions

    public function saveUnitsAction(): void
    {
        try {
            $units = app(ReceiptService::class)->saveUnits($this->getRecord(), auth()->user());
        } catch (ReceiptRuleViolation $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->reloadState();
        Notification::make()->title(count($units) ? count($units).' unit disimpan.' : 'Tidak ada unit baru untuk disimpan.')->success()->send();
    }

    /** @return list<int> */
    public function unitIds(): array
    {
        return $this->getRecord()->items()->whereNotNull('unit_id')->pluck('unit_id')->all();
    }

    public function confirmLabelScan(int $itemId, string $scanned): void
    {
        if (trim($scanned) === '') {
            return;
        }

        $item = GoodsReceiptItem::query()->where('goods_receipt_id', $this->getRecord()->id)->find($itemId);

        try {
            if (! $item) {
                throw new ReceiptRuleViolation('Unit tidak ditemukan di penerimaan ini.');
            }

            app(ReceiptService::class)->confirmLabel($item, $scanned, auth()->user());
            Notification::make()->title("Label {$item->unit_code} terbaca.")->success()->send();
        } catch (ReceiptRuleViolation $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }

        $this->reloadState();
    }

    // ------------------------------------------------------------------ final step

    /** "Kirim untuk persetujuan": the wizard's submit button. */
    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        $service = app(ReceiptService::class);

        try {
            $this->persistStep(7, $this->form->getState());
            $receipt = $service->submitForApproval($this->getRecord(), auth()->user());
        } catch (ReceiptRuleViolation $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        } catch (Halt) {
            return;
        }

        Notification::make()->title('Penerimaan diajukan untuk persetujuan.')->success()->send();
        $this->redirect(ReceiptResource::getUrl('view', ['record' => $receipt]));
    }
}
