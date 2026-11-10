@php
    use App\Enums\UnitStatus;
    use App\Support\ActivityLabels;

    /** @var \App\Models\Unit $unit */
    $chip = match (true) {
        $unit->status === UnitStatus::Available => 'nk-chip-ok',
        $unit->status === UnitStatus::OnLoan => 'nk-chip-bad',
        default => 'nk-chip-neutral',
    };
    $activeLoan = $unit->status === UnitStatus::OnLoan ? $unit->loans->firstWhere(fn ($loan) => $loan->isActive()) : null;
    $date = fn ($value) => $value?->locale('id')->translatedFormat('j M Y') ?? '-';
@endphp

<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 28px">
        <div>
            <span class="nk-chip {{ $chip }}">{{ $unit->statusLabel() }}</span>
        </div>

        <section aria-labelledby="unit-data">
            <h2 id="unit-data" class="nk-sec">Data unit</h2>
            <div style="display: flex; gap: 40px; flex-wrap: wrap">
                <dl style="flex: 1; min-width: 280px">
                    <div class="nk-kv"><dt>Kode produk</dt><dd>{{ $unit->code }}</dd></div>
                    <div class="nk-kv"><dt>Tipe</dt><dd>{{ $unit->type->name }}</dd></div>
                    <div class="nk-kv"><dt>Tanggal masuk</dt><dd>{{ $date($unit->received_on) }}</dd></div>
                    <div class="nk-kv"><dt>Penginput</dt><dd>{{ $unit->createdBy->name }}</dd></div>
                    <div class="nk-kv"><dt>Forwarder</dt><dd>{{ $unit->forwarder ?: '-' }}</dd></div>
                </dl>
                <dl style="flex: 1; min-width: 280px">
                    <div class="nk-kv">
                        <dt>Dokumen impor</dt>
                        <dd>
                            @if (! $canSeeDocuments)
                                {{ $unit->documents->count() }} berkas
                            @elseif ($unit->documents->isEmpty())
                                Belum ada berkas
                            @else
                                @foreach ($unit->documents as $document)
                                    <a class="nk-link" href="{{ $document->signedUrl() }}" target="_blank" rel="noopener">{{ $document->type->label() }}</a>@if (! $loop->last), @endif
                                @endforeach
                            @endif
                        </dd>
                    </div>
                    <div class="nk-kv">
                        <dt>Kelengkapan</dt>
                        <dd>
                            @forelse ($unit->components as $component)
                                {{ $component->name }}@if (! $component->is_present) (tidak ada)@endif@if (! $loop->last), @endif
                            @empty
                                -
                            @endforelse
                        </dd>
                    </div>
                    <div class="nk-kv"><dt>Posisi sekarang</dt><dd>{{ $unit->currentPosition() }}</dd></div>
                    <div class="nk-kv"><dt>Jatuh tempo</dt><dd>{{ $activeLoan ? $date($activeLoan->due_on) : '-' }}</dd></div>
                    <div class="nk-kv"><dt>Catatan kondisi</dt><dd>{{ $unit->initial_notes ?: '-' }}</dd></div>
                </dl>
            </div>

            @if ($unit->photos->isNotEmpty())
                <div style="display: flex; gap: 12px; margin-top: 16px">
                    @foreach ($unit->photos as $photo)
                        <a href="{{ $photo->signedUrl() }}" target="_blank" rel="noopener" style="flex: 1; min-width: 0">
                            <img class="nk-photo" style="width: 100%" src="{{ $photo->signedUrl() }}" alt="Foto kondisi awal {{ $loop->iteration }}" loading="lazy">
                        </a>
                    @endforeach
                </div>
            @else
                <p class="nk-muted" style="margin-top: 16px">Belum ada foto kondisi awal.</p>
            @endif
        </section>

        <div style="display: flex; gap: 16px; flex-wrap: wrap">
            <div class="nk-stat"><span>Total dipinjamkan</span><strong>{{ $unit->loans->count() }} kali</strong></div>
            <div class="nk-stat"><span>Home trial</span><strong>{{ $unit->loans->where('type', \App\Enums\LoanType::HomeTrial)->count() }} kali</strong></div>
            <div class="nk-stat"><span>Event</span><strong>{{ $unit->loans->where('type', \App\Enums\LoanType::Event)->count() }} kali</strong></div>
            <div class="nk-stat"><span>Label</span><strong>{{ $unit->label_printed_at ? 'Dicetak '.$date($unit->label_printed_at) : 'Belum dicetak' }}</strong></div>
        </div>

        <div style="display: flex; gap: 40px; flex-wrap: wrap">
            <section style="flex: 1; min-width: 300px" aria-labelledby="unit-history">
                <h2 id="unit-history" class="nk-sec">Riwayat unit</h2>
                @forelse ($unit->activityLogs as $log)
                    <div class="nk-tl @if (! str_starts_with($log->action, 'loan.') && $log->action !== 'unit.status_changed') nk-tl-muted @endif">
                        <small>{{ $log->created_at->locale('id')->translatedFormat('j M Y H:i') }} | {{ ActivityLabels::action($log) }}@if ($log->user) | {{ str($log->user->name)->before(' ') }}@endif</small>
                        <div>{{ ActivityLabels::description($log) }}</div>
                    </div>
                @empty
                    <p class="nk-muted">Belum ada riwayat.</p>
                @endforelse
            </section>

            <section style="flex: 1; min-width: 300px" aria-labelledby="unit-loans">
                <h2 id="unit-loans" class="nk-sec">Trial dan lead</h2>
                @if ($unit->loans->isEmpty())
                    <p class="nk-muted">Unit ini belum pernah dipinjamkan.</p>
                @else
                    <div style="overflow-x: auto">
                        <table class="nk-table">
                            <thead>
                                <tr><th scope="col">Pelanggan</th><th scope="col">Jenis</th><th scope="col">Masa pinjam</th><th scope="col">Sales</th><th scope="col">Status lead</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($unit->loans as $loan)
                                    <tr>
                                        <td>{{ $loan->borrowerName() }}</td>
                                        <td>{{ $loan->type->label() }}</td>
                                        <td>{{ $date($loan->starts_on) }} sampai {{ $date($loan->due_on) }}</td>
                                        <td>{{ str($loan->sales->name)->before(' ') }}</td>
                                        <td>{{ $loan->lead?->status->label() ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-filament-panels::page>
