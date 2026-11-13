// Single source of truth for everything that still needs real data.
// Leave a value as `null` and the section that depends on it is hidden in production builds.
// `npm run dev` / `npm run build:preview` show "[placeholders]" instead, so the full design can be reviewed.

export default {
  // WhatsApp business number, international format without "+" (example: 6281234567890)
  WA_NUMBER: '',
  // Where the testimonial form posts its JSON (e.g. a Formspree URL). null = the form opens WhatsApp with a prefilled message instead.
  FORM_ENDPOINT: null,
  WA_TEXT: 'Halo, saya ingin konsultasi tentang inhaler hidrogen',

  brand: 'Nikkou',
  company: null, // legal company name
  address: null, // "Jl. ..., Surabaya"
  hours: null, // "Senin–Sabtu, 09.00–17.00 WIB"

  // Bukti (section is hidden entirely when all four are null)
  evidence: {
    izinEdar: null, // { number: 'KEMENKES ...', validUntil: 'dd/mm/yyyy' }
    labTest: null, // { lab: 'Nama lab', certificate: 'No. sertifikat', purity: '99,99%' }
    garansi: null, // 'Garansi 1 tahun untuk unit utama'
    servis: null, // 'Servis di Surabaya, ...'
  },

  // Teknologi
  tech: {
    tabung: null, // fungsi jendela tabung berlampu biru
    indikator: null, // penjelasan indikator air, filter, suhu
    lampuDasar: null, // fungsi lampu di dasar alat
    batasKonsentrasi: null, // 'di bawah 4%'
    fiturKeselamatan: null, // 'katup satu arah, filter, sensor, pemutus otomatis'
  },

  // Cara pakai
  usage: {
    jenisAir: null,
    durasiPersiapan: null,
    durasiSesi: null,
  },

  // Produk (price: 'Rp 0.000.000'; strikePrice/discount only if the discount is real)
  products: {
    biru: { name: null, price: null, strikePrice: null, discount: null },
    putih: { name: null, price: null, strikePrice: null, discount: null },
    // Add src/assets/aksesori.png to show the accessories card.
    aksesori: { price: null },
  },

  // Real testimonials with written permission. Never invent or edit these.
  // { type: 'text' | 'photo' | 'video', quote, name, city, duration, product, rating (1-5),
  //   image: '/testimoni/foto.jpg' (file in public/, for photo), videoUrl: 'https://youtu.be/...' or '/testimoni/video.mp4' (for video) }
  // `quote` can be a string or { id, en, zh, ja, ko }. Women's stock photos are not testimonials.
  testimonials: [],

  // Free hose/accessory offered to everyone who sends a verified testimonial (shown on the testimonials page).
  // The gift must not depend on the content or rating. `terms` = optional extra conditions, string or { id, en, zh, ja, ko }.
  testimonialReward: { enabled: true, terms: null },

  // Dokter section: keep false until written permission from dr. Chen Xing-Han (and no NTU/Min-Sheng logos).
  doctor: { permission: false },

  // FAQ: official answers. null = hidden.
  faq: {
    amanDiRumah: null,
    kondisiMedisTambahan: null, // optional extra sentence appended to the "konsultasi dulu" answer
    garansiServis: null,
  },

  // Artikel
  articles: {
    main: { author: null, date: null, reviewedBy: null },
    // Set `href` once the article page exists. Cards without href are hidden.
    others: [
      { title: 'Cara Kerja Inhaler Hidrogen: dari Air hingga Napas', category: 'Teknologi', summary: null, date: null, href: null },
      { title: 'Sebelum Memakai Inhaler Hidrogen, Siapa yang Perlu Bertanya ke Dokter?', category: 'Keamanan', summary: null, date: null, href: null },
      { title: 'Merawat Inhaler Hidrogen: Filter, Masker, dan Air yang Tepat', category: 'Perawatan', summary: null, date: null, href: null },
    ],
  },
};
