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
