@php
    [$width, $height] = $this->previewSize();
    $labels = $this->previewLabels();
    $count = $this->selectedCount();
    // Preview scale: 1 mm = 4 px.
    $scale = 4;
@endphp

<x-filament-panels::page>
    <div style="display: flex; gap: 40px; flex-wrap: wrap; align-items: flex-start">
        <form wire:submit="printLabels" style="flex: 1; min-width: 320px">
            {{ $this->form }}

            <div style="display: flex; gap: 12px; margin-top: 24px; flex-wrap: wrap">
                <x-filament::button type="submit" color="primary" :disabled="$count === 0">
                    Cetak {{ $count }} label
                </x-filament::button>
                <x-filament::button type="button" color="primary" outlined wire:click="downloadLabels" :disabled="$count === 0">
                    Unduh PDF
                </x-filament::button>
            </div>
        </form>

        <section style="flex: 1; min-width: 320px" aria-labelledby="label-preview">
            <h2 id="label-preview" class="nk-sec">Pratinjau</h2>

            @if ($labels->isEmpty())
                <p class="nk-muted">Pilih unit untuk melihat pratinjau label.</p>
            @else
                <div style="display: flex; flex-wrap: wrap; gap: 16px">
                    @foreach ($labels as $label)
                        <div
                            style="width: {{ $width * $scale }}px; height: {{ $height * $scale }}px; border: 1px solid #1a1a1a; padding: {{ 2 * $scale }}px; box-sizing: border-box; display: flex; flex-direction: column; gap: 4px; background: #fff"
                        >
                            <div style="display: flex; gap: 8px; align-items: center; flex: 1; min-height: 0">
                                @if ($label['qr'])
                                    <img src="{{ $label['qr'] }}" alt="QR {{ $label['code'] }}" style="height: 100%; width: auto; aspect-ratio: 1">
                                @endif
                                <div style="min-width: 0">
                                    <img src="{{ asset('images/logo-wordmark.png') }}" alt="NIKKOU" style="height: {{ $height * 0.4 }}px; width: auto; display: block">
                                    <div style="font-size: {{ $height * 0.55 }}px; line-height: 1.2">{{ $label['code'] }}</div>
                                    <div style="font-size: {{ $height * 0.36 }}px" class="nk-muted">{{ $label['type'] }}</div>
                                </div>
                            </div>
                            @if ($label['barcode'])
                                <img src="{{ $label['barcode'] }}" alt="Barcode {{ $label['code'] }}" style="width: 100%; height: {{ $label['qr'] ? 26 : 42 }}%; display: block">
                                <div style="font-size: {{ $height * 0.32 }}px; text-align: center; line-height: 1">{{ $label['code'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
                @if ($count > $labels->count())
                    <p class="nk-muted" style="margin-top: 12px; font-size: 12px">Menampilkan {{ $labels->count() }} dari {{ $count }} label. Semua label masuk ke PDF.</p>
                @endif
                <p class="nk-muted" style="margin-top: 12px; font-size: 12px">
                    QR berisi kode unit. Barcode linear memakai Code 128 agar terbaca alat scan USB / Bluetooth. Satu label per halaman PDF, ukuran {{ $width }} x {{ $height }} mm.
                </p>
            @endif
        </section>
    </div>
</x-filament-panels::page>
