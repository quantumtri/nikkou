<x-filament-widgets::widget>
    <x-filament::section heading="Corong leads">
        @foreach ($stages as $label => $value)
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px">
                <span class="nk-muted" style="flex: 0 0 130px">{{ $label }}</span>
                <div style="flex: 1; background: #efefef; border-radius: 999px; height: 14px; overflow: hidden">
                    <div style="width: {{ round($value / $max * 100) }}%; height: 100%; background: #c0261b; border-radius: 999px"></div>
                </div>
                <span style="flex: 0 0 32px; text-align: right; font-variant-numeric: tabular-nums">{{ $value }}</span>
            </div>
        @endforeach

        <h3 class="nk-sec" style="margin-top: 24px">Alasan batal</h3>
        @if ($cancelled === 0)
            <p class="nk-muted">Belum ada lead yang batal pada periode ini.</p>
        @else
            @foreach ($reasons as $label => $value)
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px">
                    <span class="nk-muted" style="flex: 0 0 130px">{{ $label }}</span>
                    <div style="flex: 1; background: #efefef; border-radius: 999px; height: 14px; overflow: hidden">
                        <div style="width: {{ round($value / $cancelled * 100) }}%; height: 100%; background: #f49c94; border-radius: 999px"></div>
                    </div>
                    <span style="flex: 0 0 32px; text-align: right; font-variant-numeric: tabular-nums">{{ $value }}</span>
                </div>
            @endforeach
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
