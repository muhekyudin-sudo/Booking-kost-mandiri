<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$basePath = dirname(__DIR__);
$lock = null;
$locked = false;
$exitCode = 0;

try {
    require $basePath . '/app/bootstrap.php';

    $lock = fopen(BASE_PATH . '/storage/logs/expire_bookings.lock', 'c');
    if ($lock === false) {
        throw new RuntimeException('Could not open the booking expiration lock file.');
    }
    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        fwrite(STDOUT, "Booking expiration is already running; skipped.\n");
    } else {
        $locked = true;
        $expiredCount = BookingService::expirePending();
        fwrite(
            STDOUT,
            sprintf("%s Expired bookings: %d\n", date(DATE_ATOM), $expiredCount)
        );
    }
} catch (Throwable $e) {
    $message = 'Booking expiration cron failed: ' . $e->getMessage();
    error_log($message);
    fwrite(STDERR, $message . PHP_EOL);
    $exitCode = 1;
} finally {
    if (is_resource($lock)) {
        if ($locked) flock($lock, LOCK_UN);
        fclose($lock);
    }
}

exit($exitCode);
