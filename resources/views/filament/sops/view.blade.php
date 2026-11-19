@php
    $date = fn ($value) => $value?->locale('id')->translatedFormat('j M Y') ?? '-';
    $dateTime = fn ($value) => $value?->locale('id')->translatedFormat('j M Y H:i') ?? '-';
    $chip = fn ($status) => match ($status) { 'active' => 'nk-chip-ok', 'archived' => 'nk-chip-neutral', default => 'nk-chip-bad' };
    $label = fn ($status) => \App\Models\Sop::STATUS_LABELS[$status] ?? $status;
    $tabs = ['alur' => 'Alur', 'langkah' => 'Langkah', 'versi' => 'Versi dan baca'];
    if (! $isAdmin) { unset($tabs['versi']); }
@endphp

<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 20px">
        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap">
            <span class="nk-chip {{ $chip($sop->status) }}">{{ $sop->statusLabel() }}</span>
            @if ($version)
                <span class="nk-muted" style="font-size: 14px">
                    {{ $version->label() }} ({{ $label($version->status) }})
                    @if ($version->effective_from) | berlaku sejak {{ $date($version->effective_from) }} @endif
                    @if ($sop->review_on) | tinjauan berikutnya {{ $date($sop->review_on) }} @endif
                </span>
            @endif
            @if ($isAdmin && $versions->count() > 1)
                <select wire:model.live="versionId" aria-label="Pilih versi" style="margin-left: auto; border: 1px solid #e6e2e0; border-radius: 10px; padding: 6px 12px; background: #fff; font-size: 14px">
                    @foreach ($versions as $v)
                        <option value="{{ $v->id }}" @selected($version && $v->id === $version->id)>Versi {{ $v->number }} ({{ $label($v->status) }})</option>
                    @endforeach
                </select>
            @endif
        </div>

        @unless ($version)
            <section class="nk-panel"><p class="nk-muted">SOP ini belum punya versi aktif.</p></section>
        @else
            <div role="tablist" style="display: flex; gap: 4px; border-bottom: 1px solid #e6e2e0">
                @foreach ($tabs as $key => $name)
                    <button type="button" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" wire:click="setTab('{{ $key }}')"
                        style="padding: 10px 16px; font-size: 14px; font-weight: 600; border: 0; background: none; cursor: pointer; border-bottom: 2px solid {{ $tab === $key ? '#C0261B' : 'transparent' }}; color: {{ $tab === $key ? '#C0261B' : '#6b6560' }}">{{ $name }}</button>
                @endforeach
            </div>

            @if ($tab === 'alur')
                <div x-data="sopDiagram(@js($stepData), @js($sop->code.'-v'.$version->number))" style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap">
                    <section class="nk-panel" style="flex: 1; min-width: 320px; overflow: hidden">
                        <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; align-items: center">
                            <h2 class="nk-sec" style="margin: 0; margin-right: auto">Diagram alur</h2>
                            <x-filament::button size="sm" color="gray" outlined x-on:click="exportSvg()">Unduh SVG</x-filament::button>
                            <x-filament::button size="sm" color="gray" outlined x-on:click="exportPng()">Unduh PNG</x-filament::button>
                            <x-filament::button size="sm" color="gray" outlined x-on:click="exportPdf()">Cetak / PDF</x-filament::button>
                        </div>
                        <div x-ref="canvas" style="overflow-x: auto" x-on:click="pick($event)" x-on:keydown.enter="pick($event)">
                            {!! $svg !!}
                        </div>
                        <p class="nk-muted" style="font-size: 12px; margin-top: 8px">Klik sebuah kotak untuk melihat uraiannya. Garis putus-putus = tertunda, merah = masalah.</p>
                    </section>

                    <aside class="nk-panel" style="width: 320px; flex: none" aria-live="polite">
                        <template x-if="!step">
                            <p class="nk-muted">Pilih langkah di diagram.</p>
                        </template>
                        <template x-if="step">
                            <div>
                                <div class="nk-muted" style="font-size: 12px" x-text="step.type_label + (step.number ? ' ' + step.number : '')"></div>
                                <h3 style="font-size: 18px; font-weight: 600; margin: 4px 0 12px" x-text="step.title"></h3>
                                <dl>
                                    <div class="nk-kv"><dt>Peran</dt><dd x-text="step.role || '-'"></dd></div>
                                    <div class="nk-kv" x-show="step.deadline"><dt>Batas waktu</dt><dd x-text="step.deadline"></dd></div>
                                </dl>
                                <p style="margin-top: 12px; font-size: 14px; line-height: 1.5" x-show="step.description" x-text="step.description"></p>
                                <template x-if="step.forms.length">
                                    <div style="margin-top: 12px">
                                        <div class="nk-muted" style="font-size: 12px">Formulir terkait</div>
                                        <template x-for="f in step.forms" :key="f.name">
                                            <div style="font-size: 14px" x-text="f.name"></div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="step.related_route">
                                    <div style="margin-top: 16px">
                                        <a class="nk-link" :href="step.related_route" style="font-weight: 600">Buka layar terkait</a>
                                    </div>
                                </template>
                            </div>
                        </template>
                        @if (count($sharedForms))
                            <div style="margin-top: 20px; border-top: 1px solid #e6e2e0; padding-top: 12px">
                                <div class="nk-muted" style="font-size: 12px">Formulir untuk SOP ini</div>
                                @foreach ($sharedForms as $f)
                                    <div style="font-size: 14px">{{ $f['name'] }} <span class="nk-muted">({{ \App\Models\SopForm::TYPE_LABELS[$f['type']] ?? $f['type'] }})</span></div>
                                @endforeach
                            </div>
                        @endif
                    </aside>
                </div>
            @elseif ($tab === 'langkah')
                <section class="nk-panel" style="overflow-x: auto">
                    <table style="width: 100%; border-collapse: collapse; font-size: 14px">
                        <thead>
                            <tr style="text-align: left" class="nk-muted">
                                <th style="padding: 8px">No</th><th style="padding: 8px">Langkah</th><th style="padding: 8px">Peran</th><th style="padding: 8px">Uraian</th><th style="padding: 8px">Batas waktu</th><th style="padding: 8px">Lanjut ke</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($doc['steps'] as $s)
                                <tr style="border-top: 1px solid #e6e2e0; vertical-align: top">
                                    <td style="padding: 8px">{{ $s['number'] ?? '' }}</td>
                                    <td style="padding: 8px; font-weight: 600">{{ $s['title'] }} <span class="nk-muted" style="font-weight: 400">({{ \App\Models\SopStep::TYPE_LABELS[$s['type']] }})</span></td>
                                    <td style="padding: 8px">{{ $s['role'] ?? '-' }}</td>
                                    <td style="padding: 8px">{{ $s['description'] ?? '' }}</td>
                                    <td style="padding: 8px">{{ $s['deadline'] ?? '' }}</td>
                                    <td style="padding: 8px">
                                        @foreach (collect($doc['edges'])->where('from', $s['key']) as $e)
                                            <div>{{ $e['label'] ? $e['label'].': ' : '' }}{{ collect($doc['steps'])->firstWhere('key', $e['to'])['title'] ?? $e['to'] }}</div>
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @elseif ($tab === 'versi' && $isAdmin)
                <section class="nk-panel" style="overflow-x: auto">
                    <h2 class="nk-sec">Riwayat versi</h2>
                    <table style="width: 100%; border-collapse: collapse; font-size: 14px">
                        <thead><tr style="text-align: left" class="nk-muted"><th style="padding: 8px">Versi</th><th style="padding: 8px">Status</th><th style="padding: 8px">Dibuat oleh</th><th style="padding: 8px">Disetujui</th><th style="padding: 8px">Catatan perubahan</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($versions as $v)
                                <tr style="border-top: 1px solid #e6e2e0">
                                    <td style="padding: 8px; font-weight: 600">{{ $v->number }}</td>
                                    <td style="padding: 8px"><span class="nk-chip {{ $chip($v->status) }}">{{ $label($v->status) }}</span></td>
                                    <td style="padding: 8px">{{ $v->creator?->name }}<div class="nk-muted" style="font-size: 12px">{{ $dateTime($v->created_at) }}</div></td>
                                    <td style="padding: 8px">{{ $v->approver ? $v->approver->name.', '.$dateTime($v->approved_at) : '-' }}</td>
                                    <td style="padding: 8px">{{ $v->change_summary ?: '-' }}</td>
                                    <td style="padding: 8px"><button type="button" class="nk-link" wire:click="$set('versionId', {{ $v->id }}); setTab('alur')">Lihat</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>

                @if ($versions->count() > 1)
                    <section class="nk-panel">
                        <h2 class="nk-sec">Bandingkan dua versi</h2>
                        <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 12px">
                            @foreach (['compareFrom' => 'Dari', 'compareTo' => 'Ke'] as $prop => $text)
                                <label style="font-size: 14px">{{ $text }}
                                    <select wire:model.live="{{ $prop }}" style="border: 1px solid #e6e2e0; border-radius: 10px; padding: 6px 12px; background: #fff">
                                        <option value="">Pilih versi</option>
                                        @foreach ($versions as $v)<option value="{{ $v->id }}">Versi {{ $v->number }}</option>@endforeach
                                    </select>
                                </label>
                            @endforeach
                        </div>
                        @if ($comparison)
                            @php $d = $comparison['diff']; @endphp
                            @if (! $d['steps'] && ! $d['edges'] && ! $d['roles'] && ! $d['forms'])
                                <p class="nk-muted">Tidak ada perbedaan isi antara versi {{ $comparison['from']->number }} dan {{ $comparison['to']->number }}.</p>
                            @else
                                <ul style="font-size: 14px; display: flex; flex-direction: column; gap: 6px">
                                    @foreach ($d['steps'] as $c)
                                        <li><strong>{{ ['added' => 'Ditambah', 'removed' => 'Dihapus', 'changed' => 'Diubah'][$c['change']] }}:</strong> {{ $c['title'] }} ({{ $c['key'] }}){{ $c['fields'] ? ' - '.implode(', ', $c['fields']) : '' }}</li>
                                    @endforeach
                                    @foreach ($d['edges'] as $c)<li>Panah {{ $c }}</li>@endforeach
                                    @foreach ($d['roles'] as $c)<li>Peran {{ $c }}</li>@endforeach
                                    @foreach ($d['forms'] as $c)<li>Formulir {{ $c }}</li>@endforeach
                                </ul>
                            @endif
                        @endif
                    </section>
                @endif

                <section class="nk-panel" style="overflow-x: auto">
                    <h2 class="nk-sec">Konfirmasi baca{{ $sop->activeVersion ? ' versi '.$sop->activeVersion->number : '' }}</h2>
                    @if ($readers === null)
                        <p class="nk-muted">Belum ada versi aktif.</p>
                    @elseif ($readers->isEmpty())
                        <p class="nk-muted">Belum ada penerima. Pakai tombol "Atur penerima".</p>
                    @else
                        <table style="width: 100%; border-collapse: collapse; font-size: 14px">
                            <tbody>
                                @foreach ($readers as $r)
                                    <tr style="border-top: 1px solid #e6e2e0">
                                        <td style="padding: 8px">{{ $r['name'] }} <span class="nk-muted">({{ $r['role'] }})</span></td>
                                        <td style="padding: 8px">
                                            @if ($r['read_at']) <span class="nk-chip nk-chip-ok">Sudah baca {{ $dateTime($r['read_at']) }}</span>
                                            @else <span class="nk-chip nk-chip-bad">Belum baca</span> @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </section>
            @endif
        @endunless
    </div>

    @once
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('sopDiagram', (steps, name) => ({
                    steps, name, step: null,
                    pick(event) {
                        const node = event.target.closest('[data-step]');
                        if (!node) return;
                        this.$refs.canvas.querySelectorAll('.selected').forEach(n => n.classList.remove('selected'));
                        node.classList.add('selected');
                        this.step = this.steps[node.dataset.step] ?? null;
                    },
                    svgEl() { return this.$refs.canvas.querySelector('svg'); },
                    svgText() {
                        const clone = this.svgEl().cloneNode(true);
                        clone.querySelectorAll('.selected').forEach(n => n.classList.remove('selected'));
                        return new XMLSerializer().serializeToString(clone);
                    },
                    download(url, file) {
                        const a = document.createElement('a');
                        a.href = url; a.download = file; a.click();
                    },
                    exportSvg() {
                        const url = URL.createObjectURL(new Blob([this.svgText()], { type: 'image/svg+xml' }));
                        this.download(url, this.name + '.svg');
                        URL.revokeObjectURL(url);
                    },
                    exportPng() {
                        const svg = this.svgEl();
                        const w = svg.viewBox.baseVal.width, h = svg.viewBox.baseVal.height, scale = 2;
                        const img = new Image();
                        img.onload = () => {
                            const canvas = document.createElement('canvas');
                            canvas.width = w * scale; canvas.height = h * scale;
                            const ctx = canvas.getContext('2d');
                            ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
                            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                            this.download(canvas.toDataURL('image/png'), this.name + '.png');
                        };
                        img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(this.svgText());
                    },
                    exportPdf() {
                        const win = window.open('', '_blank');
                        if (!win) return;
                        win.document.write('<!doctype html><title>' + this.name + '</title><style>@page{size:landscape;margin:10mm}body{margin:0}svg{width:100%;height:auto}</style>' + this.svgText());
                        win.document.close();
                        win.focus();
                        setTimeout(() => win.print(), 300);
                    },
                }));
            });
        </script>
    @endonce
</x-filament-panels::page>
