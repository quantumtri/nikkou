# Catatan keputusan: A18 Penerimaan barang masuk

Keputusan yang saya ambil sendiri (pilihan paling sederhana) karena spesifikasi atau prompt belum menentukannya.
Silakan koreksi bila ada yang tidak sesuai.

## Peran
- Admin gudang = peran **Staf gudang**. Penguji = siapa pun dari Staf gudang atau Admin yang mengisi uji fungsi (nama tercatat otomatis, bukan peran baru).
- Pemilik atau manajer = peran **Admin**. Hanya Admin yang menyetujui penerimaan, menutup temuan, dan menyetujui temuan tetap terbuka.
- Sales tidak punya akses ke penerimaan.
- Penerima yang juga Admin tidak boleh menyetujui sendiri bila ada unit gagal atau temuan yang dibiarkan terbuka; penerimaan bersih boleh disetujui pembuatnya.

## Aturan
- Hasil **Lolos** bila menyala, output, tidak bocor, tidak ada bunyi atau panas tidak wajar (kolom uji di langkah 5 spesifikasi), fisik baik, dan lengkap semuanya benar. Prompt hanya menyebut lima syarat; kolom "bunyi atau panas" saya tambahkan karena ada di langkah 5 spesifikasi 14.1.
- **Dokumen tidak cocok** = pilihan "Tidak cocok" pada "PO, invoice, packing list, dan fisik cocok" atau "Model dan jumlah sesuai". Selisih jumlah koli saja tidak mengunci langkah 4.
- Temuan yang **mengunci langkah 4**: jenis "Jumlah atau model tidak sesuai" dan "Dokumen tidak lengkap" yang mencakup seluruh kiriman (bukan terkait satu unit). Temuan lain tidak mengunci.
- **Status unit** saat disimpan: Lolos dan tanpa temuan = `tersedia`. Gagal uji, fisik tidak baik, kelengkapan kurang, atau ada temuan unit cacat, gagal uji, atau aksesori kurang = `perlu_servis`. Seluruh kiriman = `ditahan` bila masih ada temuan dokumen yang belum ditutup (disetujui pemilik tetap terbuka).
- Uji ulang: satu kali, hanya setelah gagal.
- Unit yang sudah disimpan tidak bisa diubah dari penerimaan; hanya rak yang bisa diisi sampai penerimaan disetujui.

## Data
- Nama tabel dan kolom memakai bahasa Inggris seperti modul lain. `goods_receipt_items` mendapat kolom `unit_type_id` (tipe unit wajib untuk membuat unit) dan `components` (daftar kelengkapan JSON); `goods_receipt_checks.passed` menggantikan `ya`.
- Tabel baru di luar 14.1: `sequences` (penghitung nomor `PB-YYYYMM-0001` dan kode unit, dengan kunci baris), `app_settings` (nama perusahaan dan kode form untuk PDF), `admin_message_queue` (antrean WhatsApp admin), dan kolom `units.shelf`.
- Kode unit `NK-####` dialokasikan saat baris unit dibuat di langkah 4 (bukan saat unit disimpan), supaya terlihat di tabel.
- Dokumen impor penerimaan ikut dihubungkan ke setiap unit yang disimpan (berkas yang sama).

## Tambahan terhadap modul lain
- Status unit baru `ditahan` (label Ditahan): hanya bisa keluar lewat aturan penerimaan; tidak bisa dipinjamkan.
- A5 (tambah unit manual) kini meminta **alasan wajib**, dicatat di riwayat unit.
- Grup menu Inventori memuat Inventori, Penerimaan barang, dan Label QR.

## Notifikasi dan pengiriman
- Ringkasan ke pemilik: notifikasi di aplikasi, email (Laravel Mail; di server pakai SMTP sungguhan, bawaan hanya mencatat ke log), dan **antrean WhatsApp** di tabel `admin_message_queue` sampai integrasinya ada.
- Temuan lewat batas waktu: perintah `receipts:notify-overdue` tiap jam, satu pemberitahuan per hari per temuan, tingkat "Tinggi". Modul notifikasi A13 belum ada. Server perlu menjalankan scheduler Laravel (satu entri cron `php artisan schedule:run` tiap menit).

## Cetak PDF
- `Lembar-Periksa-Penerimaan-Nikkou.pdf` tidak tersedia saat membangun, jadi tata letak disusun dari deskripsi di prompt. "Tabel 7 baris per unit" saya tafsirkan sebagai tabel unit minimal 7 baris (bertambah bila unit lebih banyak). Mohon cocokkan dengan PDF asli.
- Font Outfit (SIL OFL) disertakan di `storage/fonts/`.
- Formulir kosong ada di `/admin/receipt-forms/blank`, bukan `/admin/receipts/blank-form`, karena rute panel `/admin/receipts/{record}` menangkap alamat itu.

## Belum dikerjakan
- Pembaruan stok aksesori (A16) di langkah 7: modul A16 belum ada.
- Endpoint dan layar A19 (Pustaka SOP): sengaja tidak dibangun.
- Pemindaian label dengan kamera di panel admin: kolom "Scan label" menerima alat scan USB atau Bluetooth (atau ketik).
