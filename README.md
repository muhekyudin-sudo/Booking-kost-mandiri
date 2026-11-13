# Kost Mandiri

Aplikasi pemesanan kost berbasis **PHP Native** (tanpa framework). Penyewa melihat katalog kost per provinsi/kota/gedung, memilih kelas kamar, lalu memesan dan membayar lewat QRIS. Resepsionis memverifikasi pembayaran, admin mengelola master data.

> README ini juga berfungsi sebagai **konteks untuk prompt berikutnya** (ke Claude atau AI lain). Lihat bagian [Panduan menulis prompt](#panduan-menulis-prompt).

---

## Stack

| Bagian | Teknologi |
| --- | --- |
| Backend | PHP Native, router buatan sendiri (`app/core/Router.php`) |
| Database | MySQL/MariaDB (`database/schema.sql`) |
| Email | PHPMailer (`composer.json`) |
| Frontend | HTML dari PHP, CSS manual (tanpa framework), ikon Feather, font Plus Jakarta Sans |
| Server lokal | Virtual host `kost-mandiri.test`, document root ke folder `public/` |

Konfigurasi ada di `.env` (contoh: `.env.example`): `APP_*`, `DB_*`, `WA_OWNER_NUMBER`, `QRIS_IMAGE_URL`, `SMTP_*`.

---

## Struktur folder

```
kost-mandiri/
├── app/
│   ├── controllers/   Catalog, Auth, Booking, Admin, Receptionist, Dashboard, Staff, Home
│   ├── core/          Router.php
│   ├── helpers/       auth, http, messaging, security, validation
│   ├── middleware/    guards.php (auth, role, readonly, property, staff)
│   ├── services/      BookingService, EmailService
│   └── views/         halaman PHP (lihat tabel di bawah)
├── config/            config.php, database.php
├── cron/              expire_bookings.php (booking kedaluwarsa)
├── database/          schema.sql, migrations/
├── public/            document root
│   ├── index.php
│   └── assets/
│       ├── css/       semua stylesheet (lihat bagian CSS)
│       ├── js/        searchable-select.js (dropdown yang bisa diketik)
│       └── img/       foto kota, provinsi, gedung, kelas, qris.jpeg
├── routes/web.php     daftar route
└── storage/           log dan bukti pembayaran (private)
```

Tabel database: `users`, `cities`, `properties`, `room_classes`, `room_units`, `price_matrices`, `bookings`, `payments`, `receptionist_staff`.

---

## Peran dan halaman

| Peran | URL utama | Keterangan |
| --- | --- | --- |
| Pengunjung | `/`, `/katalog/gedung/{id}` | Katalog publik, bisa difilter kota, kelas kamar, jenis kost |
| Penyewa (`tenant`) | `/tenant/bookings` | Checkout, upload bukti bayar, riwayat booking |
| Resepsionis (`receptionist`) | `/resepsionis` | Verifikasi pembayaran, kasir, pembatalan |
| Admin (`admin`) | `/admin` | Master data dan laporan transaksi (hanya baca) |

### Peta view ke CSS

Layout `app/views/layouts/main.php` selalu memuat `base.css`. CSS tambahan dikirim controller lewat `extraCss`.

| Halaman | View | CSS tambahan |
| --- | --- | --- |
| Katalog (kota/gedung) | `catalog/index.php` | `filters.css`, `catalog.css` |
| Detail gedung | `catalog/property.php` | `catalog.css` |
| Login / Register | `auth/*.php` | `auth.css` |
| Booking penyewa | `booking/*.php` | `booking.css` |
| Dashboard | `dashboard.php`, `admin/dashboard.php`, `receptionist/dashboard.php` | `dashboard.css` |
| Master data admin | `admin/master-data.php` | `masterdata.css` |
| Transaksi admin, booking resepsionis | `admin/*`, `receptionist/bookings.php` | `filters.css`, `booking.css` |

---

## Panduan CSS

Semua file ada di `public/assets/css/` dan **setiap aturan sudah diberi komentar fungsi** dalam bahasa Indonesia. Aturan di dalam `@media` diawali kata **"HP:"**.

| File | Isi |
| --- | --- |
| `base.css` | Warna (variabel `--biru`, `--teks-abu`, dst.), header, `.card`, form, tombol, `.badge`, `.page-heading`, `.stats`/`.stat` |
| `catalog.css` | Katalog: kartu kota, bulatan provinsi, kartu gedung, kartu kelas, dropdown yang bisa diketik, header katalog di HP |
| `filters.css` | Form filter (Kota, Kelas kamar, Jenis kost, tombol Terapkan/Reset) |
| `booking.css` | Detail booking dan gambar QRIS |
| `auth.css` | Login dan register |
| `dashboard.css` | Kartu statistik dan kartu menu dashboard |
| `masterdata.css` | Navigasi tab, filter, dan form master data admin |

### Breakpoint

| Lebar | Dipakai untuk |
| --- | --- |
| `max-width: 900px` | Dashboard: statistik 2 kolom |
| `max-width: 700px` | Layout HP umum (header, kartu, header katalog, filter) |
| `max-width: 600px` | Kartu kota/gedung/kelas diperkecil |
| `max-width: 480px` | Kartu login/register |

Perangkat uji: **Samsung Galaxy A55, 360 x 800** di DevTools mode responsif.

### Konvensi

- Nama class memakai bahasa Inggris atau campuran, huruf kecil dengan tanda hubung (`.city-card`, `.class-grid`); varian memakai `--` (`.filters--compact`, `.page-heading--catalog`).
- Aturan khusus halaman dibatasi dengan class modifier supaya tidak merusak halaman lain. Contoh: `.stats` dipakai juga di dashboard, jadi gaya mungil di HP hanya berlaku untuk `.page-heading--catalog .stats`.
- Ukuran font di HP memakai `rem` kecil (0.58 sampai 0.85rem) untuk komponen padat; judul 1.15 sampai 1.3rem.
- Foto selalu `object-fit: cover` supaya di-crop rapi dan tidak gepeng.
- File berakhiran baris **CRLF** (Windows). Pertahankan saat mengedit lewat skrip.

---

## Desain responsive HP (keputusan yang sudah diambil)

**Halaman katalog (`/`)**
- Judul "Katalog Kost" di kiri, tiga statistik mungil (Gedung, Kota, Unit tersedia) di kanan, tanpa ikon. Paragraf pengantar di bawahnya selebar penuh. Dikendalikan class `page-heading--catalog`.
- Filter: tiga dropdown sebaris, tombol "Terapkan filter" dan "Reset" selebar baris dengan tulisan kecil (0.75rem, tinggi 36px). Dikendalikan `.filters--compact`.
- Kartu kota: 3 kolom, foto 80px menempel penuh ke tepi (tanpa padding), harga rentang dipecah dua baris (`.price-min` dan `.price-max`) agar tidak terpotong.
- Kartu gedung (setelah pilih kota): 1 kolom, foto 110px.

**Halaman detail gedung (`/katalog/gedung/{id}`)**
- Kartu info gedung dipadatkan: foto 130px, judul 1.15rem. Dikendalikan `.property-info`.
- Kartu kelas kamar: **1 kolom** (sempat dicoba 3 kolom, hasilnya terlalu sempit). Foto di atas 130px, fasilitas dan harga 0.8rem, baris unit: nomor kamar kiri, tombol "Pesan kamar" kanan. Dikendalikan `.class-grid`.

### Class penting yang harus ada di markup

| View | Elemen | Class wajib |
| --- | --- | --- |
| `catalog/index.php` | `<section>` header | `page-heading page-heading--catalog` |
| `catalog/index.php` | harga rentang kartu kota | `<span class="price-min">` dan `<span class="price-max">` |
| `catalog/property.php` | `<section>` info gedung | `card property-info` |
| `catalog/property.php` | `<div>` pembungkus kartu kelas | `stack class-grid` |

Kalau class di atas hilang, CSS HP **tidak akan terpakai** dan tampilan kembali seperti desktop.

---

## Cara menjalankan dan mengecek

1. Arahkan virtual host `kost-mandiri.test` ke folder `public/`.
2. Salin `.env.example` jadi `.env`, isi koneksi database, lalu impor `database/schema.sql`.
3. Jalankan `composer install` untuk PHPMailer.
4. Buka di Chrome, tekan F12, pilih mode responsif 360 x 800.

### Cache CSS

`layouts/main.php` memuat CSS dengan `?v=N` (saat ini `?v=4`). **Naikkan angka itu setiap kali CSS berubah** (ada dua tempat: `base.css` dan perulangan `extraCss`), atau tekan **Ctrl+Shift+R**. Kalau tampilan HP "tidak berubah", penyebab paling sering adalah cache atau file di folder lain.

---

## Panduan menulis prompt

Supaya hasil dari AI tepat dan tidak perlu bolak-balik:

1. **Lampirkan file yang dimaksud**, bukan hanya screenshot: view PHP-nya (mis. `catalog/property.php`) dan CSS terkait. Markup dan CSS harus cocok, jadi keduanya perlu dilihat.
2. **Sebutkan halaman dan lebar layar**, mis. "halaman detail gedung, 360px".
3. **Gunakan nama elemen yang ada di screenshot** ("kartu kelas", "tombol Reset", "angka statistik"), dan jelaskan hasil yang diinginkan (jumlah kolom, besar kecil, posisi).
4. **Minta perubahan terbatas pada HP** bila desktop sudah bagus: "hanya di `@media (max-width: 600px)`".
5. **Minta komentar fungsi** untuk aturan CSS baru, sesuai gaya file yang ada.
6. Setelah menerapkan, **hard refresh** dan kirim screenshot terbaru kalau masih ada yang aneh. Sertakan juga DevTools tab Styles bila perlu.

### Template prompt

```
Halaman: <katalog / detail gedung / login / ...>
Lebar: 360px (HP)
File terlampir: <view.php>, <file.css>

Yang ingin diubah:
- <elemen> : <hasil yang diinginkan>

Batasan:
- Desktop tidak boleh berubah
- Tambahkan komentar fungsi di setiap aturan baru
```

### Contoh prompt yang baik

> "Di halaman detail gedung (360px), kartu kelas dibuat 1 kolom dengan foto di atas. Tombol 'Pesan kamar' diperkecil. Desktop jangan berubah. Terlampir `property.php` dan `catalog.css`."

---

## Riwayat perubahan responsive HP

| Area | Perubahan | File |
| --- | --- | --- |
| Header katalog | Statistik mungil di kanan judul, tanpa ikon | `catalog.css`, `catalog/index.php` |
| Kartu kota | Harga dirapikan jadi 2 baris, font diperkecil | `catalog.css`, `catalog/index.php` |
| Kartu kota | Celah gambar dihapus (padding dibuang, foto `absolute` + `inset: 0`) | `catalog.css` |
| Filter | Tulisan, ikon, dan tinggi tombol Terapkan/Reset diperkecil | `filters.css` |
| Kartu gedung | Padding, foto, dan font diperkecil (daftar dan detail) | `catalog.css` |
| Kartu kelas | Dicoba 3 kolom, lalu dikembalikan ke 1 kolom | `catalog.css`, `catalog/property.php` |
| Lint | Tambah `line-clamp` standar di samping `-webkit-line-clamp` | `catalog.css` |
| Dokumentasi | Komentar fungsi di semua aturan CSS | semua `*.css` |
| Cache | `?v=1` dinaikkan jadi `?v=2` | `layouts/main.php` |

---

## Catatan dan batasan

- Perubahan tampilan dicek lewat render statis di Chrome headless, **belum selalu diuji langsung di server PHP**. Selalu verifikasi di browser.
- Modul transaksi admin bersifat hanya baca (middleware `readonly`); aksi persetujuan ada di sisi resepsionis.
- `app/views/partials/` masih kosong; potongan tampilan yang berulang masih ditulis langsung di tiap view.
