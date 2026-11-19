<?php

namespace App\Filament\Resources\Receipts\Support;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptFile;
use App\Models\GoodsReceiptItem;

/** Builds the wizard's form state from the database, so a saved draft can be resumed at any time. */
class ReceiptState
{
    /** @return array<string, mixed> */
    public static function load(GoodsReceipt $receipt): array
    {
        $receipt->load(['checks', 'items.files', 'files', 'items.unit']);
        $check = fn (string $code) => $receipt->check($code);
        $paths = fn (string $kind, ?string $docType = null) => $receipt->files
            ->where('kind', $kind)->whereNull('goods_receipt_item_id')->whereNull('goods_receipt_finding_id')
            ->when($docType, fn ($c) => $c->where('document_type', $docType))
            ->pluck('file_path')->values()->all();

        return [
            'number' => $receipt->number,
            'received_on' => $receipt->received_on?->toDateString(),
            'po_number' => $receipt->po_number,
            'supplier' => $receipt->supplier,
            'forwarder' => $receipt->forwarder,
            'tracking_number' => $receipt->tracking_number,
            'packages_expected' => $receipt->packages_expected,
            'from_paper' => $receipt->from_paper,
            'paper_photos' => $paths(GoodsReceiptFile::PAPER_PHOTO),

            'packages_received' => $receipt->packages_received,
            'package_photos' => $paths(GoodsReceiptFile::PACKAGE_PHOTO),
            'damage_noted' => (bool) $check('damage_noted')?->passed,
            'delivery_note_files' => $paths(GoodsReceiptFile::DELIVERY_NOTE),

            'documents_match' => $check('documents_match') ? ($check('documents_match')->passed ? '1' : '0') : null,
            'model_quantity_ok' => $check('model_quantity_ok') ? ($check('model_quantity_ok')->passed ? '1' : '0') : null,
            'doc_packing_list' => $paths(GoodsReceiptFile::IMPORT_DOCUMENT, 'packing_list'),
            'doc_invoice' => $paths(GoodsReceiptFile::IMPORT_DOCUMENT, 'invoice'),
            'doc_bl_awb' => $paths(GoodsReceiptFile::IMPORT_DOCUMENT, 'bl_awb'),
            'doc_pib' => $paths(GoodsReceiptFile::IMPORT_DOCUMENT, 'pib'),

            'items' => self::items($receipt),
            'tests' => self::tests($receipt),
            'labels' => self::labels($receipt),
            'shelves' => self::shelves($receipt),
        ];
    }

    /** @return array<string, array<string, mixed>> keyed by "item-<id>" so rows keep their identity */
    public static function items(GoodsReceipt $receipt): array
    {
        return $receipt->items->mapWithKeys(fn (GoodsReceiptItem $item) => ["item-{$item->id}" => [
            'item_id' => $item->id,
            'unit_code' => $item->unit_code,
            'unit_type_id' => $item->unit_type_id,
            'serial_number' => $item->serial_number,
            'components' => collect($item->components ?? [])->where('present', true)->pluck('name')->values()->all(),
            'physical_ok' => $item->physical_ok === null ? true : $item->physical_ok,
            'unit_photos' => $item->files->where('kind', GoodsReceiptFile::UNIT_PHOTO)->pluck('file_path')->values()->all(),
            'notes' => $item->notes,
        ]])->all();
    }

    /** @return array<string, array<string, mixed>> */
    public static function tests(GoodsReceipt $receipt): array
    {
        return $receipt->items->mapWithKeys(fn (GoodsReceiptItem $item) => ["item-{$item->id}" => [
            'item_id' => $item->id,
            'label' => $item->unit_code.($item->serial_number ? " | SN {$item->serial_number}" : ''),
            'powers_on' => (bool) $item->powers_on,
            'outputs' => (bool) $item->outputs,
            'no_leak' => (bool) $item->no_leak,
            'no_abnormal' => (bool) $item->no_abnormal,
            'fail_reason' => $item->fail_reason,
            'retest' => false,
            'result' => $item->result,
            'is_retest' => (bool) $item->is_retest,
            'saved' => (bool) $item->unit_id,
        ]])->all();
    }

    /** @return array<string, array<string, mixed>> */
    public static function labels(GoodsReceipt $receipt): array
    {
        return $receipt->items->filter->unit_id->mapWithKeys(fn (GoodsReceiptItem $item) => ["item-{$item->id}" => [
            'item_id' => $item->id,
            'unit_code' => $item->unit_code,
            'status' => $item->unit?->status->label(),
            'scan' => null,
            'read' => $item->label_read_at?->locale('id')->translatedFormat('j M H:i'),
        ]])->all();
    }

    /** @return array<string, array<string, mixed>> */
    public static function shelves(GoodsReceipt $receipt): array
    {
        return $receipt->items->filter->unit_id->mapWithKeys(fn (GoodsReceiptItem $item) => ["item-{$item->id}" => [
            'item_id' => $item->id,
            'unit_code' => $item->unit_code,
            'shelf' => $item->shelf,
        ]])->all();
    }
}
