<?php
function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) return null;
    static $user = null;
    if ($user === null) {
        $st = db()->prepare('SELECT id, name, email, phone, role FROM users WHERE id = ?');
        $st->execute([$_SESSION['user_id']]);
        $user = $st->fetch() ?: null;
    }
    return $user;
}

function login_user(int $id): void
{
    session_regenerate_id(true);   // ganti ID sesi, mencegah session fixation
    $_SESSION['user_id'] = $id;
}

function logout_user(): void
{
    $_SESSION = [];
    session_destroy();
}

function require_login(): void
{
    if (!current_user()) {
        flash('Silakan login terlebih dahulu.', 'error');
        redirect('/login');
    }
}

function require_admin(): void
{
    require_login();
    if (current_user()['role'] !== 'admin') {
        http_response_code(403);
        exit('Akses ditolak.');
    }
}