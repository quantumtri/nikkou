<x-filament-panels::page>
    <div style="display: flex; gap: 24px; flex-wrap: wrap; align-items: flex-start">
        <form wire:submit="save" style="flex: 1; min-width: 360px; max-width: 760px; display: flex; flex-direction: column; gap: 16px">
            {{ $this->form }}

            <div style="display: flex; gap: 12px; flex-wrap: wrap">
                <x-filament::button type="submit" color="primary">Simpan sebagai draf</x-filament::button>
                <x-filament::button type="button" color="primary" outlined wire:click="save(true)" wire:confirm="Simpan dan langsung setujui sebagai versi aktif?">Simpan dan setujui</x-filament::button>
                <x-filament::button type="button" color="gray" outlined wire:click="renumber">Nomori ulang langkah</x-filament::button>
            </div>
        </form>

        <section class="nk-panel" style="flex: 1; min-width: 360px; overflow: hidden" aria-labelledby="sop-preview">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px">
                <h2 id="sop-preview" class="nk-sec" style="margin: 0; margin-right: auto">Pratinjau diagram</h2>
                <x-filament::button size="sm" color="gray" outlined wire:click="$refresh">Perbarui</x-filament::button>
            </div>
            <div style="overflow-x: auto">{!! $this->previewSvg() !!}</div>
        </section>
    </div>
</x-filament-panels::page>
