# Catatan keputusan A19 (Pustaka SOP)

Keputusan yang saya ambil sendiri karena spesifikasi 14.2 sampai 14.4 tidak menutupnya. Mohon dikonfirmasi atau dikoreksi.

1. **Pemilik = peran admin.** Sama seperti A18: hanya admin yang membuat, mengubah, dan menyetujui SOP. Admin gudang dan penguji (peran gudang) hanya membaca SOP aktif yang ditugaskan dan menekan konfirmasi baca.
2. **Satu draf terbuka per SOP.** "Setiap simpan menjadi versi draf" saya baca sebagai: simpan berulang menimpa draf yang sedang terbuka; nomor versi baru hanya dibuat saat belum ada draf (salinan dari versi aktif). Kalau mau setiap klik simpan menjadi nomor baru, ini satu baris di `SopService::saveDraft`.
3. **Titik Mulai opsional.** Data 14.3 tidak punya node mulai (12 langkah, 13 panah), jadi diagram tidak menambahkannya. Jenis "Mulai" tetap tersedia di editor untuk SOP lain.
4. **Nomor langkah manual.** Lencana angka diisi di editor; tombol "Nomori ulang langkah" merapikan urutannya setelah langkah dipindah.
5. **Penerima SOP-01:** peran admin, gudang, dan sales (sales ditambahkan supaya tampilan aplikasi sales bisa diuji saat UAT). Ubah lewat tombol "Atur penerima".
6. **Tinjauan berikutnya** otomatis 1 tahun setelah persetujuan. Pengingat tinjauan ke admin mulai 30 hari sebelumnya.
7. **Pengingat baca otomatis:** perintah `sops:remind` tiap hari 08:00, maksimal satu pengingat per orang per versi tiap 3 hari. Tombol "Kirim pengingat baca" mengirim sekarang.
8. **Ekspor diagram:** SVG dan PNG dibuat di browser dari SVG yang sama. "Cetak / PDF" membuka dialog cetak browser (Simpan sebagai PDF). Tidak ada dependency baru.
9. **Alur-SOP-Penerimaan-Inventori-Nikkou.pdf belum ada di mesin ini.** Gaya diagram mengikuti uraian 14.3 (jalur per peran, lencana merah, keputusan biru muda, kartu temuan merah muda). Perlu dicocokkan setelah PDF-nya ada.
10. **Rute `/admin/receipts/create`** di data SOP-01 baru ada setelah branch `feature/goods-receipt` digabung; sebelum itu tautan "Buka layar terkait" menuju halaman yang belum ada.
11. **Tata letak diagram otomatis:** kolom ditentukan dari jalur terpanjang; panah yang membentuk putaran ditandai sebagai panah balik dan digambar di bawah kartu. SOP dengan banyak percabangan mungkin perlu dirapikan manual di versi berikutnya.
