<?php
function generate_unique_pnr(PDO $pdo): string
{
    $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';   // tanpa 0, 1, O, I
    $max   = strlen($chars) - 1;
    $stmt  = $pdo->prepare('SELECT 1 FROM bookings WHERE pnr_code = ? LIMIT 1');
    do {
        $pnr = '';
        for ($i = 0; $i < 6; $i++) {
            $pnr .= $chars[random_int(0, $max)];
        }
        $stmt->execute([$pnr]);
    } while ($stmt->fetchColumn());   // ulangi kalau sudah dipakai
    return $pnr;
}