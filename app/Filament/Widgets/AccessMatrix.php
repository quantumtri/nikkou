<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/** Summary of who may use what (spec section 2). The rules themselves are enforced by policies on the server. */
class AccessMatrix extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.access-matrix';

    protected int|string|array $columnSpan = 'full';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'rows' => [
                ['Form peminjaman dan scan', 'Ya', 'Ya', 'Tidak'],
                ['Pengembalian unit', 'Ya', 'Ya (peminjaman sendiri)', 'Ya'],
                ['Peminjaman', 'Semua', 'Milik sendiri', 'Tidak'],
                ['Input inventori', 'Ya', 'Tidak', 'Ya'],
                ['Label QR', 'Ya', 'Tidak', 'Ya'],
                ['Dasbor dan leads semua sales', 'Ya', 'Tidak', 'Tidak'],
                ['Follow-up otomatis', 'Ya', 'Lihat saja', 'Tidak'],
                ['Akun dan akses', 'Ya', 'Tidak', 'Tidak'],
            ],
        ];
    }
}
