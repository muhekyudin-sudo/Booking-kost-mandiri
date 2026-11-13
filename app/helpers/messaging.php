<?php
declare(strict_types=1);

function owner_whatsapp_url(string $message): ?string
{
    $number = preg_replace('/\D+/', '', (string) env('WA_OWNER_NUMBER', ''));
    if (!is_string($number) || strlen($number) < 8 || strlen($number) > 15) {
        return null;
    }
    return 'https://wa.me/' . $number . '?text=' . rawurlencode($message);
}

/** Ubah nomor HP penyewa ke format internasional untuk wa.me (0812... menjadi 62812...). */
function normalize_phone_for_whatsapp(?string $phone): ?string
{
    $digits = preg_replace('/\D+/', '', (string) $phone);
    if (!is_string($digits) || $digits === '') {
        return null;
    }
    if (str_starts_with($digits, '0')) {
        $digits = '62' . substr($digits, 1);
    } elseif (str_starts_with($digits, '8')) {
        $digits = '62' . $digits;
    }
    if (strlen($digits) < 8 || strlen($digits) > 15) {
        return null;
    }
    return $digits;
}

/** Link wa.me ke penyewa dengan pesan terisi; pengirim tetap menekan Kirim sendiri. */
function tenant_whatsapp_url(?string $phone, string $message): ?string
{
    $number = normalize_phone_for_whatsapp($phone);
    return $number === null ? null : 'https://wa.me/' . $number . '?text=' . rawurlencode($message);
}
