@php
    use App\Models\GoodsReceiptFinding;
    $date = fn ($v) => $v?->locale('id')->translatedFormat('j F Y') ?? '';
    $box = fn (?bool $on) => '<span class="box'.($on ? ' on' : '').'"></span>';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Lembar Periksa Penerimaan {{ $receipt?->number }}</title>
    <style>
        @font-face { font-family: 'Outfit'; font-weight: 400; src: url('{{ $fontDir }}/Outfit-400.ttf') format('truetype'); }
        @font-face { font-family: 'Outfit'; font-weight: 600; src: url('{{ $fontDir }}/Outfit-600.ttf') format('truetype'); }
        @page { margin: 14mm 12mm 16mm 12mm; }
        body { font-family: 'Outfit', sans-serif; font-size: 8.5pt; color: #1a1a1a; line-height: 1.3; }
        h1 { font-size: 14pt; font-weight: 600; margin: 0; color: #c0261b; }
        h2 { font-size: 9pt; font-weight: 600; margin: 10px 0 4px; color: #c0261b; text-transform: uppercase; letter-spacing: 0.5pt; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 0.6pt solid #727171; padding: 3px 4px; vertical-align: middle; }
        th { background: #efefef; font-weight: 600; text-align: left; font-size: 7.5pt; }
        td.c, th.c { text-align: center; }
        .plain td, .plain th { border: 0; padding: 1px 0; }
        .label { color: #4a4543; font-size: 7.5pt; }
        .value { border-bottom: 0.6pt solid #727171; min-height: 12px; padding: 1px 2px; }
        .box { display: inline-block; width: 9px; height: 9px; border: 0.8pt solid #1a1a1a; vertical-align: middle; }
        .box.on { background: #1a1a1a; }
        .sign td { height: 44px; vertical-align: bottom; text-align: center; border: 0; border-top: 0.6pt solid #1a1a1a; padding-top: 2px; }
        .foot { position: fixed; bottom: -9mm; left: 0; right: 0; font-size: 7pt; color: #727171; }
        .foot td { border: 0; padding: 0; }
        .tag { font-size: 7pt; color: #7a1d14; }
    </style>
</head>
<body>
    <div class="foot">
        <table><tr>
            <td>{{ $company }}</td>
            <td style="text-align: center">{{ $formCode }}</td>
            <td style="text-align: right">{{ $blank ? 'Formulir kosong' : 'Dicetak '.$generatedAt }}</td>
        </tr></table>
    </div>

    <table class="plain" style="margin-bottom: 6px">
        <tr>
            <td style="width: 40%"><img src="{{ $logo }}" alt="NIKKOU" style="height: 20px"></td>
            <td style="text-align: right">
                <h1>Lembar Periksa Penerimaan Barang</h1>
                <div class="label">Nomor lembar: <strong>{{ $receipt?->number ?: 'PB-______-____' }}</strong>
                    @if ($receipt?->from_paper) | <span class="tag">Disalin dari kertas</span>@endif
                </div>
            </td>
        </tr>
    </table>

    <table style="margin-bottom: 4px">
        <tr>
            <th colspan="4">Kedatangan</th>
            <th colspan="2">Jumlah</th>
        </tr>
        <tr>
            <td class="label" style="width: 13%">Tanggal terima</td><td style="width: 22%">{{ $date($receipt?->received_on) }}</td>
            <td class="label" style="width: 13%">Nomor PO</td><td style="width: 22%">{{ $receipt?->po_number }}</td>
            <td class="label" style="width: 15%">Koli tercatat</td><td>{{ $receipt?->packages_expected }}</td>
        </tr>
        <tr>
            <td class="label">Pemasok</td><td>{{ $receipt?->supplier }}</td>
            <td class="label">Kurir / forwarder</td><td>{{ $receipt?->forwarder }}</td>
            <td class="label">Koli diterima</td><td>{{ $receipt?->packages_received }}</td>
        </tr>
        <tr>
            <td class="label">Resi / BL</td><td colspan="3">{{ $receipt?->tracking_number }}</td>
            <td class="label">Penerima</td><td>{{ $receipt?->creator?->name }}</td>
        </tr>
    </table>

    <h2>Kemasan dan dokumen</h2>
    <table>
        <tr>
            @foreach (collect($checks)->chunk(3) as $group)
                <td style="width: 50%; border: 0; vertical-align: top; padding: 0 6px 0 0">
                    <table class="plain">
                        @foreach ($group as $label => $value)
                            <tr>
                                <td style="width: 14px">{!! $box($value === true) !!}</td>
                                <td>{{ $label }}@if ($value === false) <span class="tag">(tidak)</span>@endif</td>
                            </tr>
                        @endforeach
                    </table>
                </td>
            @endforeach
        </tr>
    </table>

    <h2>Pemeriksaan tiap unit dan uji fungsi</h2>
    <table>
        <thead>
            <tr>
                <th class="c" style="width: 3%">No</th>
                <th style="width: 11%">Kode unit</th>
                <th style="width: 15%">Nomor seri</th>
                <th class="c">Lengkap</th>
                <th class="c">Fisik baik</th>
                <th class="c">Menyala</th>
                <th class="c">Output keluar</th>
                <th class="c">Tidak bocor</th>
                <th class="c">Tanpa bunyi / panas</th>
                <th class="c" style="width: 9%">Hasil</th>
                <th style="width: 9%">Rak</th>
            </tr>
        </thead>
        <tbody>
            @for ($i = 0; $i < $rows; $i++)
                @php $item = $items[$i] ?? null; @endphp
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td>{{ $item?->unit_code }}</td>
                    <td>{{ $item?->serial_number }}</td>
                    <td class="c">{!! $box($item?->is_complete === true) !!}</td>
                    <td class="c">{!! $box($item?->physical_ok === true) !!}</td>
                    <td class="c">{!! $box($item?->powers_on === true) !!}</td>
                    <td class="c">{!! $box($item?->outputs === true) !!}</td>
                    <td class="c">{!! $box($item?->no_leak === true) !!}</td>
                    <td class="c">{!! $box($item?->no_abnormal === true) !!}</td>
                    <td class="c">@if ($item?->result){{ $item->passed() ? 'Lolos' : 'Gagal' }}@else{!! '<span class="label">Lolos / Gagal</span>' !!}@endif</td>
                    <td>{{ $item?->shelf }}</td>
                </tr>
                @if ($item && ! $item->passed() && $item->fail_reason)
                    <tr><td></td><td colspan="10" class="label">Alasan gagal: {{ $item->fail_reason }}</td></tr>
                @endif
            @endfor
        </tbody>
    </table>
    <div class="label" style="margin-top: 2px">Hasil Lolos hanya bila semua kolom uji, fisik baik, dan lengkap tercentang. Selain itu Gagal dan alasannya dicatat.</div>

    <h2>Temuan</h2>
    <table>
        <thead>
            <tr><th style="width: 18%">Jenis</th><th>Deskripsi</th><th style="width: 14%">Tindakan</th><th style="width: 11%">Batas waktu</th><th style="width: 13%">Status</th></tr>
        </thead>
        <tbody>
            @php $findings = $receipt?->findings ?? collect(); $findingRows = max(3, $findings->count()); @endphp
            @for ($i = 0; $i < $findingRows; $i++)
                @php $f = $findings[$i] ?? null; @endphp
                <tr>
                    <td>{{ $f ? GoodsReceiptFinding::typeLabel($f->type) : '' }}</td>
                    <td>{{ $f?->description }}{{ $f?->item ? ' ('.$f->item->unit_code.')' : '' }}</td>
                    <td>{{ $f?->action ? (GoodsReceiptFinding::ACTIONS[$f->action] ?? '') : '' }}</td>
                    <td>{{ $f?->due_on?->format('d/m/Y') }}</td>
                    <td>@if ($f){{ $f->waived_at ? 'Tetap terbuka' : GoodsReceiptFinding::STATUS_LABELS[$f->status] }}@endif</td>
                </tr>
            @endfor
        </tbody>
    </table>

    <h2>Penyelesaian</h2>
    <table>
        <tr>
            <td class="label" style="width: 18%">Ringkasan</td>
            <td>
                @if ($summary)
                    Diterima {{ $summary['received'] }} | Lolos {{ $summary['passed'] }} | Gagal {{ $summary['failed'] }} | Ditahan {{ $summary['held'] }} | Temuan {{ $summary['findings_total'] }} (terbuka {{ $summary['findings_open'] }})
                @else
                    Diterima ____ | Lolos ____ | Gagal ____ | Ditahan ____ | Temuan ____
                @endif
            </td>
            <td class="label" style="width: 14%">Status</td>
            <td style="width: 20%">{{ $receipt?->statusLabel() }}</td>
        </tr>
        <tr>
            <td class="label">Disetujui oleh</td><td>{{ $receipt?->approver?->name }}</td>
            <td class="label">Tanggal</td><td>{{ $date($receipt?->approved_at) }}</td>
        </tr>
    </table>

    <table class="sign" style="margin-top: 6px">
        <tr>
            <td style="width: 31%">Admin gudang (penerima)<br><strong>{{ $receipt?->creator?->name }}</strong></td>
            <td style="width: 3.5%; border: 0"></td>
            <td style="width: 31%">Penguji<br><strong>{{ $items->pluck('tester.name')->filter()->unique()->implode(', ') }}</strong></td>
            <td style="width: 3.5%; border: 0"></td>
            <td style="width: 31%">Pemilik / manajer<br><strong>{{ $receipt?->approver?->name }}</strong></td>
        </tr>
    </table>
</body>
</html>
