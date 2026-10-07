# 🏢 Platform Web Booking & Manajemen Kost Mandiri (Multi-Lokasi)

Aplikasi web *mobile-first* untuk pengelolaan portofolio kost di beberapa kota, reservasi *online* dengan DP/Pelunasan, pencatatan *walk-in/offline*, serta integrasi kode unik PNR untuk verifikasi cepat via WhatsApp & Dashboard Admin.

---

## 🛠️ Tech Stack & Requirements

* **Language:** PHP ≥ 8.0 (PHP Native / Tanpa Framework)
* **Database:** MySQL / MariaDB
* **Mail Delivery:** PHPMailer / SMTP (SendGrid, Mailgun, dll.)
* **Authentication:** Native Session + Google OAuth 2.0 (Composer)
* **Cron Job:** CLI PHP Script (Expire Handler & Billing Reminder)

---

## ✨ Fitur Utama (MVP)

* 🏬 **Multi-Location & Class Hierarchy:** Pengelolaan hirarki Kota ➔ Gedung ➔ Kelas Kamar ➔ Unit Kamar.
* 🎫 **PNR Unik 6-Karakter:** Kode pelacakan transaksi acak aman tanpa karakter ambigu untuk verifikasi cepat.
* ⏱️ **1-Hour Auto-Cancel Hold Timer:** Mencegah *double booking* dengan pemodelan transaksi `SELECT ... FOR UPDATE` dan cron pembatalan otomatis.
* 💳 **Skema Pembayaran Fleksibel:** Mendukung DP 50% atau Lunas 100% via QRIS statis/dinamis.
* 🧾 **Kasir Offline / Walk-In:** Pencatatan langsung oleh Admin untuk penyewa *walk-in*.
* 📲 **WhatsApp Redirect:** Integrasi tombol konfirmasi/pembatalan otomatis terformat dengan kode PNR.
* 📧 **E-Kuitansi & Notifikasi Email:** Pengiriman otomatis bukti bayar & notifikasi status pembatalan.

---

## 📁 Struktur Direktori Singkat

```text
├── app/
│   ├── config.php         # Konfigurasi database & service
│   ├── controllers/        # Logika aplikasi per modul
│   ├── helpers.php        # Helper CSRF, Sanitisasi, & Session
│   ├── pnr.php            # Generator PNR Unik
│   └── views/             # Template UI (Layout & Modul)
├── cron/
│   ├── expire_bookings.php # Cron pembatalan timer 1 jam
│   └── billing_reminders.php # Cron pengingat tagihan bulanan
├── public/                # Document root
│   └── index.php          # Front controller & router sederhana
└── storage/proofs/        # Penyimpanan bukti transfer (Di luar webroot)
