<?php
declare(strict_types=1);

function url(string $path = ''): string
{
    return rtrim((string) env('APP_URL', ''), '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    $target = preg_match('#^https?://#', $path) ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

function view(string $name, array $data = [], string $layout = 'layouts/main'): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require BASE_PATH . "/app/views/$name.php";
    $content = ob_get_clean();
    require BASE_PATH . "/app/views/$layout.php";
}

function abort(int $code, string $message = ''): never
{
    $titles = [
        403 => 'Akses ditolak', 404 => 'Halaman tidak ditemukan',
        405 => 'Metode tidak diizinkan', 409 => 'Konflik data',
        419 => 'Sesi tidak valid', 422 => 'Data tidak valid', 500 => 'Kesalahan server',
    ];
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($code);
    view('errors/error', ['code' => $code, 'title' => $titles[$code] ?? 'Error', 'message' => $message]);
    exit;
}

/** Flash message: flash('error','teks') untuk set, flash('error') untuk ambil. */
function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) { $_SESSION['_flash'][$key] = $message; return null; }
    $v = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $v;
}
// Jenis kost: kode di database => teks yang tampil di website
function kost_types(): array
{
    return ['putra' => 'Kost Putra', 'putri' => 'Kost Putri', 'bebas' => 'Kost Bebas'];
}

function kost_type_label(?string $type): string
{
    return kost_types()[$type] ?? '';
}