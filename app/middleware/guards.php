<?php
declare(strict_types=1);

/** Semua middleware berbentuk fn(array $params, array $args): void. Stop request dengan redirect/abort. */

function requireAuth(array $params = [], array $args = []): void
{
    $u = current_user();
    if (!$u) {
        flash('error', 'Silakan login terlebih dahulu.');
        redirect('/login');
    }
    // Cek ulang ke DB: akun nonaktif / dipindah gedung langsung berlaku
    $stmt = db()->prepare('SELECT id, name, email, role, property_id, is_active FROM users WHERE id = ?');
    $stmt->execute([$u['id']]);
    $row = $stmt->fetch();
    if (!$row || !(int) $row['is_active']) {
        logout_user();
        redirect('/login');
    }
    $_SESSION['user']['name']        = $row['name'];
    $_SESSION['user']['email']       = $row['email'];
    $_SESSION['user']['role']        = $row['role'];
    $_SESSION['user']['property_id'] = $row['property_id'] !== null ? (int) $row['property_id'] : null;
}

function requireRole(array $params = [], array $args = []): void
{
    $u = current_user();
    if (!$u || !in_array($u['role'], $args, true)) abort(403, 'Anda tidak memiliki akses ke halaman ini.');
}

/** Admin hanya boleh GET pada modul transaksi (booking, payment, kasir). */
function readOnlyTransactions(array $params = [], array $args = []): void
{
    $u = current_user();
    if ($u && $u['role'] === 'admin' && !in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
        abort(403, 'Admin bersifat read-only pada modul transaksi.');
    }
}

/** Resepsionis terkunci pada property_id akunnya (berlaku GET maupun POST). */
function checkPropertyAccess(array $params = [], array $args = []): void
{
    $u = current_user();
    if (!$u || $u['role'] !== 'receptionist' || empty($u['property_id'])) {
        abort(403, 'Akun resepsionis tidak terikat pada gedung.');
    }

    $joins = 'JOIN room_classes rc ON rc.id = ru.room_class_id';
    $queries = [
        'booking_id' => "SELECT rc.property_id FROM bookings b JOIN room_units ru ON ru.id = b.room_unit_id $joins WHERE b.id = ?",
        'payment_id' => "SELECT rc.property_id FROM payments p JOIN bookings b ON b.id = p.booking_id JOIN room_units ru ON ru.id = b.room_unit_id $joins WHERE p.id = ?",
        'unit_id'    => "SELECT rc.property_id FROM room_units ru $joins WHERE ru.id = ?",
    ];

    foreach ($queries as $key => $sql) {
        if (!isset($params[$key])) continue;
        $stmt = db()->prepare($sql);
        $stmt->execute([(int) $params[$key]]);
        $pid = $stmt->fetchColumn();
        // tidak ditemukan juga 403 agar keberadaan data gedung lain tidak bocor
        if ($pid === false || (int) $pid !== $u['property_id']) abort(403, 'Data bukan milik gedung Anda.');
    }

    foreach ([$params['property_id'] ?? null, $_GET['property_id'] ?? null, $_POST['property_id'] ?? null] as $pid) {
        if ($pid !== null && (int) $pid !== $u['property_id']) abort(403, 'Gedung tidak sesuai.');
    }
}

/** Dipakai di controller untuk validasi manual. */
function authorizeBuilding(int $propertyId): void
{
    $u = current_user();
    if ($u && $u['role'] === 'receptionist' && $u['property_id'] !== $propertyId) abort(403);
}

function ensureStaffSelected(array $params = [], array $args = []): void
{
    $user = current_user();
    $staffId = filter_var($_SESSION['active_staff_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$user || $staffId === false || $staffId === null || empty($user['property_id'])) {
        unset($_SESSION['active_staff_id'], $_SESSION['active_staff_name']);
        redirect('/resepsionis/pilih-petugas');
    }

    $stmt = db()->prepare(
        'SELECT id, name FROM receptionist_staff WHERE id = ? AND property_id = ? AND is_active = 1 LIMIT 1'
    );
    $stmt->execute([$staffId, $user['property_id']]);
    $staff = $stmt->fetch();
    if (!$staff) {
        unset($_SESSION['active_staff_id']);
        unset($_SESSION['active_staff_name']);
        redirect('/resepsionis/pilih-petugas');
    }
    $_SESSION['active_staff_name'] = $staff['name'];
}

function run_middleware(string $spec, array $params): void
{
    [$name, $argStr] = array_pad(explode(':', $spec, 2), 2, null);
    $args = $argStr !== null ? explode(',', $argStr) : [];
    $map = [
        'auth'     => 'requireAuth',
        'role'     => 'requireRole',
        'property' => 'checkPropertyAccess',
        'readonly' => 'readOnlyTransactions',
        'staff'    => 'ensureStaffSelected',
    ];
    if (!isset($map[$name])) throw new RuntimeException("Middleware '$name' tidak dikenal.");
    $map[$name]($params, $args);
}
