<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

final class EmailService
{
    public static function sendReceipt(int $bookingId): void
    {
        $booking = self::bookingDetails($bookingId);
        $body = implode("\n", [
            'Kuitansi pembayaran Kost Mandiri',
            '',
            'Nama penyewa: ' . $booking['tenant_name'],
            'PNR: ' . $booking['pnr_code'],
            'Invoice: ' . $booking['invoice_code'],
            'Gedung: ' . $booking['property_name'],
            'Kamar: ' . $booking['room_class_name'] . ' - ' . $booking['room_number'],
            'Periode: ' . $booking['start_date'] . ' s.d. ' . $booking['end_date'],
            'Total sewa: Rp ' . self::rupiah($booking['total_amount']),
            'Pembayaran diterima: Rp ' . self::rupiah($booking['payment_amount']),
            'Metode pembayaran: ' . ($booking['payment_method'] === 'cash' ? 'Tunai' : 'QRIS'),
            'Status: ' . ($booking['remaining_amount'] > 0 ? 'DP diterima' : 'Lunas'),
            '',
            'Simpan email ini sebagai bukti pembayaran.',
        ]);
        self::send(
            (string) $booking['tenant_email'],
            (string) $booking['tenant_name'],
            'E-Kuitansi booking ' . $booking['pnr_code'],
            $body
        );
    }

    public static function sendCancellation(int $bookingId, string $reason): void
    {
        $booking = self::bookingDetails($bookingId);
        $body = implode("\n", [
            'Informasi pembatalan booking Kost Mandiri',
            '',
            'Nama penyewa: ' . $booking['tenant_name'],
            'PNR: ' . $booking['pnr_code'],
            'Gedung: ' . $booking['property_name'],
            'Kamar: ' . $booking['room_class_name'] . ' - ' . $booking['room_number'],
            'Alasan: ' . ($reason !== '' ? $reason : 'Booking dibatalkan oleh resepsionis.'),
            '',
            'Pengembalian dana (jika ada) diproses secara manual di luar sistem.',
        ]);
        self::send(
            (string) $booking['tenant_email'],
            (string) $booking['tenant_name'],
            'Booking ' . $booking['pnr_code'] . ' dibatalkan',
            $body
        );
    }

    public static function sendPasswordReset(string $email, string $name, string $link, int $minutes): void
    {
        $body = implode("\n", [
            'Halo ' . self::oneLine($name) . ',',
            '',
            'Kami menerima permintaan untuk mengatur ulang password akun Kost Mandiri Anda.',
            '',
            'Klik link berikut untuk membuat password baru (berlaku ' . $minutes . ' menit dan hanya bisa dipakai satu kali):',
            $link,
            '',
            'Jika Anda tidak merasa meminta ini, abaikan email ini. Password Anda tidak akan berubah.',
            '',
            'Kost Mandiri',
        ]);
        self::send($email, self::oneLine($name), 'Reset password akun Kost Mandiri', $body);
    }

    public static function sendPasswordChanged(string $email, string $name, string $when, string $ip): void
    {
        $whatsapp = owner_whatsapp_url('Halo, password akun Kost Mandiri saya diubah tanpa sepengetahuan saya.');
        $body = implode("\n", [
            'Halo ' . self::oneLine($name) . ',',
            '',
            'Password akun Kost Mandiri Anda baru saja diubah.',
            'Waktu: ' . $when,
            'Alamat IP: ' . $ip,
            '',
            'Jika ini memang Anda, abaikan email ini.',
            'Jika BUKAN Anda, segera hubungi pemilik kost' . ($whatsapp !== null ? ': ' . $whatsapp : '.'),
            '',
            'Kost Mandiri',
        ]);
        self::send($email, self::oneLine($name), 'Password akun Kost Mandiri Anda telah diubah', $body);
    }

