<?php
declare(strict_types=1);

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function login_user(array $row): void
{
    session_regenerate_id(true);
    unset($_SESSION['active_staff_id']);
    $_SESSION['user'] = [
        'id'          => (int) $row['id'],
        'name'        => $row['name'],
        'email'       => $row['email'],
        'role'        => $row['role'],
        'property_id' => $row['property_id'] !== null ? (int) $row['property_id'] : null,
    ];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function home_for_role(string $role): string
{
    // Tenant tanpa booking tertunda kembali ke katalog.
    return match ($role) {
        'admin'        => '/admin',
        'receptionist' => '/resepsionis/pilih-petugas',
        default        => '/',
    };
}
