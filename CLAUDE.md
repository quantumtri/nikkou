# Nikkou — Landing Page Inhaler Hidrogen (brief untuk Claude Code)

## Tujuan
Bangun website statis dua halaman dalam Bahasa Indonesia:
1. **Landing page** (`/`) yang meyakinkan calon pembeli bahwa inhaler hidrogen ini aman, bisa dibuktikan, dan bermakna.
2. **Detail artikel** (`/artikel/apa-itu-terapi-hidrogen`) sebagai contoh template blog.

Acuan visual ada di `reference/Main.dc.html` dan `reference/Article.dc.html`. File itu memakai format desain khusus (`<x-dc>`, `{{ }}`, `DCLogic`) dan **tidak untuk dijalankan**. Baca sebagai spesifikasi visual, lalu bangun ulang dengan HTML/CSS/JS biasa. Semua gambar ada di `assets/` (path di acuan sudah mengarah ke sana).

## Stack yang disarankan
- Astro (atau HTML statis + Tailwind). Tanpa framework berat. Target: cepat di HP.
- Hosting: Vercel atau Netlify.
- Satu file konfigurasi `site.config.*` berisi: `WA_NUMBER` (format 628xxx), nama perusahaan, alamat, jam layanan, tanggal. Semua link WhatsApp dan QR dibuat dari `WA_NUMBER`.
- QR WhatsApp dibuat saat build (paket `qrcode`/`segno`), isinya `https://wa.me/<WA_NUMBER>?text=Halo%2C%20saya%20ingin%20konsultasi%20tentang%20inhaler%20hidrogen`. Warna modul `#C0261B` di latar putih.

## Brand tokens (Nikkou)
| Token | Hex | Pakai untuk |
|---|---|---|
| red | #E83828 | latar hero dan CTA, teks besar (>=24px, atau >=19px tebal) |
| deep red | #C0261B | teks kecil, tombol, judul di latar terang |
| darkest | #7A1D14 | judul di latar rose, hover |
| coral | #F49C94 | bentuk dekoratif |
| rose | #F9CDC9 | latar bagian, chip |
| blush | #EE6A5E | aksen |
| sky | #86B3E0 | bentuk dekoratif, latar bagian dokter |
| sky light | #DCE9F6 | panel |
| ink | #1a1a1a | teks utama |
| grey | #727171 | teks sekunder |
| mist | #EFEFEF, #FAF8F7, #E6E2E0 | latar dan garis |
| footer | #1d1a19 | latar footer |

**Kontras:** putih di atas #E83828 hanya 4.18:1. Jadi teks putih di atas merah hanya boleh besar/tebal. Teks kecil pakai deep red di latar terang.

## Tipografi
- **Outfit** (400/500/600/700): isi dan judul. Judul section: `clamp(30px,4vw,44px)`, weight 500, warna deep red.
- **Newsreader italic** (400): hanya untuk kata penekanan di judul (contoh: "*tidak semudah kelihatannya*").
- Ukuran isi minimal 16px.

## Bentuk dan komponen
- Kartu: radius 24–32px. Tombol: pill (radius 999px), tinggi min 44–48px.
- Bentuk lengkung (arch): `border-radius: 999px 999px 28px 28px`.
- Foto "keluar dari bentuk": bentuk berwarna di belakang, cutout PNG transparan menimpa (absolute) dan melewati tepi bentuk.
- Tombol: putih (di atas merah), ghost putih, deep red (di atas terang), outline deep red.

