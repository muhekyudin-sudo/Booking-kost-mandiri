<?php
declare(strict_types=1);

// Pengingat pelunasan sisa DP lewat email.
// Jalankan sekali sehari:  php cron/send_settlement_reminders.php
// Default mengirim untuk jatuh tempo H-3, H-1, dan hari H. Bisa diganti, mis. H-5 dan H-2:
//   php cron/send_settlement_reminders.php 5 2

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$basePath = dirname(__DIR__);
$exitCode = 0;

try {
    require $basePath . '/app/bootstrap.php';

    $days = array_values(array_filter(
        array_map('intval', array_slice($argv, 1)),
        static fn (int $d): bool => $d >= 0
    ));
    if ($days === []) {
        $days = [3, 1, 0];
    }

    // Booking aktif yang masih punya sisa dan jatuh tempo tepat pada tanggal tujuan.
    // Dilewati bila penyewa sudah mengirim bukti pelunasan yang menunggu verifikasi.
    $stmt = db()->prepare(
        "SELECT b.id, b.pnr_code
         FROM bookings b
         WHERE b.status = 'active' AND b.remaining_amount > 0 AND b.remaining_due_date = ?
           AND NOT EXISTS (
               SELECT 1 FROM payments p
               WHERE p.booking_id = b.id AND p.payment_type = 'pelunasan_dp'
                 AND p.status = 'pending' AND p.proof_file IS NOT NULL
           )"
    );

    $sent = 0;
    foreach ($days as $daysLeft) {
        $dueDate = date('Y-m-d', strtotime('+' . $daysLeft . ' days'));
        $stmt->execute([$dueDate]);
        foreach ($stmt->fetchAll() as $booking) {
            try {
                EmailService::sendSettlementReminder((int) $booking['id'], $daysLeft);
                $sent++;
            } catch (RuntimeException|PDOException $e) {
                $message = 'Pengingat pelunasan gagal untuk PNR ' . $booking['pnr_code'] . ': ' . $e->getMessage();
                error_log($message);
                fwrite(STDERR, $message . PHP_EOL);
            }
        }
    }
    fwrite(STDOUT, sprintf("%s Pengingat pelunasan terkirim: %d\n", date(DATE_ATOM), $sent));
} catch (Throwable $e) {
    $message = 'Pengingat pelunasan gagal: ' . $e->getMessage();
    error_log($message);
    fwrite(STDERR, $message . PHP_EOL);
    $exitCode = 1;
}

exit($exitCode);
