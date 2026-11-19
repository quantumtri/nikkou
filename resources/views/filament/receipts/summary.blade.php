@php
    $receipt = $getRecord();
    $summary = app(\App\Services\ReceiptService::class)->summary($receipt);
    $problems = app(\App\Services\ReceiptService::class)->readinessProblems($receipt);
@endphp

<div class="nk-panel" style="margin-top: 8px">
    <h3 class="nk-sec">Ringkasan penerimaan {{ $receipt->number }}</h3>
    <div style="display: flex; gap: 16px; flex-wrap: wrap">
        <div class="nk-stat"><span>Diterima</span><strong>{{ $summary['received'] }}</strong></div>
        <div class="nk-stat"><span>Lolos</span><strong>{{ $summary['passed'] }}</strong></div>
        <div class="nk-stat"><span>Gagal</span><strong>{{ $summary['failed'] }}</strong></div>
        <div class="nk-stat"><span>Temuan terbuka</span><strong>{{ $summary['findings_open'] }} dari {{ $summary['findings_total'] }}</strong></div>
    </div>

    @if ($problems !== [])
        <p style="margin-top: 16px; font-weight: 600">Yang masih perlu dilengkapi sebelum diajukan:</p>
        <ul style="margin-top: 4px; padding-left: 20px; list-style: disc">
            @foreach ($problems as $problem)
                <li class="nk-muted">{{ $problem }}</li>
            @endforeach
        </ul>
    @else
        <p class="nk-muted" style="margin-top: 16px">Semua lengkap. Tekan "Kirim untuk persetujuan" untuk melaporkan ke pemilik.</p>
    @endif
</div>
