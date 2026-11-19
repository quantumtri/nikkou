<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use Barryvdh\DomPDF\Facade\Pdf;

/** The A4 check sheet (Lembar Periksa Penerimaan): filled with a receipt's data, or blank for paper use. */
class ReceiptPrinter
{
    public const MIN_UNIT_ROWS = 7;

    public const SETTING_COMPANY = 'receipt.company_name';

    public const SETTING_FORM_CODE = 'receipt.form_code';

    public const DEFAULT_COMPANY = '[Nama Perusahaan]';

    public const DEFAULT_FORM_CODE = '[kode form, revisi]';

    public function __construct(private readonly ReceiptService $receipts) {}

    public function render(?GoodsReceipt $receipt): string
    {
        $data = $this->data($receipt);

        return Pdf::setOption(['fontDir' => storage_path('fonts'), 'fontCache' => storage_path('fonts'), 'isRemoteEnabled' => false, 'defaultFont' => 'Outfit'])
            ->loadView('receipts.sheet', $data)
            ->setPaper('a4', 'portrait')
            ->output();
    }

    /** @return array<string, mixed> */
    public function data(?GoodsReceipt $receipt): array
    {
        $receipt?->load(['items.tester', 'items.unit', 'findings.item', 'checks', 'creator', 'approver']);

        $check = fn (string $code): ?bool => $receipt?->check($code)?->passed;
        $items = $receipt ? $receipt->items->values() : collect();
        $rows = max(self::MIN_UNIT_ROWS, $items->count());

        return [
            'blank' => $receipt === null,
            'receipt' => $receipt,
            'company' => AppSetting::read(self::SETTING_COMPANY, self::DEFAULT_COMPANY),
            'formCode' => AppSetting::read(self::SETTING_FORM_CODE, self::DEFAULT_FORM_CODE),
            'logo' => 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('images/logo-wordmark.png'))),
            'fontDir' => storage_path('fonts'),
            'checks' => [
                'Kemasan luar difoto sebelum dibuka' => $check('packaging_photographed'),
                'Kerusakan dicatat di surat jalan' => $check('damage_noted'),
                'PO, invoice, packing list, dan fisik cocok' => $check('documents_match'),
                'Model dan jumlah sesuai' => $check('model_quantity_ok'),
                'Dokumen impor lengkap (packing list, invoice, BL/AWB, PIB)' => $check('import_documents'),
                'Surat jalan bertanda tangan' => $check('delivery_note_signed'),
            ],
            'items' => $items,
            'rows' => $rows,
            'summary' => $receipt ? $this->receipts->summary($receipt) : null,
            'generatedAt' => now()->locale('id')->translatedFormat('j F Y H:i'),
        ];
    }

    public static function mark(?bool $value): bool
    {
        return $value === true;
    }

    public static function itemFlag(?GoodsReceiptItem $item, string $flag): ?bool
    {
        return $item?->{$flag};
    }
}
