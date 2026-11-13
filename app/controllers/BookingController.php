<?php
declare(strict_types=1);

final class BookingController
{
    public function checkout(array $params): void
    {
        $unitId = $this->positiveId($params['unit_id'] ?? null);
        $user = current_user();
        if (!$user) {
            $_SESSION['booking_intent'] = $unitId;
            flash('error', 'Silakan masuk atau daftar sebagai penyewa untuk memesan kamar.');
            redirect('/login');
        }
        requireAuth();
        $user = current_user();
        if ($user['role'] !== 'tenant') redirect(home_for_role($user['role']));
        if (isset($_SESSION['booking_intent']) && (int) $_SESSION['booking_intent'] === $unitId) {
            unset($_SESSION['booking_intent']);
        }

        BookingService::expirePending($unitId);
        $stmt = db()->prepare(
            "SELECT ru.id AS unit_id, ru.room_number, ru.status AS unit_status,
                    rc.id AS room_class_id, rc.name AS room_class_name, rc.property_id,
                    p.name AS property_name,
                    pm.daily_price, pm.weekly_price, pm.monthly_price, pm.yearly_price,
                    EXISTS (
                        SELECT 1 FROM users u
                        WHERE u.property_id = p.id AND u.role = 'receptionist' AND u.is_active = 1
                          AND EXISTS (
                              SELECT 1 FROM receptionist_staff rs
                              WHERE rs.property_id = p.id AND rs.is_active = 1
                          )
                    ) AS accepts_online
             FROM room_units ru
             JOIN room_classes rc ON rc.id = ru.room_class_id
             JOIN properties p ON p.id = rc.property_id
             LEFT JOIN price_matrices pm ON pm.room_class_id = rc.id
             WHERE ru.id = ? LIMIT 1"
        );
        $stmt->execute([$unitId]);
        $unit = $stmt->fetch();
        if (!$unit) abort(404);
        if (!(int) $unit['accepts_online']) {
            abort(422, 'Gedung ini belum menerima pemesanan online.');
        }
        if ($unit['unit_status'] !== 'available') {
            abort(409, 'Kamar ini sudah tidak tersedia. Silakan pilih kamar lain.');
        }
        if (!array_filter([$unit['daily_price'], $unit['weekly_price'], $unit['monthly_price'], $unit['yearly_price']], static fn ($price): bool => $price !== null)) {
            abort(422, 'Harga sewa untuk kelas kamar ini belum tersedia.');
        }

        view('booking/checkout', [
            'unit' => $unit,
            'today' => date('Y-m-d'),
            'qrisImageUrl' => $this->qrisImageUrl(),
        ]);
    }

    public function store(array $params): void
    {
        $unitId = $this->positiveId($params['unit_id'] ?? null);
        $user = current_user();
        if (!$user) {
            $_SESSION['booking_intent'] = $unitId;
            flash('error', 'Silakan masuk atau daftar sebagai penyewa untuk memesan kamar.');
            redirect('/login');
        }
        requireAuth();
        $user = current_user();
        if ($user['role'] !== 'tenant') redirect(home_for_role($user['role']));
        if ($this->qrisImageUrl() === null) {
            flash('error', 'Booking belum dapat dibuat karena QRIS belum dikonfigurasi pengelola.');
            redirect('/booking/checkout/' . $unitId);
        }

        $durationType = $this->postString('duration_type');
        $durationValue = filter_var($_POST['duration_value'] ?? null, FILTER_VALIDATE_INT);
        $startDate = $this->postString('start_date');
        $paymentScheme = $this->postString('payment_scheme');
        if (!in_array($durationType, ['daily', 'weekly', 'monthly', 'yearly'], true)
            || $durationValue === false || $durationValue < 1 || $durationValue > 1000
            || !in_array($paymentScheme, ['full', 'dp'], true)) {
            flash('error', 'Durasi atau skema pembayaran tidak valid.');
            redirect('/booking/checkout/' . $unitId);
        }

        try {
            $bookingId = BookingService::createOnlineBooking(
                $unitId,
                (int) $user['id'],
                $durationType,
                $durationValue,
                $startDate,
                $paymentScheme
            );
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
            redirect('/booking/checkout/' . $unitId);
        }

        unset($_SESSION['booking_intent']);
        flash('success', 'Booking berhasil dibuat. Selesaikan pembayaran sebelum batas waktu.');
        redirect('/tenant/bookings/' . $bookingId);
    }

    public function myBookings(array $params): void
    {
        BookingService::expirePending();
        $stmt = db()->prepare($this->bookingQuery() . ' WHERE b.user_id = ? ORDER BY b.id DESC');
        $stmt->execute([(int) current_user()['id']]);
        view('booking/list', ['bookings' => $stmt->fetchAll()]);
    }

    public function show(array $params): void
    {
        BookingService::expirePending();
        $bookingId = $this->positiveId($params['booking_id'] ?? null);
        $stmt = db()->prepare($this->bookingQuery() . ' WHERE b.id = ? AND b.user_id = ? LIMIT 1');
        $stmt->execute([$bookingId, (int) current_user()['id']]);
        $booking = $stmt->fetch();
        if (!$booking) abort(404);

        view('booking/show', [
            'extraCss' => ['/assets/css/booking.css'],
            'booking' => $booking,
            'qrisImageUrl' => $this->qrisImageUrl(),
            'expiresAt' => $booking['expires_at'] !== null ? strtotime((string) $booking['expires_at']) * 1000 : null,
        ]);
    }

