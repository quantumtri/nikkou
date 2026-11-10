@php
    // Proportions are derived from the label height so every size keeps the same layout.
    $pad = 2;
    $inner = $height - 2 * $pad;
    $innerWidth = $width - 2 * $pad;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Label unit</title>
    <style>
        @page { margin: 0; }
        * { margin: 0; padding: 0; }
        body { font-family: Helvetica, Arial, sans-serif; color: #000; }
        .label { width: {{ $innerWidth }}mm; height: {{ $inner }}mm; padding: {{ $pad }}mm; page-break-after: always; overflow: hidden; }
        .label:last-child { page-break-after: auto; }
        table { border-collapse: collapse; width: 100%; }
        td { vertical-align: middle; }
        .code { font-size: {{ round($height * 0.36, 1) }}pt; line-height: 1.1; }
        .type { font-size: {{ round($height * 0.2, 1) }}pt; color: #333; }
        .caption { font-size: {{ round($height * 0.18, 1) }}pt; text-align: center; letter-spacing: 0.5pt; }
    </style>
</head>
<body>
@foreach ($labels as $label)
    @php
        $barcodeHeight = $label['barcode'] ? ($label['qr'] ? $inner * 0.26 : $inner * 0.42) : 0;
        $captionHeight = $label['barcode'] ? $inner * 0.12 : 0;
        $top = $inner - $barcodeHeight - $captionHeight - ($label['barcode'] ? 1 : 0);
        $qrSize = $label['qr'] ? min($top, $innerWidth * 0.42) : 0;
    @endphp
    <div class="label">
        <table style="height: {{ $top }}mm">
            <tr>
                @if ($label['qr'])
                    <td style="width: {{ $qrSize + 2 }}mm">
                        <img src="{{ $label['qr'] }}" alt="" style="width: {{ $qrSize }}mm; height: {{ $qrSize }}mm">
                    </td>
                @endif
                <td>
                    <img src="{{ $logo }}" alt="NIKKOU" style="height: {{ round($height * 0.1, 2) }}mm">
                    <div class="code">{{ $label['code'] }}</div>
                    <div class="type">{{ $label['type'] }}</div>
                </td>
            </tr>
        </table>
        @if ($label['barcode'])
            <img src="{{ $label['barcode'] }}" alt="" style="display: block; margin-top: 1mm; width: {{ $innerWidth }}mm; height: {{ $barcodeHeight }}mm">
            <div class="caption">{{ $label['code'] }}</div>
        @endif
    </div>
@endforeach
</body>
</html>