    public static function notifyNewProof(int $paymentId): void
    {
        $stmt = db()->prepare(
            "SELECT b.pnr_code, p.name AS property_name, u.email, u.name
             FROM payments pay
             JOIN bookings b ON b.id = pay.booking_id
             JOIN room_units ru ON ru.id = b.room_unit_id
             JOIN room_classes rc ON rc.id = ru.room_class_id
             JOIN properties p ON p.id = rc.property_id
             JOIN users u ON u.property_id = p.id AND u.role = 'receptionist'
             WHERE pay.id = ? AND u.is_active = 1
             LIMIT 1"
        );
        $stmt->execute([$paymentId]);
        $recipient = $stmt->fetch();
        if (!$recipient) {
            throw new RuntimeException('Resepsionis aktif untuk notifikasi bukti pembayaran tidak ditemukan.');
        }
        self::send(
            (string) $recipient['email'],
            (string) $recipient['name'],
            'Bukti pembayaran baru menunggu verifikasi - ' . $recipient['pnr_code'],
            "Bukti pembayaran baru menunggu verifikasi.\n\n"
                . 'PNR: ' . $recipient['pnr_code'] . "\n"
                . 'Gedung: ' . $recipient['property_name'] . "\n"
                . 'Buka dashboard resepsionis untuk memeriksa bukti dan memproses pembayaran.'
        );
    }

    private static function bookingDetails(int $bookingId): array
    {
        $stmt = db()->prepare(
            "SELECT b.pnr_code, b.start_date, b.end_date, b.total_amount, b.remaining_amount,
                    u.name AS tenant_name, u.email AS tenant_email, p.name AS property_name,
                    rc.name AS room_class_name, ru.room_number,
                    pay.invoice_code, pay.amount AS payment_amount, pay.payment_method
             FROM bookings b
             JOIN users u ON u.id = b.user_id
             JOIN room_units ru ON ru.id = b.room_unit_id
             JOIN room_classes rc ON rc.id = ru.room_class_id
             JOIN properties p ON p.id = rc.property_id
             JOIN payments pay ON pay.booking_id = b.id
             WHERE b.id = ?
             ORDER BY pay.id DESC LIMIT 1"
        );
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();
        if (!$booking) {
            throw new RuntimeException('Data booking untuk email tidak ditemukan.');
        }
        return $booking;
    }

    private static function send(string $email, string $name, string $subject, string $body): void
    {
        $host = trim((string) env('SMTP_HOST', ''));
        $username = (string) env('SMTP_USER', '');
        $password = (string) env('SMTP_PASS', '');
        $from = trim((string) env('SMTP_FROM', ''));
        $port = filter_var(env('SMTP_PORT', 587), FILTER_VALIDATE_INT);
        $encryption = strtolower(trim((string) env('SMTP_ENCRYPTION', 'tls')));
        if ($host === '' || $username === '' || $password === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)
            || $port === false || $port < 1 || $port > 65535
            || !in_array($encryption, ['tls', 'ssl'], true)) {
            throw new RuntimeException('Konfigurasi SMTP belum lengkap atau tidak valid.');
        }
        if (!class_exists(PHPMailer::class)) {
            throw new RuntimeException('PHPMailer belum terpasang. Jalankan composer install.');
        }

        $mailer = new PHPMailer(true);
        try {
            $mailer->isSMTP();
            $mailer->Host = $host;
            $mailer->SMTPAuth = true;
            $mailer->Username = $username;
            $mailer->Password = $password;
            $mailer->Port = $port;
            $mailer->SMTPSecure = $encryption === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $mailer->CharSet = 'UTF-8';
            $mailer->Timeout = 10;
            $mailer->setFrom($from, (string) env('SMTP_FROM_NAME', 'Kost Mandiri'));
            $mailer->addAddress($email, $name);
            $mailer->Subject = $subject;
            $mailer->Body = $body;
            $mailer->send();
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            throw new RuntimeException('Email tidak dapat dikirim melalui SMTP.', 0, $e);
        }
    }

    /** Nama dipakai di kepala email; buang pindah baris supaya tidak bisa menyisipkan header lain. */
    private static function oneLine(string $text): string
    {
        return trim((string) preg_replace('/[\r\n]+/', ' ', $text));
    }

    private static function rupiah(string|int|float $amount): string
    {
        return number_format((float) $amount, 0, ',', '.');
    }
}
