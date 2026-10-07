<?php
function after_login_url(): string
{
    return !empty($_SESSION['checkout_intent']) ? '/checkout' : '/';
}

function page_register(): void
{
    if (current_user()) redirect('/');

    $errors = [];
    $old    = ['name' => '', 'email' => '', 'phone' => ''];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();
        $old['name']  = trim($_POST['name'] ?? '');
        $old['email'] = strtolower(trim($_POST['email'] ?? ''));
        $old['phone'] = trim($_POST['phone'] ?? '');
        $password     = $_POST['password'] ?? '';
        $confirm      = $_POST['password_confirmation'] ?? '';

        if ($old['name'] === '' || mb_strlen($old['name']) > 255)  $errors['name'] = 'Nama wajib diisi (maks 255 karakter).';
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))     $errors['email'] = 'Format email tidak valid.';
        if ($old['phone'] === '' || mb_strlen($old['phone']) > 20) $errors['phone'] = 'No. HP wajib diisi (maks 20 karakter).';
        if (strlen($password) < 8)                                  $errors['password'] = 'Password minimal 8 karakter.';
        if ($password !== $confirm)                                 $errors['password_confirmation'] = 'Konfirmasi password tidak sama.';

        if (!isset($errors['email'])) {
            $st = db()->prepare('SELECT 1 FROM users WHERE email = ?');
            $st->execute([$old['email']]);
            if ($st->fetchColumn()) $errors['email'] = 'Email sudah terdaftar.';
        }

        if (!$errors) {
            $st = db()->prepare(
                "INSERT INTO users (name, email, password, phone, role, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 'tenant', NOW(), NOW())"
            );
            $st->execute([$old['name'], $old['email'], password_hash($password, PASSWORD_BCRYPT), $old['phone']]);
            login_user((int)db()->lastInsertId());
            flash('Registrasi berhasil. Selamat datang!');
            redirect(after_login_url());
        }
    }
    view('auth/register', ['title' => 'Daftar', 'errors' => $errors, 'old' => $old]);
}

function page_login(): void
{
    if (current_user()) redirect('/');

    $error = null;
    $email = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        $st = db()->prepare('SELECT id, password FROM users WHERE email = ?');
        $st->execute([$email]);
        $u = $st->fetch();

        if ($u && $u['password'] && password_verify($password, $u['password'])) {
            login_user((int)$u['id']);
            redirect(after_login_url());
        }
        $error = 'Email atau password salah.';
    }
    view('auth/login', ['title' => 'Masuk', 'error' => $error, 'email' => $email]);
}

function page_logout(): void
{
    csrf_verify();
    logout_user();
    session_start();
    session_regenerate_id(true);
    flash('Anda sudah keluar.');
    redirect('/');
}