<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 24px">
        @forelse ($items as $item)
            <section class="nk-panel" aria-labelledby="sop-{{ $item['sop']->id }}">
                <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 12px">
                    <h2 id="sop-{{ $item['sop']->id }}" class="nk-sec" style="margin: 0">
                        <a class="nk-link" href="{{ $item['url'] }}">{{ $item['sop']->code }} {{ $item['sop']->title }}</a>
                    </h2>
                    <span class="nk-chip {{ $item['sop']->status === 'active' ? 'nk-chip-ok' : 'nk-chip-bad' }}">{{ $item['sop']->statusLabel() }}</span>
                    @if ($item['version'])
                        <span class="nk-muted" style="font-size: 13px">Versi {{ $item['version']->number }}</span>
                    @endif
                </div>
                @if ($item['svg'])
                    <div style="overflow-x: auto">{!! $item['svg'] !!}</div>
                @else
                    <p class="nk-muted">Alur belum diisi.</p>
                @endif
            </section>
        @empty
            <section class="nk-panel"><p class="nk-muted">Belum ada SOP.</p></section>
        @endforelse
    </div>
</x-filament-panels::page>
