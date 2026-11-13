# Kost Mandiri

Aplikasi web untuk katalog kost, pemesanan kamar, dan pengelolaan transaksi. Penyewa dapat mencari gedung dan kamar, membuat booking online dengan pembayaran QRIS, serta melihat status booking. Admin mengelola data kost, sedangkan resepsionis menangani booking, verifikasi pembayaran, dan transaksi tunai.

## Fitur

- Katalog berdasarkan provinsi dan kota, dengan filter kelas kamar dan jenis kost.
- Detail gedung, kelas kamar, unit tersedia, fasilitas, dan harga berdasarkan durasi.
- Registrasi dan login penyewa; pengelolaan profil dan permintaan reset password melalui email.
- Booking online dengan pilihan harian, mingguan, bulanan, atau tahunan. Pembayaran DP 50% tersedia selain untuk sewa harian.
- Pembayaran online melalui QRIS, unggah bukti pembayaran, dan verifikasi oleh resepsionis.
- Booking walk-in dan pencatatan pembayaran tunai oleh resepsionis.
- Dashboard admin dan resepsionis, serta email kuitansi dan pemberitahuan transaksi.
- Booking online yang belum dibayar kedaluwarsa setelah satu jam.

## Teknologi dan kebutuhan

- PHP 8.1 atau lebih baru; ekstensi `pdo_mysql`, `mbstring`, dan `fileinfo`.
- MySQL/MariaDB.
- Apache dengan `mod_rewrite` aktif, atau web server lain yang mengarahkan request ke `public/index.php`.
- Composer untuk memasang PHPMailer.

Frontend menggunakan PHP, HTML, CSS, dan JavaScript tanpa framework CSS/JavaScript. Ikon Feather dan font Plus Jakarta Sans dimuat dari CDN.

## Menjalankan secara lokal

1. Arahkan document root virtual host ke folder `public/`. Pastikan rewrite URL aktif agar rute aplikasi selain `/` diarahkan ke `public/index.php`.
2. Salin `.env.example` menjadi `.env`, lalu isi konfigurasi database dan aplikasi.
3. Jalankan `composer install`.
4. Buat database MySQL/MariaDB dan siapkan skema yang sesuai dengan kode aplikasi.
5. Pastikan folder `storage/logs/` dan `storage/private/` dapat ditulis oleh PHP. Bukti pembayaran disimpan secara privat di `storage/private/bukti/`.
6. Buka alamat lokal aplikasi di browser.

### Penting: skema database

`database/schema.sql` adalah dump dasar dan **belum sepenuhnya sesuai dengan kode saat ini**. Mengimpor file tersebut saja belum cukup untuk menjalankan aplikasi. Kode katalog dan admin saat ini juga menggunakan tabel/kolom yang tidak dibuat oleh dump tersebut, termasuk tabel `provinces`, `cities.province_id`, `provinces.image`, serta kolom `properties.kost_type`, `properties.image`, dan `room_classes.image`.

Fitur reset password memerlukan tabel `password_resets`; definisinya tersedia di `database/migrations/create_password_resets.sql`. Migration `database/migrations/add_city_image.sql` ditujukan untuk database lama yang belum memiliki kolom `cities.image`; kolom itu sudah ada di dump dasar saat ini, jadi jangan jalankan migration tersebut setelah mengimpor dump tanpa memeriksa skema terlebih dahulu. Siapkan atau migrasikan database ke skema yang digunakan aplikasi sebelum menjalankan situs.

## Konfigurasi `.env`

| Variabel | Kegunaan |
| --- | --- |
| `APP_ENV` | Lingkungan aplikasi; gunakan `production` untuk lingkungan produksi. |
| `APP_DEBUG` | Menampilkan detail error ketika bernilai `true`; matikan di produksi. |
| `APP_URL` | URL dasar aplikasi, digunakan untuk link dan redirect. |
| `APP_PUBLIC_URL` | URL publik untuk link reset password dalam email; wajib HTTPS ketika `APP_ENV=production`. |
| `APP_TIMEZONE` | Zona waktu aplikasi; default `Asia/Jakarta`. |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | Koneksi MySQL/MariaDB. |
| `QRIS_IMAGE_URL` | Path lokal di bawah `/assets/` atau URL HTTPS untuk gambar QRIS. |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM`, `SMTP_FROM_NAME` | Pengiriman email reset password, notifikasi, pembatalan, dan kuitansi. |
| `WA_OWNER_NUMBER` | Nomor WhatsApp pengelola untuk tautan bantuan. |

Lihat `.env.example` untuk nilai awal. Jangan masukkan kredensial produksi ke repositori.

## Peran dan rute utama

| Peran | Rute | Akses |
| --- | --- | --- |
| Pengunjung | `/`, `/katalog/gedung/{property_id}`, `/login`, `/register`, `/lupa-password` | Melihat katalog, masuk/daftar, dan meminta reset password. |
| Penyewa (`tenant`) | `/tenant`, `/tenant/bookings` | Melihat dashboard dan booking, melihat detail, serta mengirim bukti pembayaran. |
| Resepsionis (`receptionist`) | `/resepsionis` | Memilih petugas jaga, mengelola booking gedungnya, memverifikasi pembayaran, membatalkan booking, dan mencatat transaksi kasir. |
| Admin (`admin`) | `/admin` | Mengelola master data dan melihat transaksi booking. Transaksi admin hanya-baca; verifikasi dilakukan resepsionis. |

Rute katalog menerima filter `city_id`, `province_id`, `room_class`, dan `kost_type`. Rute checkout adalah `/booking/checkout/{unit_id}`. Rute bertanda peran memerlukan autentikasi dan dibatasi middleware sesuai perannya.

## Kedaluwarsa booking

Booking online menahan unit selama satu jam sambil menunggu pembayaran. Proses kedaluwarsa juga dijalankan ketika beberapa halaman aplikasi dibuka. Untuk pemrosesan terjadwal, jalankan `php cron/expire_bookings.php` melalui CLI, misalnya sebagai tugas terjadwal berkala. Skrip ini hanya dapat dijalankan dari CLI dan mencatat error ke log aplikasi.

## Struktur proyek

```text
app/
  controllers/   Pengendali katalog, autentikasi, booking, akun, admin, dan resepsionis
  core/          Router
  helpers/       Helper HTTP, autentikasi, keamanan, validasi, dan pesan
  middleware/    Pemeriksaan autentikasi, peran, gedung, petugas, dan akses baca
  services/      Logika booking dan pengiriman email
  views/         Template PHP untuk halaman aplikasi
config/          Konfigurasi aplikasi dan koneksi database
cron/            Tugas pemeliharaan booking kedaluwarsa
database/        Dump dasar dan migration SQL
public/          Document root, front controller, CSS, JavaScript, dan gambar
routes/web.php   Definisi rute GET/POST
storage/         Log aplikasi dan file privat
```

Stylesheet halaman berada di `public/assets/css/`; skrip halaman berada di `public/assets/js/`. View memakai layout bersama `app/views/layouts/main.php`.