    public function uploadProof(array $params): void
    {
        $paymentId = $this->positiveId($params['payment_id'] ?? null);
        $uploaded = false;
        if ($this->qrisImageUrl() === null) {
            flash('error', 'Bukti bayar belum dapat dikirim karena QRIS belum dikonfigurasi pengelola.');
        } else {
            try {
                BookingService::storeProof(
                    $paymentId,
                    (int) current_user()['id'],
                    $_FILES['proof'] ?? [],
                    (int) ($_SERVER['REQUEST_TIME'] ?? time())
                );
                $uploaded = true;
            } catch (DomainException $e) {
                flash('error', $e->getMessage());
            }
        }

        $stmt = db()->prepare(
            'SELECT b.id FROM payments p JOIN bookings b ON b.id = p.booking_id
             WHERE p.id = ? AND b.user_id = ? LIMIT 1'
        );
        $stmt->execute([$paymentId, (int) current_user()['id']]);
        $bookingId = $stmt->fetchColumn();
        if ($bookingId === false) abort(404);
        if ($uploaded) {
            flash('success', 'Bukti pembayaran berhasil dikirim.');
            try {
                EmailService::notifyNewProof($paymentId);
            } catch (RuntimeException|PDOException $e) {
                error_log('New payment proof notification failed for payment ' . $paymentId . ': ' . $e->getMessage());
                flash('error', 'Bukti tersimpan, tetapi notifikasi email resepsionis gagal dikirim. Periksa konfigurasi SMTP.');
            }
        }
        redirect('/tenant/bookings/' . (int) $bookingId);
    }

    public function proofFile(array $params): never
    {
        $paymentId = $this->positiveId($params['payment_id'] ?? null);
        $user = current_user();
        if (!$user) abort(403);
        if ($user['role'] === 'receptionist') {
            ensureStaffSelected();
        } elseif (!in_array($user['role'], ['tenant', 'admin'], true)) {
            abort(403);
        }

        $stmt = db()->prepare(
            'SELECT p.proof_file, b.user_id, rc.property_id
             FROM payments p
             JOIN bookings b ON b.id = p.booking_id
             JOIN room_units ru ON ru.id = b.room_unit_id
             JOIN room_classes rc ON rc.id = ru.room_class_id
             WHERE p.id = ? LIMIT 1'
        );
        $stmt->execute([$paymentId]);
        $proof = $stmt->fetch();
        if (!$proof || $proof['proof_file'] === null) abort(404);

        if ($user['role'] === 'tenant' && (int) $proof['user_id'] !== (int) $user['id']) abort(403);
        if ($user['role'] === 'receptionist') {
            if ((int) $proof['property_id'] !== (int) $user['property_id']) abort(403);
        }

        if (!preg_match('#^bukti/[a-f0-9]{32}\.(?:jpg|png|webp)$#', (string) $proof['proof_file'])) {
            error_log('A payment proof has an invalid private storage path.');
            abort(404);
        }
        $path = BASE_PATH . '/storage/private/' . $proof['proof_file'];
        if (!is_file($path)) abort(404);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            error_log('A payment proof has an unsupported stored MIME type.');
            abort(404);
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: inline');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    private function bookingQuery(): string
    {
        return "SELECT b.id, b.pnr_code, b.booking_code, b.duration_type, b.duration_value,
                       b.start_date, b.end_date, b.total_amount, b.payment_scheme, b.paid_amount,
                       b.remaining_amount, b.remaining_due_date, b.status, b.expires_at,
                       ru.room_number, rc.name AS room_class_name, p.name AS property_name,
                       pay.id AS payment_id, pay.invoice_code, pay.payment_type, pay.amount AS payment_amount,
                       pay.status AS payment_status, pay.proof_file, pay.rejection_reason
                FROM bookings b
                JOIN room_units ru ON ru.id = b.room_unit_id
                JOIN room_classes rc ON rc.id = ru.room_class_id
                JOIN properties p ON p.id = rc.property_id
                LEFT JOIN payments pay ON pay.booking_id = b.id";
    }

    private function qrisImageUrl(): ?string
    {
        $configured = trim((string) env('QRIS_IMAGE_URL', ''));
        if ($configured === '') return null;
        if (str_starts_with($configured, '/assets/')) return url($configured);
        $parts = parse_url($configured);
        if (filter_var($configured, FILTER_VALIDATE_URL) && ($parts['scheme'] ?? '') === 'https') {
            return $configured;
        }
        error_log('QRIS_IMAGE_URL must be a local /assets/ path or an HTTPS URL.');
        return null;
    }

    private function positiveId(mixed $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id === null || $id < 1) abort(404);
        return $id;
    }

    private function postString(string $key): string
    {
        $value = $_POST[$key] ?? null;
        if (!is_string($value)) return '';
        return trim($value);
    }
}
