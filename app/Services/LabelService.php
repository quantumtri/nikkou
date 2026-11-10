<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Unit;
use App\Models\User;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Picqer\Barcode\BarcodeGeneratorPNG;

/**
 * Unit labels (spec A7). The QR code carries the unit code; the linear
 * barcode is Code 128 so USB/Bluetooth scanners can read it as keyboard input.
 */
class LabelService
{
    /** Label sizes in millimetres: [width, height]. */
    public const SIZES = [
        '50x30' => [50, 30],
        '60x40' => [60, 40],
        '70x50' => [70, 50],
    ];

    public const CODE_MODES = [
        'both' => 'QR dan barcode',
        'qr' => 'QR saja',
        'barcode' => 'Barcode saja',
    ];

    public const DEFAULT_SIZE = '50x30';

    public const DEFAULT_MODE = 'both';

    private const MM_TO_PT = 72 / 25.4;

    /** @return array<string, string> */
    public static function sizeOptions(): array
    {
        return collect(self::SIZES)
            ->map(fn (array $size) => "{$size[0]} x {$size[1]} mm (printer label)")
            ->all();
    }

    public function qrPng(string $code, int $pixels = 600): string
    {
        return (new Writer(new GDLibRenderer($pixels, 1)))->writeString($code);
    }

    /**
     * Code 128 with the quiet zone (10 modules of white on each side) baked
     * into the image, so scanners still read it when the label is cropped tight.
     */
    public function barcodePng(string $code, int $widthFactor = 4, int $height = 120): string
    {
        $generator = new BarcodeGeneratorPNG;
        $bars = imagecreatefromstring($generator->getBarcode($code, $generator::TYPE_CODE_128, $widthFactor, $height));

        $quiet = 10 * $widthFactor;
        $canvas = imagecreatetruecolor(imagesx($bars) + 2 * $quiet, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $bars, $quiet, 0, 0, 0, imagesx($bars), $height);

        ob_start();
        imagepng($canvas);

        return (string) ob_get_clean();
    }

    public function qrDataUri(string $code): string
    {
        return 'data:image/png;base64,'.base64_encode($this->qrPng($code));
    }

    public function barcodeDataUri(string $code): string
    {
        return 'data:image/png;base64,'.base64_encode($this->barcodePng($code));
    }

    /**
     * One label per PDF page, sized exactly to the label stock.
     *
     * @param  Collection<int, Unit>  $units
     */
    public function pdf(Collection $units, string $size = self::DEFAULT_SIZE, string $mode = self::DEFAULT_MODE): string
    {
        if (! isset(self::SIZES[$size]) || ! isset(self::CODE_MODES[$mode])) {
            throw new InvalidArgumentException('Ukuran atau jenis kode label tidak dikenal.');
        }

        if ($units->isEmpty()) {
            throw new InvalidArgumentException('Pilih minimal satu unit.');
        }

        [$width, $height] = self::SIZES[$size];

        return Pdf::loadView('labels.pdf', [
            'labels' => $units->map(fn (Unit $unit) => $this->labelData($unit, $mode)),
            'width' => $width,
            'height' => $height,
            'logo' => 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/logo-wordmark.png'))),
        ])
            ->setPaper([0, 0, $width * self::MM_TO_PT, $height * self::MM_TO_PT])
            ->output();
    }

    /** @return array{code: string, type: string, qr: ?string, barcode: ?string} */
    public function labelData(Unit $unit, string $mode = self::DEFAULT_MODE): array
    {
        return [
            'code' => $unit->code,
            'type' => $unit->type->name,
            'qr' => $mode !== 'barcode' ? $this->qrDataUri($unit->code) : null,
            'barcode' => $mode !== 'qr' ? $this->barcodeDataUri($unit->code) : null,
        ];
    }

    /**
     * @param  Collection<int, Unit>  $units
     */
    public function markPrinted(Collection $units, User $actor): void
    {
        DB::transaction(function () use ($units, $actor) {
            foreach ($units as $unit) {
                $unit->update(['label_printed_at' => now()]);
                ActivityLog::record($unit, 'unit.label_printed', $actor, notes: 'Label dicetak.');
            }
        });
    }
}
