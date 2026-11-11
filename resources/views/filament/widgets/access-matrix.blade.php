<x-filament-widgets::widget>
    <x-filament::section heading="Hak akses menu">
        <div style="overflow-x: auto">
            <table class="nk-table">
                <thead>
                    <tr><th scope="col">Menu</th><th scope="col">Admin</th><th scope="col">Sales</th><th scope="col">Staf gudang</th></tr>
                </thead>
                <tbody>
                    @foreach ($rows as [$menu, $admin, $sales, $warehouse])
                        <tr>
                            <th scope="row" style="font-weight: 400">{{ $menu }}</th>
                            @foreach ([$admin, $sales, $warehouse] as $value)
                                <td @class(['nk-muted' => $value === 'Tidak']) @style(['font-weight: 600' => $value !== 'Tidak'])>{{ $value }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="nk-muted" style="margin-top: 12px; font-size: 12px">
            Ringkasan dari spesifikasi. Pembatasan dijalankan di server, bukan hanya dengan menyembunyikan menu.
        </p>
    </x-filament::section>
</x-filament-widgets::widget>
