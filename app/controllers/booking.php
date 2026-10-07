<?php
const STATUS_LABEL = [
    'pending_payment'  => 'Menunggu Pembayaran',
    'waiting_approval' => 'Menunggu Verifikasi',
    'active'           => 'Aktif',
    'completed'        => 'Selesai',
    'cancelled'        => 'Dibatalkan',
    'expired'          => 'Kedaluwarsa',
];
const DURATION_LABEL = ['daily' => 'Harian', 'weekly' => 'Mingguan', 'monthly' => 'Bulanan', 'yearly' => 'Tahunan'];
const DURATION_UNIT  = ['daily' => 'day', 'weekly' => 'week', 'monthly' => 'month', 'yearly' => 'year'];

function fetch_unit_detail(int $unitId): ?array
{
    $st = db()->prepare(
        'SELECT ru.id AS unit_id, ru.room_number, ru.status,
                rc.id AS class_id, rc.name AS class_name, rc.facilities,
                p.name AS property_name, c.name AS city_name,
                pm.daily_price, pm.weekly_price, pm.monthly_price, pm.yearly_price
           FROM room_units ru
           JOIN room_classes rc ON rc.id = ru.room_class_id
           JOIN properties p    ON p.id = rc.property_id
           JOIN cities c        ON c.id = p.city_id
           LEFT JOIN price_matrices pm ON pm.room_class_id = rc.id
          WHERE ru.id = ?'
    );
    $st->execute([$unitId]);
    return $st->fetch() ?: null;
}

function page_start_checkout(): void
{
    $unitId = (int)($_GET['unit_id'] ?? 0);
    $unit   = fetch_unit_detail($unitId);
    if (!$unit || $unit['status'] !== 'available') {
        flash('Kamar tidak tersedia.', 'error');
        redirect('/');
    }

    $_SESSION['checkout_intent'] = ['unit_id' => $unitId];

    if (!current_user()) {
        flash('Silakan login atau daftar untuk melanjutkan pemesanan.', 'error');
        redirect('/login');
    }
    redirect('/checkout');
}

function page_checkout(): void
{
    require_login();
    $unitId = (int)($_SESSION['checkout_intent']['unit_id'] ?? 0);
    $unit   = $unitId ? fetch_unit_detail($unitId) : null;
    if (!$unit) {
        flash('Pilih kamar terlebih dahulu.', 'error');
        redirect('/');
    }

    $prices = [];
    foreach (['daily', 'weekly', 'monthly', 'yearly'] as $d) {
        if ($unit[$d . '_price'] !== null) $prices[$d] = (float)$unit[$d . '_price'];
    }

    $errors = [];
    $old = ['duration_type' => 'monthly', 'duration_value' => 1, 'payment_scheme' => 'dp', 'start_date' => date('Y-m-d')];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();
        $old['duration_type']  = $_POST['duration_type'] ?? '';
        $old['duration_value'] = (int)($_POST['duration_value'] ?? 0);
        $old['payment_scheme'] = $_POST['payment_scheme'] ?? '';
        $old['start_date']     = $_POST['start_date'] ?? '';

        if (!isset($prices[$old['duration_type']]))                    $errors[] = 'Durasi tidak valid.';
        if ($old['duration_value'] < 1 || $old['duration_value'] > 36) $errors[] = 'Jumlah durasi harus 1 - 36.';
        if (!in_array($old['payment_scheme'], ['full', 'dp'], true))   $errors[] = 'Skema bayar tidak valid.';
        $start = DateTimeImmutable::createFromFormat('Y-m-d', $old['start_date']);
        if (!$start || $old['start_date'] !== $start->format('Y-m-d') || $start < new DateTimeImmutable('today')) {
            $errors[] = 'Tanggal mulai tidak valid (tidak boleh kemarin).';
        }

        if (!$errors) {
            $result = create_online_booking($unitId, (int)current_user()['id'], $old, $prices, $start);
            if ($result['ok']) {
                unset($_SESSION['checkout_intent']);
                redirect('/booking/' . $result['pnr']);
            }
            $errors[] = $result['error'];
        }
    }

    view('booking/checkout', ['title' => 'Checkout', 'unit' => $unit, 'prices' => $prices, 'old' => $old, 'errors' => $errors]);
}