## Urutan section landing page (jangan diubah tanpa alasan)
1. **Announcement bar** (rose): ajakan konsultasi gratis.
2. **Header**: logo wordmark, menu (Masalah, Solusi, Cara pakai, Bukti, Produk, FAQ), tombol "Konsultasi". Di HP menu disembunyikan; **tambahkan menu hamburger**.
3. **Hero** (latar merah): H1 "Hidrogen murni dalam *setiap tarikan napas*", subjudul, dua tombol, lalu SATU baris 4 foto bercaption: wanita membaca (arch rose), wanita kanula (rounded sky), ruang tamu (rounded square), inhaler biru (arch coral). Wrap jadi 2x2 di HP.
4. **Masalah**: pain point utama "Ingin menjaga kesehatan sampai ke tingkat sel" + kutipan konsumen; 3 kebutuhan (menjangkau lokasi kerusakan, bekerja dengan tepat, manfaat dan keamanan jelas); kotak biru "Mengapa hidrogen menarik untuk diteliti?" + kotak putih "Perlu diingat"; lalu "Lalu, memilih produknya juga tidak mudah" dengan 3 kekhawatiran pembeli, masing-masing punya baris "Jawaban kami"; pita rose dengan tombol ke #teknologi.
5. **Solusi / Teknologi** (`#teknologi`, latar rose): label "Solusinya", showcase inhaler biru dengan 4 bagian beranotasi, 3 pilar (kemurnian terukur, kadar terkontrol, perlindungan berlapis).
6. **Cara pakai** (`#cara-pakai`): 3 langkah, masing-masing dengan gambar 4:3 di atas. Disclaimer konsultasi dokter di bawahnya.
7. **Bukti** (`#bukti`, latar #FAF8F7): 4 kartu (izin edar, uji lab, garansi, servis).
8. **Produk** (`#produk`): 3 kartu (inhaler biru, inhaler putih, aksesori).
9. **Blok konsultasi**: foto wanita keluar dari arch biru dengan 3 chip melayang, judul "Bingung cocok atau tidak?", tombol WhatsApp.
10. **Perbandingan**: tabel "Yang diperiksa" (alat kami vs alat tanpa standar). Tabel scroll horizontal di HP.
11. **Dokter**: foto dr. Chen Xing-Han dalam arch, kartu kredensial dengan terjemahan Indonesia.
12. **Testimoni**: 3 kartu.
13. **FAQ** (`#faq`): 4 pertanyaan (pakai `<details>`).
14. **Artikel** (`#artikel`): 4 kartu, kartu pertama ke halaman detail artikel.
15. **CTA** (`#konsultasi`, latar merah): "Masih ragu? *Tanya dulu, gratis.*"
16. **Footer** (gelap) + **widget WhatsApp mengambang**.

## Widget WhatsApp mengambang
- Pojok kanan bawah, tetap terlihat saat scroll.
- Kartu putih (lebar ~224px): judul "Chat WhatsApp sekarang!", QR (klik membuka WhatsApp), tombol minimalkan bulat gelap di pojok kanan atas kartu.
- Tombol bulat 64px dengan simbol Nikkou membuka/menutup kartu.
- Di layar < 640px kartu **mulai tertutup**.
- Target sentuh minimal 44px.

## Halaman artikel
Header sama, breadcrumb, banner rounded dengan overlay gradasi gelap + badge kategori + H1 + meta; 3 kolom: daftar isi sticky (sembunyi < 1100px), isi artikel (maks 760px), sidebar sticky (kartu produk + "Punya pertanyaan?"); lalu FAQ, artikel terkait, CTA, footer, widget WhatsApp. Tabel di artikel scroll horizontal di HP.

## Responsif
- Mobile-first. Titik patah: 640px (padding section 56px, tombol penuh lebar), 860px (menu hamburger), 980px (sidebar jadi satu kolom), 1100px (daftar isi disembunyikan).
- Tidak boleh ada scroll horizontal halaman.

## Aturan konten (WAJIB)
- **Jangan** menulis klaim menyembuhkan atau mencegah penyakit. Hidrogen bukan obat. Jangan menulis bahwa hidrogen "pasti lebih unggul" dari antioksidan lain, atau "hanya membersihkan radikal jahat".
- Disclaimer ("bukan obat, tidak menggantikan dokter") wajib tetap ada di FAQ, cara pakai, blok konsultasi, artikel, dan footer.
- "Menarik secara mekanisme" tidak sama dengan "terbukti lebih unggul". Pertahankan nada hati-hati.
- Jangan menyebut "dokter" sebagai konsultan kecuali sudah ada. Pakai "tim pendamping".
- Jangan menaruh logo NTU/RS Min-Sheng atau klaim endorsement tanpa izin tertulis.
- Foto wanita bukan testimoni. Jangan dilabeli sebagai pengguna.

## Placeholder `[ ]` yang harus diisi sebelum rilis
Jangan tayang dengan tanda kurung siku yang masih terlihat. Kalau datanya belum ada, **sembunyikan bagiannya**, jangan isi karangan.
- Nomor WhatsApp bisnis (format 628xxx), nama perusahaan, alamat, jam layanan.
- Nama produk, harga, harga coret/diskon (hanya bila nyata), foto aksesori.
- Bukti: nomor izin edar + tanggal, hasil uji lab (nama lab, nomor sertifikat, kemurnian), garansi, lokasi servis.
- Teknologi: fungsi jendela tabung, indikator, lampu dasar, batas konsentrasi, daftar fitur keselamatan.
- Cara pakai: jenis air, durasi persiapan, durasi sesi.
- Testimoni asli dengan izin tertulis; izin tertulis dokter.
- Artikel: penulis, tanggal, ringkasan, "Ditinjau secara medis oleh", dan teks 3 artikel lain.
- FAQ: jawaban resmi.
- Foto kedua wanita bertuliskan "NIKOU" di perangkat: periksa/ganti sebelum rilis.

## SEO dan teknis
- `lang="id"`, judul dan meta description per halaman, Open Graph (gambar share), `alt` deskriptif, satu H1 per halaman.
- Schema JSON-LD: `Organization`, `Product` (setelah harga ada), `FAQPage`, `Article`.
- Optimalkan gambar (WebP/AVIF, `loading="lazy"`, ukuran eksplisit). Gambar sumber besar (hingga ~2 MB).
- Font di-host sendiri atau `preconnect` Google Fonts; `font-display: swap`.
- Aksesibilitas: fokus terlihat, kontras sesuai tabel di atas, `aria-label` pada tombol ikon.

## Aset (`assets/`)
| File | Dipakai di |
|---|---|
| logo-wordmark.png | header, footer |
| logo-symbol.png | tombol widget WhatsApp |
| inhaler-biru-cutout.png | hero, teknologi, produk, langkah 1, artikel |
| inhaler-putih-cutout.png | produk, langkah 3, artikel terkait |
| ruang-tamu-inhaler-putih.png | hero, banner artikel |
| sesi-samping-tempat-tidur.png | langkah 2, artikel terkait |
| dokter-chen-xing-han.png | bagian dokter |
| wanita-membaca-cutout.png | hero, blok konsultasi |
| wanita-kanula-cutout.png | hero |

## Status
Desain belum diuji di layar nyata oleh pembuat desain. Setelah build, uji di Chrome (desktop) dan di HP (lebar 360–414px) sebelum rilis.
