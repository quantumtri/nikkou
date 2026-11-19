<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Sop;
use App\Models\SopAssignment;
use App\Models\User;
use App\Services\SopService;
use Illuminate\Database\Seeder;

/** SOP-01 from spec 14.3 (published) plus the other SOPs from the preparation map as empty drafts. */
class SopSeeder extends Seeder
{
    public const OTHER_SOPS = [
        'SOP-02' => 'Peminjaman dan serah terima unit',
        'SOP-03' => 'Pengembalian unit',
        'SOP-04' => 'Servis dan garansi',
        'SOP-05' => 'Keluhan dan kejadian tidak diinginkan',
        'SOP-06' => 'Follow-up harian pelanggan',
        'SOP-07' => 'Medical check-up',
        'SOP-08' => 'Penjualan dan pengiriman',
        'SOP-09' => 'Pemeriksaan barang masuk aksesori',
        'SOP-10' => 'Onboarding sales',
    ];

    public function run(): void
    {
        $owner = User::where('role', UserRole::Admin)->orderBy('id')->first();
        if (! $owner) {
            return;
        }

        $service = app(SopService::class);

        $sop = Sop::firstOrCreate(['code' => 'SOP-01'], ['title' => 'Penerimaan inventori masuk', 'owner_id' => $owner->id]);
        if (! $sop->versions()->exists()) {
            $draft = $service->saveDraft($sop, self::sop01(), $owner, 'Versi awal dari spesifikasi.');
            $service->publish($draft, $owner);
            foreach (['admin', 'warehouse', 'sales'] as $role) {
                SopAssignment::firstOrCreate(['sop_id' => $sop->id, 'role' => $role]);
            }
        }

        foreach (self::OTHER_SOPS as $code => $title) {
            Sop::firstOrCreate(['code' => $code], ['title' => $title, 'owner_id' => $owner->id]);
        }
    }

    /** @return array<string, mixed> */
    public static function sop01(): array
    {
        $step = fn (string $key, string $type, string $role, string $title, ?int $number = null, ?string $desc = null, ?string $deadline = null, ?string $route = null) => [
            'key' => $key, 'number' => $number, 'type' => $type, 'title' => $title, 'description' => $desc,
            'role' => $role, 'deadline' => $deadline, 'related_route' => $route,
        ];
        $edge = fn (string $from, string $to, ?string $label = null, string $type = 'normal') => compact('from', 'to', 'label', 'type');

        return [
            'roles' => ['Admin gudang', 'Penguji', 'Pemilik / manajer'],
            'steps' => [
                $step('s1', 'step', 'Admin gudang', 'Persiapan', 1, 'Cek PO dan invoice pemasok, siapkan area penerimaan dan alat.'),
                $step('s2', 'step', 'Admin gudang', 'Terima barang dan periksa kemasan luar', 2, 'Cocokkan kurir dan jumlah koli, foto kemasan sebelum dibuka, catat kerusakan di surat jalan.'),
                $step('s3', 'step', 'Admin gudang', 'Cocokkan PO, invoice, packing list, dan fisik', 3, 'Bandingkan produk, model, jumlah, dan nomor seri. Salin dokumen impor.'),
                $step('d1', 'decision', 'Admin gudang', 'Dokumen dan jumlah cocok?'),
                $step('t1', 'step', 'Pemilik / manajer', 'Barang ditahan', null, 'Foto, lapor pemilik dan pemasok, putuskan klaim atau minta kiriman susulan.', 'Hari yang sama'),
                $step('s4', 'step', 'Admin gudang', 'Periksa kelengkapan dan kondisi tiap unit', 4, 'Buka satu per satu, cocokkan daftar kelengkapan, foto kondisi awal.'),
                $step('s5', 'step', 'Penguji', 'Uji fungsi', 5, 'Pasang sesuai buku petunjuk, nyalakan, cek output, bocor, bunyi, dan panas.'),
                $step('d2', 'decision', 'Penguji', 'Unit lolos uji?'),
                $step('f1', 'step', 'Pemilik / manajer', 'Status Perlu servis dan ajukan klaim', null, null, '1 hari kerja'),
                $step('s6', 'step', 'Admin gudang', 'Input di web admin, label QR, status Tersedia', 6, null, null, '/admin/receipts/create'),
                $step('s7', 'step', 'Admin gudang', 'Simpan di rak, arsipkan, laporkan ke pemilik', 7),
                $step('end', 'end', 'Pemilik / manajer', 'Selesai'),
            ],
            'edges' => [
                $edge('s1', 's2'), $edge('s2', 's3'), $edge('s3', 'd1'),
                $edge('d1', 's4', 'Cocok'), $edge('d1', 't1', 'Tidak cocok', 'problem'), $edge('t1', 's4', 'temuan ditutup', 'delayed'),
                $edge('s4', 's5'), $edge('s5', 'd2'),
                $edge('d2', 's6', 'Lolos'), $edge('d2', 'f1', 'Gagal', 'problem'),
                $edge('s6', 's7'), $edge('s7', 'end'), $edge('f1', 'end', 'dicatat di laporan penerimaan', 'delayed'),
            ],
            'forms' => [
                ['name' => 'Lembar periksa penerimaan', 'type' => 'web', 'route_or_file' => '/admin/receipts/create', 'step' => 's6'],
                ['name' => 'Lembar periksa penerimaan (kertas)', 'type' => 'paper_pdf', 'route_or_file' => 'Lembar-Periksa-Penerimaan-Nikkou.pdf', 'step' => null],
            ],
        ];
    }
}
