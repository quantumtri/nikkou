# Nikkou Landing

Website statis dua halaman (Bahasa Indonesia) untuk inhaler hidrogen Nikkou:

- `/` landing page
- `/artikel/apa-itu-terapi-hidrogen` contoh template artikel

Stack: Astro, CSS biasa, JS minimal. Spesifikasi lengkap ada di `CLAUDE.md`, acuan visual di `reference/`.

## Menjalankan

```bash
npm install
npm run dev            # preview: data kosong tampil sebagai [placeholder]
npm run build          # produksi: bagian tanpa data disembunyikan
npm run build:preview  # build statis dengan placeholder (untuk review desain)
npm run preview        # sajikan hasil build
```

Env opsional saat build:

| Env | Fungsi |
|---|---|
| `SITE_URL` | Domain final (mis. `https://nikkou.id`). Mengaktifkan canonical dan OG image absolut. |
| `PREVIEW_PLACEHOLDERS=1` | Menampilkan `[placeholder]` untuk data yang kosong. |

## Mengisi data

Semua data yang belum ada ada di satu file: `site.config.mjs`.
Nilai `null` berarti bagian terkait **disembunyikan** di build produksi.

| Kunci | Efek bila diisi |
|---|---|
| `WA_NUMBER` (format `628xxx`) | Semua link WhatsApp dan QR widget |
| `company`, `address`, `hours` | Footer, blok konsultasi |
| `evidence.*` | Section Bukti, pilar Teknologi, baris tabel Perbandingan |
| `tech.*` | Anotasi alat dan pilar Teknologi |
| `usage.*` | Teks langkah Cara pakai dan FAQ artikel |
| `products.*` | Nama, harga, harga coret dan diskon (hanya bila nyata) |
| `testimonials` | Section Testimoni (hanya testimoni asli dengan izin tertulis) |
| `doctor.permission` | Section Dokter. Tetap `false` sampai ada izin tertulis |
| `faq.*` | Jawaban FAQ resmi |
| `articles.*` | Penulis, tanggal, peninjau medis, dan kartu artikel lain (isi `href` agar tampil) |

Foto aksesori: taruh file di `src/assets/aksesori.png` (atau format lain) agar kartu aksesori tampil.

## Struktur

```
site.config.mjs          data yang perlu diisi
src/lib/content.js       helper config, mode preview, link WhatsApp
src/layouts/Base.astro   head, SEO, JSON-LD, header, footer, widget
src/components/          Header (hamburger), Footer, WaWidget (QR build-time), Icon
src/pages/               index.astro, artikel/apa-itu-terapi-hidrogen.astro
src/styles/global.css    token brand dan seluruh gaya
src/assets/              gambar sumber (dioptimasi ke WebP saat build)
```

## Checklist sebelum rilis

- [ ] Isi `WA_NUMBER` dan pastikan build tidak lagi memberi peringatan
- [ ] Isi data bukti (izin edar, uji lab, garansi, servis) dan data teknologi
- [ ] Isi nama dan harga produk; tambah foto aksesori
- [ ] Testimoni asli dengan izin tertulis; izin tertulis dokter sebelum `doctor.permission = true`
- [ ] Penulis, tanggal, dan peninjau medis artikel
- [ ] Periksa atau ganti foto wanita yang bertuliskan "NIKOU"
- [ ] Jawaban FAQ resmi
- [ ] Build dengan `SITE_URL` final; pastikan tidak ada `[ ]` terlihat
- [ ] Uji di Chrome desktop dan HP (lebar 360–414px)

## Aturan konten

Hidrogen bukan obat: jangan menulis klaim menyembuhkan atau mencegah penyakit. Disclaimer wajib ada di FAQ, cara pakai, blok konsultasi, artikel, dan footer. Jangan memakai logo atau klaim endorsement NTU/RS Min-Sheng tanpa izin tertulis. Foto wanita bukan testimoni.
