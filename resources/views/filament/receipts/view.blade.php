@php
    use App\Models\GoodsReceipt;
    use App\Models\GoodsReceiptFinding;
    $date = fn ($value) => $value?->locale('id')->translatedFormat('j M Y H:i') ?? '-';
@endphp

<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 24px">
        @if ($problems !== [])
            <div class="nk-panel">
                <h2 class="nk-sec">Belum bisa diselesaikan</h2>
                <ul style="padding-left: 20px; list-style: disc">
                    @foreach ($problems as $problem)
                        <li class="nk-muted">{{ $problem }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div style="display: flex; gap: 16px; flex-wrap: wrap">
            <div class="nk-stat"><span>Diterima</span><strong>{{ $summary['received'] }} unit</strong></div>
            <div class="nk-stat"><span>Lolos</span><strong>{{ $summary['passed'] }}</strong></div>
            <div class="nk-stat"><span>Gagal</span><strong>{{ $summary['failed'] }}</strong></div>
            <div class="nk-stat"><span>Ditahan</span><strong>{{ $summary['held'] }}</strong></div>
            <div class="nk-stat"><span>Temuan terbuka</span><strong>{{ $summary['findings_open'] }} dari {{ $summary['findings_total'] }}</strong></div>
        </div>

        <section class="nk-panel" aria-labelledby="r-data">
            <h2 id="r-data" class="nk-sec">Data kiriman</h2>
            <dl style="display: flex; gap: 40px; flex-wrap: wrap">
                <div style="flex: 1; min-width: 260px">
                    <div class="nk-kv"><dt>Nomor lembar</dt><dd>{{ $receipt->number }}</dd></div>
                    <div class="nk-kv"><dt>Nomor PO</dt><dd>{{ $receipt->po_number ?: '-' }}</dd></div>
                    <div class="nk-kv"><dt>Pemasok</dt><dd>{{ $receipt->supplier ?: '-' }}</dd></div>
                    <div class="nk-kv"><dt>Forwarder</dt><dd>{{ $receipt->forwarder ?: '-' }}</dd></div>
                    <div class="nk-kv"><dt>Resi atau BL</dt><dd>{{ $receipt->tracking_number ?: '-' }}</dd></div>
                </div>
                <div style="flex: 1; min-width: 260px">
                    <div class="nk-kv"><dt>Koli</dt><dd>{{ $receipt->packages_received ?? '-' }} diterima dari {{ $receipt->packages_expected ?? '-' }} tercatat</dd></div>
                    <div class="nk-kv"><dt>Penerima</dt><dd>{{ $receipt->creator->name }}</dd></div>
                    <div class="nk-kv"><dt>Diajukan</dt><dd>{{ $date($receipt->submitted_at) }}</dd></div>
                    <div class="nk-kv"><dt>Disetujui</dt><dd>{{ $receipt->approver ? $receipt->approver->name.' | '.$date($receipt->approved_at) : '-' }}</dd></div>
                    @if ($receipt->from_paper)
                        <div class="nk-kv"><dt>Sumber</dt><dd>Disalin dari kertas</dd></div>
                    @endif
                </div>
            </dl>
        </section>

        <section class="nk-panel" aria-labelledby="r-units">
            <h2 id="r-units" class="nk-sec">Unit</h2>
            <div style="overflow-x: auto">
                <table class="nk-table">
                    <thead>
                        <tr><th scope="col">Kode</th><th scope="col">Nomor seri</th><th scope="col">Kelengkapan</th><th scope="col">Hasil uji</th><th scope="col">Status unit</th><th scope="col">Rak</th><th scope="col">Label</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($receipt->items as $item)
                            <tr>
                                <td>
                                    @if ($item->unit_id)
                                        <a class="nk-link" href="{{ \App\Filament\Resources\Units\UnitResource::getUrl('view', ['record' => $item->unit_id]) }}">{{ $item->unit_code }}</a>
                                    @else
                                        {{ $item->unit_code }}
                                    @endif
                                </td>
                                <td>{{ $item->serial_number ?: '-' }}</td>
                                <td>{{ $item->is_complete === null ? '-' : ($item->is_complete ? 'Lengkap' : 'Kurang') }}</td>
                                <td>
                                    @if ($item->result)
                                        <span class="nk-chip {{ $item->passed() ? 'nk-chip-ok' : 'nk-chip-bad' }}">{{ $item->passed() ? 'Lolos' : 'Gagal' }}</span>
                                        @if (! $item->passed() && $item->fail_reason)<div class="nk-muted" style="font-size: 12px">{{ $item->fail_reason }}</div>@endif
                                    @else - @endif
                                </td>
                                <td>{{ $item->unit?->status->label() ?? 'Belum disimpan' }}</td>
                                <td>{{ $item->shelf ?: '-' }}</td>
                                <td>{{ $item->label_read_at ? 'Terbaca' : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="nk-muted">Belum ada unit.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
