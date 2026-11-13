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

## Bahasa

Situs tersedia dalam 5 bahasa. Indonesia adalah bahasa utama (tanpa prefiks), sisanya memakai prefiks di URL:

| Bahasa | Landing | Artikel |
|---|---|---|
| Indonesia | `/` | `/artikel/apa-itu-terapi-hidrogen` |
| English | `/en/` | `/en/artikel/apa-itu-terapi-hidrogen` |
| 中文 (Simplified) | `/zh/` | `/zh/artikel/apa-itu-terapi-hidrogen` |
| 日本語 | `/ja/` | `/ja/artikel/apa-itu-terapi-hidrogen` |
| 한국어 | `/ko/` | `/ko/artikel/apa-itu-terapi-hidrogen` |

- Semua teks ada di `src/i18n/{id,en,zh,ja,ko}.js` dengan struktur kunci yang sama. Tambah atau ubah teks di semua file sekaligus.
- Tanda `{it}...{/it}` di judul menghasilkan kata penekanan bergaya Newsreader. Untuk CJK gaya italic diganti huruf tebal.
- Data dari `site.config.mjs` boleh berupa teks biasa (Indonesia) atau objek per bahasa, misalnya `garansi: { id: '...', en: '...', zh: '...' }`. Bahasa yang belum diisi memakai teks Indonesia.
- Font Noto Sans SC/JP/KR dimuat hanya di halaman bahasa terkait. Teks WhatsApp otomatis mengikuti bahasa halaman.
- Dengan `SITE_URL` terisi, halaman otomatis memuat `hreflang` untuk semua bahasa.
- **Terjemahan EN/ZH/JA/KO ditulis oleh AI dan belum ditinjau penutur asli.** Minta peninjauan sebelum rilis, terutama kalimat tentang manfaat dan keamanan, karena aturan iklan produk kesehatan berbeda di tiap negara.

## Halaman testimoni

`/testimoni` (dan `/en/testimoni`, `/zh/testimoni`, `/ja/testimoni`, `/ko/testimoni`) menampilkan semua bentuk testimoni (tulisan, foto, video) dengan filter jenis, dan formulir bagi pelanggan untuk mengirim pengalaman mereka. Tautannya ada di footer dan di section "Cerita pengguna" pada landing.

- **Tata letak**: hero berilustrasi, lalu mosaik "Sorotan" yang mencampur video, foto, kutipan, dan cerita panjang (otomatis dipilih dari data: video pertama, foto pertama dan kedua, satu kutipan, dan satu cerita di atas 180 karakter), lalu daftar kartu "Semua testimoni" dengan filter untuk sisanya, lalu formulir. Video YouTube baru dimuat saat tombol putar ditekan. Isi `poster` (gambar di `public/testimoni/`) agar video punya sampul.
- **Isi daftar**: hanya dari `testimonials` di `site.config.mjs`, yaitu testimoni asli dengan izin tertulis. Format tiap item ada di komentar file itu (`type`: `text`, `photo`, atau `video`; opsional `poster` untuk video). Foto ditaruh di `public/testimoni/`. Video boleh tautan YouTube atau file di `public/testimoni/`. Bila kosong, halaman menampilkan pesan ajakan, tanpa data karangan.
- **Formulir**: wajib mengisi nama, kota, penilaian, cerita, kontak verifikasi, dan dua centang (izin tampil dan pernyataan pengalaman pribadi). Ada kolom jebakan (honeypot) untuk bot dan petunjuk agar tidak menulis klaim menyembuhkan.
- **Tujuan pengiriman**: bila `FORM_ENDPOINT` terisi, formulir mengirim JSON ke alamat itu (cocok dengan Formspree dan layanan sejenis). Bila `null`, formulir membuka WhatsApp dengan pesan yang sudah terisi. Kiriman tidak langsung tampil di situs: tim memverifikasi dulu, lalu menambahkannya ke `site.config.mjs`.
- **Hadiah testimoni**: halaman menawarkan selang atau aksesori gratis bagi setiap pengguna yang testimoninya lolos verifikasi. Atur di `testimonialReward` pada `site.config.mjs` (`enabled: false` untuk mematikan, `terms` untuk syarat tambahan). Pelanggan mencentang klaim di formulir; setelah kirim muncul panel klaim dengan tombol WhatsApp, dan alamat pengiriman ditanyakan tim lewat kontak, bukan di formulir. Hadiah tidak boleh bergantung pada isi atau penilaian, dan halaman mencantumkan pengungkapan tentang hadiah itu.
- Layanan formulir pihak ketiga bisa berbayar atau menyimpan data pelanggan. Putuskan dulu sebelum mengisi `FORM_ENDPOINT`.

## Struktur

```
site.config.mjs          data yang perlu diisi
src/lib/content.js       helper config, mode preview, link WhatsApp
src/layouts/Base.astro   head, SEO, JSON-LD, header, footer, widget
src/components/          Header (hamburger), Footer, WaWidget (QR build-time), Icon
src/pages/               index.astro, artikel/..., [lang]/ (en, zh, ja, ko)
src/components/Landing.astro, ArticlePage.astro  isi halaman (dipakai semua bahasa)
src/i18n/                kamus teks per bahasa
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
- [ ] Terjemahan EN, ZH, JA, KO ditinjau penutur asli dan diperiksa terhadap aturan iklan setempat
- [ ] Konfirmasi ketentuan hadiah testimoni (jenis barang, batas jumlah, masa berlaku, ongkos kirim) dan isi `testimonialReward.terms` bila perlu
- [ ] Putuskan tujuan formulir testimoni (`FORM_ENDPOINT` atau WhatsApp) dan uji pengirimannya
- [ ] Uji di Chrome desktop dan HP (lebar 360–414px)

## Aturan konten

Hidrogen bukan obat: jangan menulis klaim menyembuhkan atau mencegah penyakit. Disclaimer wajib ada di FAQ, cara pakai, blok konsultasi, artikel, dan footer. Jangan memakai logo atau klaim endorsement NTU/RS Min-Sheng tanpa izin tertulis. Foto wanita bukan testimoni.