// Inti sistem: transaksi database
function create_online_booking(int $unitId, int $userId, array $in, array $prices, DateTimeImmutable $start): array
{
    $pdo = db();

    $total = $prices[$in['duration_type']] * $in['duration_value'];
    $end   = $start->modify('+' . $in['duration_value'] . ' ' . DURATION_UNIT[$in['duration_type']]);
    $isDp  = $in['payment_scheme'] === 'dp';
    $toPay = $isDp ? round($total / 2, 2) : $total;

    $pdo->beginTransaction();
    try {
        // FOR UPDATE: kunci baris kamar, mencegah double booking
        $st = $pdo->prepare('SELECT status FROM room_units WHERE id = ? FOR UPDATE');
        $st->execute([$unitId]);
        $status = $st->fetchColumn();
        if ($status !== 'available') {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Maaf, kamar baru saja dipesan orang lain.'];
        }

        $pdo->prepare("UPDATE room_units SET status = 'on_hold', updated_at = NOW() WHERE id = ?")->execute([$unitId]);

        $pnr         = generate_unique_pnr($pdo);
        $bookingCode = 'BK' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));

        $pdo->prepare(
            "INSERT INTO bookings
               (pnr_code, booking_code, user_id, room_unit_id, duration_type, duration_value,
                start_date, end_date, total_amount, payment_scheme, paid_amount, remaining_amount,
                status, booking_type, expires_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 'pending_payment', 'online',
                     DATE_ADD(NOW(), INTERVAL ? MINUTE), NOW(), NOW())"
        )->execute([
            $pnr, $bookingCode, $userId, $unitId, $in['duration_type'], $in['duration_value'],
            $start->format('Y-m-d'), $end->format('Y-m-d'), $total, $in['payment_scheme'], $total,
            (int)config('hold_minutes'),
        ]);
        $bookingId = (int)$pdo->lastInsertId();

        $pdo->prepare(
            "INSERT INTO payments (booking_id, invoice_code, payment_type, amount, payment_method, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, 'qris', 'pending', NOW(), NOW())"
        )->execute([
            $bookingId, 'INV' . date('ymd') . strtoupper(bin2hex(random_bytes(3))),
            $isDp ? 'dp_50' : 'full_100', $toPay,
        ]);

        $pdo->commit();
        return ['ok' => true, 'pnr' => $pnr];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => 'Gagal membuat pesanan, silakan coba lagi.'];
    }
}

function page_booking_show(string $pnr): void
{
    require_login();
    $st = db()->prepare(
        'SELECT b.*, UNIX_TIMESTAMP(b.expires_at) AS exp_ts, UNIX_TIMESTAMP() AS now_ts,
                ru.room_number, rc.name AS class_name, p.name AS property_name, c.name AS city_name
           FROM bookings b
           JOIN room_units ru   ON ru.id = b.room_unit_id
           JOIN room_classes rc ON rc.id = ru.room_class_id
           JOIN properties p    ON p.id = rc.property_id
           JOIN cities c        ON c.id = p.city_id
          WHERE b.pnr_code = ?'
    );
    $st->execute([$pnr]);
    $b = $st->fetch();

    if (!$b || ((int)$b['user_id'] !== (int)current_user()['id'] && current_user()['role'] !== 'admin')) {
        http_response_code(404);
        view('404', ['title' => 'Pesanan tidak ditemukan']);
        return;
    }

    $st = db()->prepare('SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1');
    $st->execute([$b['id']]);
    $payment = $st->fetch();

    $secondsLeft = ($b['status'] === 'pending_payment') ? max(0, (int)$b['exp_ts'] - (int)$b['now_ts']) : 0;

    $waText = "Halo Admin, saya ingin konfirmasi booking Kode PNR: {$b['pnr_code']} "
            . "({$b['property_name']} - {$b['class_name']}, No {$b['room_number']}).";
    $waUrl  = 'https://wa.me/' . config('wa_owner') . '?text=' . urlencode($waText);

    view('booking/show', ['title' => 'Pesanan ' . $pnr, 'b' => $b, 'payment' => $payment, 'secondsLeft' => $secondsLeft, 'waUrl' => $waUrl]);
}

function page_history(): void
{
    require_login();
    $st = db()->prepare(
        'SELECT b.pnr_code, b.status, b.total_amount, b.start_date, b.end_date,
                ru.room_number, rc.name AS class_name, p.name AS property_name
           FROM bookings b
           JOIN room_units ru   ON ru.id = b.room_unit_id
           JOIN room_classes rc ON rc.id = ru.room_class_id
           JOIN properties p    ON p.id = rc.property_id
          WHERE b.user_id = ? ORDER BY b.id DESC'
    );
    $st->execute([current_user()['id']]);
    view('booking/history', ['title' => 'Riwayat Pesanan', 'rows' => $st->fetchAll()]);
}