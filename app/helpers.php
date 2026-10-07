<?php
function config(string $key)
{
    static $c = null;
    if ($c === null) $c = require __DIR__ . '/config.php';
    return $c[$key] ?? null;
}

// e() = escape, mencegah XSS. Semua data yang ditampilkan di HTML wajib lewat sini.
function e($text): string
{
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

// Pesan sekali tampil. flash('teks') = simpan, flash() = baca lalu hapus.
function flash(?string $msg = null, string $type = 'success')
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// ----- CSRF -----
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function csrf_verify(): void
{
    $ok = isset($_POST['csrf'], $_SESSION['csrf'])
          && hash_equals($_SESSION['csrf'], $_POST['csrf']);
    if (!$ok) {
        http_response_code(419);
        exit('Sesi form tidak valid. Silakan kembali dan coba lagi.');
    }
}

function rupiah($angka): string
{
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

// Memuat halaman di dalam header + footer
function view(string $file, array $data = []): void
{
    extract($data);   // ['title'=>'Hai'] menjadi variabel $title
    require __DIR__ . '/views/layout/header.php';
    require __DIR__ . '/views/' . $file . '.php';
    require __DIR__ . '/views/layout/footer.php';
}