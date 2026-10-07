<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

$app = dirname(__DIR__) . '/app';
require $app . '/helpers.php';
require $app . '/db.php';
require $app . '/auth.php';
require $app . '/pnr.php';
require $app . '/controllers/auth.php';
require $app . '/controllers/catalog.php';
require $app . '/controllers/booking.php';

$path   = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/', '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($path === '/')                                    page_catalog();
    elseif (preg_match('#^/kost/(\d+)$#', $path, $m))     page_property((int)$m[1]);
    elseif ($path === '/register')                        page_register();
    elseif ($path === '/login')                           page_login();
    elseif ($path === '/logout' && $method === 'POST')    page_logout();
    elseif ($path === '/pesan')                           page_start_checkout();
    elseif ($path === '/checkout')                        page_checkout();
    elseif ($path === '/riwayat')                         page_history();
    elseif (preg_match('#^/booking/([A-Z0-9]{6})$#', $path, $m)) page_booking_show($m[1]);
    else {
        http_response_code(404);
        view('404', ['title' => 'Halaman tidak ditemukan']);
    }
} catch (Throwable $ex) {
    http_response_code(500);
    echo '<pre style="padding:16px">Terjadi kesalahan: ' . e($ex->getMessage()) . '</pre>';
}