<?php
declare(strict_types=1);

final class BookingService
{
    private const PNR_CHARACTERS = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const MAX_PROOF_SIZE = 5 * 1024 * 1024;

    public static function expirePending(?int $unitId = null): int
    {
        $pdo = db();
        $expiredBookingIds = [];
        $pdo->beginTransaction();
        try {
            $sql = "SELECT id, room_unit_id FROM bookings
                    WHERE status = 'pending_payment' AND expires_at < CURRENT_TIMESTAMP";
            $params = [];
            if ($unitId !== null) {
                $sql .= ' AND room_unit_id = ?';
                $params[] = $unitId;
            }
            $sql .= ' ORDER BY id FOR UPDATE';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $expire = $pdo->prepare(
                "UPDATE bookings SET status = 'expired' WHERE id = ? AND status = 'pending_payment'"
            );
            $release = $pdo->prepare(
                "UPDATE room_units SET status = 'available' WHERE id = ? AND status = 'on_hold'"
            );
            foreach ($stmt->fetchAll() as $booking) {
                $expire->execute([(int) $booking['id']]);
                if ($expire->rowCount() === 1) {
                    $release->execute([(int) $booking['room_unit_id']]);
                    $expiredBookingIds[] = (int) $booking['id'];
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        foreach ($expiredBookingIds as $bookingId) {
            try {
                EmailService::sendCancellation($bookingId, 'Batas waktu pembayaran 1 jam telah habis.');
            } catch (RuntimeException|PDOException $e) {
                error_log('Expired booking cancellation email failed for booking ' . $bookingId . ': ' . $e->getMessage());
            }
        }

        return count($expiredBookingIds);
    }

    public static function createOnlineBooking(
        int $unitId,
        int $tenantId,
        string $durationType,
        int $durationValue,
        string $startDate,
        string $paymentScheme
    ): int {
        self::expirePending($unitId);
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT ru.status AS unit_status, rc.name AS room_class_name, rc.property_id,
                        p.name AS property_name, pm.daily_price, pm.weekly_price, pm.monthly_price, pm.yearly_price
                 FROM room_units ru
                 JOIN room_classes rc ON rc.id = ru.room_class_id
                 JOIN properties p ON p.id = rc.property_id
                 LEFT JOIN price_matrices pm ON pm.room_class_id = rc.id
                 WHERE ru.id = ? FOR UPDATE'
            );
            $stmt->execute([$unitId]);
            $unit = $stmt->fetch();
            if (!$unit || $unit['unit_status'] !== 'available') {
                throw new DomainException('Kamar ini sudah tidak tersedia. Silakan pilih kamar lain.');
            }

            $receptionist = $pdo->prepare(
                "SELECT u.id FROM users u
                 WHERE u.property_id = ? AND u.role = 'receptionist' AND u.is_active = 1
                   AND EXISTS (
                       SELECT 1 FROM receptionist_staff rs
                       WHERE rs.property_id = u.property_id AND rs.is_active = 1
                   )
                 LIMIT 1"
            );
            $receptionist->execute([(int) $unit['property_id']]);
            if ($receptionist->fetchColumn() === false) {
                throw new DomainException('Gedung ini belum menerima pemesanan online.');
            }

            $priceColumn = match ($durationType) {
                'daily' => 'daily_price',
                'weekly' => 'weekly_price',
                'monthly' => 'monthly_price',
                'yearly' => 'yearly_price',
                default => throw new DomainException('Durasi sewa tidak valid.'),
            };
            $unitPrice = $unit[$priceColumn];
            if ($unitPrice === null || !preg_match('/^\d+(?:\.\d{1,2})?$/', (string) $unitPrice)) {
                throw new DomainException('Harga untuk durasi sewa ini belum tersedia.');
            }
            if ($paymentScheme === 'dp' && $durationType === 'daily') {
                throw new DomainException('Sewa harian wajib dibayar lunas.');
            }
            if (!in_array($paymentScheme, ['full', 'dp'], true)) {
                throw new DomainException('Skema pembayaran tidak valid.');
            }

            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
            $dateErrors = DateTimeImmutable::getLastErrors();
            if ($date === false
                || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
                || $date->format('Y-m-d') !== $startDate
                || $startDate < date('Y-m-d')) {
                throw new DomainException('Tanggal mulai tidak valid atau sudah lewat.');
            }

            $duration = match ($durationType) {
                'daily' => new DateInterval('P' . $durationValue . 'D'),
                'weekly' => new DateInterval('P' . $durationValue . 'W'),
                'monthly' => new DateInterval('P' . $durationValue . 'M'),
                'yearly' => new DateInterval('P' . $durationValue . 'Y'),
            };
            $endDate = $date->add($duration)->modify('-1 day')->format('Y-m-d');
            $dueDate = $paymentScheme === 'dp'
                ? $date->modify('+' . ($durationType === 'weekly' ? '3' : '7') . ' days')->format('Y-m-d')
                : null;

            $unitPriceCents = self::toCents((string) $unitPrice);
            if ($unitPriceCents > intdiv(PHP_INT_MAX, $durationValue)) {
                throw new DomainException('Total harga di luar batas yang dapat diproses.');
            }
            $totalCents = $unitPriceCents * $durationValue;
            if ($totalCents > 999999999999) {
                throw new DomainException('Total harga melebihi batas transaksi.');
            }
            $paidCents = $paymentScheme === 'dp' ? intdiv($totalCents + 1, 2) : $totalCents;
            $totalAmount = self::fromCents($totalCents);
            $paidAmount = self::fromCents($paidCents);
            $remainingAmount = self::fromCents($totalCents - $paidCents);

            $insertBooking = $pdo->prepare(
                "INSERT INTO bookings
                    (pnr_code, booking_code, user_id, room_unit_id, duration_type, duration_value,
                     start_date, end_date, total_amount, payment_scheme, paid_amount, remaining_amount,
                     remaining_due_date, status, booking_type, created_by_user_id, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_payment', 'online', ?, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 1 HOUR))"
            );
            $insertPayment = $pdo->prepare(
                "INSERT INTO payments (booking_id, invoice_code, payment_type, amount, payment_method, status)
                 VALUES (?, ?, ?, ?, 'qris', 'pending')"
            );
            $updateUnit = $pdo->prepare(
                "UPDATE room_units SET status = 'on_hold' WHERE id = ? AND status = 'available'"
            );

            for ($attempt = 0; $attempt < 5; $attempt++) {
                $pnr = self::generatePnr($pdo);
                try {
                    $insertBooking->execute([
                        $pnr,
                        'BK-' . $pnr,
                        $tenantId,
                        $unitId,
                        $durationType,
                        $durationValue,
                        $startDate,
                        $endDate,
                        $totalAmount,
                        $paymentScheme,
                        $paidAmount,
                        $remainingAmount,
                        $dueDate,
                        $tenantId,
                    ]);
                } catch (PDOException $e) {
                    if ($e->getCode() !== '23000' || (int) ($e->errorInfo[1] ?? 0) !== 1062 || $attempt === 4) {
                        throw $e;
                    }
                    continue;
                }
                $bookingId = (int) $pdo->lastInsertId();
                $insertPayment->execute([
                    $bookingId,
                    'INV-' . $pnr,
                    $paymentScheme === 'dp' ? 'dp_50' : 'full_100',
                    $paidAmount,
                ]);
                $updateUnit->execute([$unitId]);
                if ($updateUnit->rowCount() !== 1) {
                    throw new DomainException('Kamar ini sudah tidak tersedia. Silakan pilih kamar lain.');
                }
                $pdo->commit();
                return $bookingId;
            }
            throw new RuntimeException('PNR unik tidak dapat dibuat setelah beberapa percobaan.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function createOfflineBooking(
        int $unitId,
        int $receptionistId,
        int $staffId,
        string $tenantName,
        string $tenantEmail,
        string $tenantPhone,
        string $durationType,
        int $durationValue,
        string $startDate,
        string $paymentScheme
    ): int {
        if (!in_array($durationType, ['daily', 'weekly', 'monthly', 'yearly'], true)
            || $durationValue < 1 || $durationValue > 1000
            || !in_array($paymentScheme, ['full', 'dp'], true)) {
            throw new DomainException('Durasi atau skema pembayaran tidak valid.');
        }
        if ($paymentScheme === 'dp' && $durationType === 'daily') {
            throw new DomainException('Sewa harian wajib dibayar lunas.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
        $dateErrors = DateTimeImmutable::getLastErrors();
        $today = new DateTimeImmutable('today');
        if ($date === false
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $date->format('Y-m-d') !== $startDate
            || $date < $today
            || $date > $today->modify('+7 days')) {
            throw new DomainException('Tanggal mulai harus hari ini hingga 7 hari ke depan.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT u.property_id, u.role, u.is_active
                 FROM users u
                 WHERE u.id = ? FOR UPDATE"
            );
            $stmt->execute([$receptionistId]);
            $receptionist = $stmt->fetch();
            $staff = $pdo->prepare(
                'SELECT id FROM receptionist_staff WHERE id = ? AND property_id = ? AND is_active = 1'
            );
            if (!$receptionist || $receptionist['role'] !== 'receptionist' || !(int) $receptionist['is_active']) {
                throw new DomainException('Akun resepsionis tidak aktif.');
            }
            $propertyId = (int) $receptionist['property_id'];
            $staff->execute([$staffId, $propertyId]);
            if ($staff->fetchColumn() === false) {
                throw new DomainException('Petugas jaga tidak aktif atau bukan milik gedung Anda.');
            }

            $unitStmt = $pdo->prepare(
                'SELECT ru.status AS unit_status, rc.property_id, pm.daily_price, pm.weekly_price,
                        pm.monthly_price, pm.yearly_price
                 FROM room_units ru
                 JOIN room_classes rc ON rc.id = ru.room_class_id
                 LEFT JOIN price_matrices pm ON pm.room_class_id = rc.id
                 WHERE ru.id = ? FOR UPDATE'
            );
            $unitStmt->execute([$unitId]);
            $unit = $unitStmt->fetch();
            if (!$unit || (int) $unit['property_id'] !== $propertyId) {
                throw new DomainException('Kamar bukan milik gedung Anda.');
            }
            if ($unit['unit_status'] !== 'available') {
                throw new DomainException('Kamar sudah tidak tersedia. Pilih kamar lain.');
            }

            $priceColumn = match ($durationType) {
                'daily' => 'daily_price',
                'weekly' => 'weekly_price',
                'monthly' => 'monthly_price',
                'yearly' => 'yearly_price',
            };
            $unitPrice = $unit[$priceColumn];
            if ($unitPrice === null || !preg_match('/^\d+(?:\.\d{1,2})?$/', (string) $unitPrice)) {
                throw new DomainException('Harga untuk durasi sewa ini belum tersedia.');
            }
            $unitPriceCents = self::toCents((string) $unitPrice);
            if ($unitPriceCents > intdiv(PHP_INT_MAX, $durationValue)) {
                throw new DomainException('Total harga di luar batas yang dapat diproses.');
            }
            $totalCents = $unitPriceCents * $durationValue;
            if ($totalCents > 999999999999) {
                throw new DomainException('Total harga melebihi batas transaksi.');
            }

            $tenantEmail = mb_strtolower(trim($tenantEmail));
            $tenantStmt = $pdo->prepare('SELECT id, role, is_active FROM users WHERE email = ? FOR UPDATE');
            $tenantStmt->execute([$tenantEmail]);
            $tenant = $tenantStmt->fetch();
            if ($tenant) {
                if ($tenant['role'] !== 'tenant' || !(int) $tenant['is_active']) {
                    throw new DomainException('Email sudah dipakai akun non-penyewa atau akun tidak aktif.');
                }
                $tenantId = (int) $tenant['id'];
            } else {
                try {
                    $createTenant = $pdo->prepare(
                        "INSERT INTO users (name, email, phone, role, password, is_active)
                         VALUES (?, ?, ?, 'tenant', NULL, 1)"
                    );
                    $createTenant->execute([$tenantName, $tenantEmail, $tenantPhone]);
                    $tenantId = (int) $pdo->lastInsertId();
                } catch (PDOException $e) {
                    if ($e->getCode() !== '23000' || (int) ($e->errorInfo[1] ?? 0) !== 1062) {
                        throw $e;
                    }
                    $tenantStmt->execute([$tenantEmail]);
                    $existingTenant = $tenantStmt->fetch();
                    if (!$existingTenant || $existingTenant['role'] !== 'tenant' || !(int) $existingTenant['is_active']) {
                        throw new DomainException('Email sudah dipakai akun non-penyewa atau akun tidak aktif.');
                    }
                    $tenantId = (int) $existingTenant['id'];
                }
            }

            $endDate = $date->add(match ($durationType) {
                'daily' => new DateInterval('P' . $durationValue . 'D'),
                'weekly' => new DateInterval('P' . $durationValue . 'W'),
                'monthly' => new DateInterval('P' . $durationValue . 'M'),
                'yearly' => new DateInterval('P' . $durationValue . 'Y'),
            })->modify('-1 day')->format('Y-m-d');
            $dueDate = $paymentScheme === 'dp'
                ? $date->modify('+' . ($durationType === 'weekly' ? '3' : '7') . ' days')->format('Y-m-d')
                : null;
            $paidCents = $paymentScheme === 'dp' ? intdiv($totalCents + 1, 2) : $totalCents;
            $totalAmount = self::fromCents($totalCents);
            $paidAmount = self::fromCents($paidCents);
            $remainingAmount = self::fromCents($totalCents - $paidCents);

            $insertBooking = $pdo->prepare(
                "INSERT INTO bookings
                    (pnr_code, booking_code, user_id, room_unit_id, duration_type, duration_value,
                     start_date, end_date, total_amount, payment_scheme, paid_amount, remaining_amount,
                     remaining_due_date, status, booking_type, created_by_user_id, created_by_staff_id, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 'offline', ?, ?, NULL)"
            );
            $insertPayment = $pdo->prepare(
                "INSERT INTO payments
                    (booking_id, invoice_code, payment_type, amount, payment_method, status,
                     approved_at, approved_by_user_id, approved_by_staff_id)
                 VALUES (?, ?, ?, ?, 'cash', 'approved', CURRENT_TIMESTAMP, ?, ?)"
            );
            $updateUnit = $pdo->prepare(
                "UPDATE room_units SET status = 'occupied' WHERE id = ? AND status = 'available'"
            );

            for ($attempt = 0; $attempt < 5; $attempt++) {
                $pnr = self::generatePnr($pdo);
                try {
                    $insertBooking->execute([
                        $pnr,
                        'BK-' . $pnr,
                        $tenantId,
                        $unitId,
                        $durationType,
                        $durationValue,
                        $startDate,
                        $endDate,
                        $totalAmount,
                        $paymentScheme,
                        $paidAmount,
                        $remainingAmount,
                        $dueDate,
                        $receptionistId,
                        $staffId,
                    ]);
                } catch (PDOException $e) {
                    if ($e->getCode() !== '23000' || (int) ($e->errorInfo[1] ?? 0) !== 1062 || $attempt === 4) {
                        throw $e;
                    }
                    continue;
                }

                $bookingId = (int) $pdo->lastInsertId();
                $insertPayment->execute([
                    $bookingId,
                    'INV-' . $pnr,
                    $paymentScheme === 'dp' ? 'dp_50' : 'full_100',
                    $paidAmount,
                    $receptionistId,
                    $staffId,
                ]);
                $updateUnit->execute([$unitId]);
                if ($updateUnit->rowCount() !== 1) {
                    throw new DomainException('Kamar sudah tidak tersedia. Pilih kamar lain.');
                }
                $pdo->commit();
                return $bookingId;
            }
            throw new RuntimeException('PNR unik tidak dapat dibuat setelah beberapa percobaan.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function reviewPayment(
        int $paymentId,
        int $receptionistId,
        int $staffId,
        bool $approved,
        string $rejectionReason = ''
    ): int {
        $rejectionReason = trim($rejectionReason);
        if (!$approved && ($rejectionReason === '' || mb_strlen($rejectionReason) > 2000)) {
            throw new DomainException('Alasan penolakan wajib diisi (maksimal 2000 karakter).');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT p.status AS payment_status, p.payment_method, p.proof_file,
                        b.id AS booking_id, b.status AS booking_status, b.room_unit_id,
                        rc.property_id
                 FROM payments p
                 JOIN bookings b ON b.id = p.booking_id
                 JOIN room_units ru ON ru.id = b.room_unit_id
                 JOIN room_classes rc ON rc.id = ru.room_class_id
                 WHERE p.id = ? FOR UPDATE"
            );
            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch();
            if (!$payment || $payment['payment_method'] !== 'qris'
                || $payment['payment_status'] !== 'pending'
                || $payment['booking_status'] !== 'waiting_approval'
                || $payment['proof_file'] === null) {
                throw new DomainException('Pembayaran ini tidak lagi menunggu verifikasi.');
            }
            self::assertReceptionistForProperty($pdo, $receptionistId, $staffId, (int) $payment['property_id']);

            $unitStatus = $pdo->prepare('SELECT status FROM room_units WHERE id = ? FOR UPDATE');
            $unitStatus->execute([(int) $payment['room_unit_id']]);
            if ($unitStatus->fetchColumn() !== 'on_hold') {
                throw new DomainException('Status kamar berubah. Muat ulang halaman sebelum memproses.');
            }

            $updatePayment = $pdo->prepare(
                'UPDATE payments
                 SET status = ?, rejection_reason = ?, approved_at = CURRENT_TIMESTAMP,
                     approved_by_user_id = ?, approved_by_staff_id = ?
                 WHERE id = ? AND status = \'pending\''
            );
            $updatePayment->execute([
                $approved ? 'approved' : 'rejected',
                $approved ? null : $rejectionReason,
                $receptionistId,
                $staffId,
                $paymentId,
            ]);
            if ($updatePayment->rowCount() !== 1) {
                throw new DomainException('Pembayaran sudah diproses oleh petugas lain.');
            }

            $bookingStatus = $approved ? 'active' : 'cancelled';
            $updateBooking = $pdo->prepare(
                "UPDATE bookings SET status = ? WHERE id = ? AND status = 'waiting_approval'"
            );
            $updateBooking->execute([$bookingStatus, (int) $payment['booking_id']]);
            if ($updateBooking->rowCount() !== 1) {
                throw new DomainException('Status booking berubah. Muat ulang halaman sebelum memproses.');
            }
            $updateUnit = $pdo->prepare(
                'UPDATE room_units SET status = ? WHERE id = ? AND status = \'on_hold\''
            );
            $updateUnit->execute([$approved ? 'occupied' : 'available', (int) $payment['room_unit_id']]);
            if ($updateUnit->rowCount() !== 1) {
                throw new DomainException('Status kamar berubah. Muat ulang halaman sebelum memproses.');
            }

            $pdo->commit();
            return (int) $payment['booking_id'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function cancelBooking(int $bookingId, int $receptionistId, int $staffId): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT b.status, b.room_unit_id, rc.property_id
                 FROM bookings b
                 JOIN room_units ru ON ru.id = b.room_unit_id
                 JOIN room_classes rc ON rc.id = ru.room_class_id
                 WHERE b.id = ? FOR UPDATE"
            );
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch();
            if (!$booking) throw new DomainException('Booking tidak ditemukan.');
            self::assertReceptionistForProperty($pdo, $receptionistId, $staffId, (int) $booking['property_id']);
            if ($booking['status'] !== 'active') {
                throw new DomainException('Hanya booking aktif yang dapat dibatalkan.');
            }

            $unitStatus = $pdo->prepare('SELECT status FROM room_units WHERE id = ? FOR UPDATE');
            $unitStatus->execute([(int) $booking['room_unit_id']]);
            if ($unitStatus->fetchColumn() !== 'occupied') {
                throw new DomainException('Status kamar berubah. Muat ulang halaman sebelum membatalkan.');
            }

            $updateBooking = $pdo->prepare(
                "UPDATE bookings SET status = 'cancelled' WHERE id = ? AND status = 'active'"
            );
            $updateBooking->execute([$bookingId]);
            $releaseUnit = $pdo->prepare(
                "UPDATE room_units SET status = 'available' WHERE id = ? AND status = 'occupied'"
            );
            $releaseUnit->execute([(int) $booking['room_unit_id']]);
            if ($updateBooking->rowCount() !== 1 || $releaseUnit->rowCount() !== 1) {
                throw new DomainException('Status booking atau kamar berubah. Muat ulang halaman.');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    private static function assertReceptionistForProperty(
        PDO $pdo,
        int $receptionistId,
        int $staffId,
        int $propertyId
    ): void {
        $user = $pdo->prepare(
            "SELECT 1 FROM users WHERE id = ? AND role = 'receptionist'
             AND property_id = ? AND is_active = 1"
        );
        $user->execute([$receptionistId, $propertyId]);
        $staff = $pdo->prepare(
            'SELECT 1 FROM receptionist_staff WHERE id = ? AND property_id = ? AND is_active = 1'
        );
        $staff->execute([$staffId, $propertyId]);
        if ($user->fetchColumn() === false || $staff->fetchColumn() === false) {
            throw new DomainException('Petugas tidak berwenang memproses booking gedung ini.');
        }
    }

    public static function storeProof(int $paymentId, int $tenantId, array $file, int $requestTimestamp): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !isset($file['tmp_name'], $file['size'])
            || !is_string($file['tmp_name'])
            || !is_uploaded_file($file['tmp_name'])) {
            throw new DomainException('Pilih berkas bukti pembayaran yang valid.');
        }
        $size = filter_var($file['size'], FILTER_VALIDATE_INT);
        if ($size === false || $size < 1 || $size > self::MAX_PROOF_SIZE) {
            throw new DomainException('Ukuran bukti bayar harus maksimal 5 MB.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!is_string($mime) || !isset($extensions[$mime])) {
            throw new DomainException('Format bukti bayar harus JPG, PNG, atau WEBP.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        $storedPath = null;
        try {
            $stmt = $pdo->prepare(
                "SELECT p.id, b.id AS booking_id, b.room_unit_id, b.status AS booking_status, b.expires_at
                 FROM payments p
                 JOIN bookings b ON b.id = p.booking_id
                 WHERE p.id = ? AND b.user_id = ? AND p.payment_method = 'qris'
                 FOR UPDATE"
            );
            $stmt->execute([$paymentId, $tenantId]);
            $payment = $stmt->fetch();
            if (!$payment) {
                throw new DomainException('Pembayaran tidak ditemukan.');
            }
            if ($payment['booking_status'] !== 'pending_payment' || $payment['expires_at'] === null) {
                throw new DomainException('Booking ini tidak lagi menunggu bukti pembayaran.');
            }
            if ($requestTimestamp > strtotime((string) $payment['expires_at'])) {
                $expire = $pdo->prepare(
                    "UPDATE bookings SET status = 'expired'
                     WHERE id = ? AND status = 'pending_payment'"
                );
                $expire->execute([(int) $payment['booking_id']]);
                $release = $pdo->prepare(
                    "UPDATE room_units SET status = 'available' WHERE id = ? AND status = 'on_hold'"
                );
                $release->execute([(int) $payment['room_unit_id']]);
                $pdo->commit();
                throw new DomainException('Waktu pembayaran telah habis. Silakan buat booking baru.');
            }

            $directory = BASE_PATH . '/storage/private/bukti';
            if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
                throw new RuntimeException('Direktori penyimpanan bukti bayar tidak dapat dibuat.');
            }
            $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
            $storedPath = 'bukti/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
                throw new RuntimeException('Berkas bukti bayar gagal dipindahkan ke penyimpanan privat.');
            }

            $updatePayment = $pdo->prepare('UPDATE payments SET proof_file = ? WHERE id = ?');
            $updatePayment->execute([$storedPath, $paymentId]);
            $updateBooking = $pdo->prepare(
                "UPDATE bookings SET status = 'waiting_approval', expires_at = NULL
                 WHERE id = ? AND status = 'pending_payment'"
            );
            $updateBooking->execute([(int) $payment['booking_id']]);
            if ($updateBooking->rowCount() !== 1) {
                throw new DomainException('Status booking berubah. Muat ulang halaman dan coba kembali.');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($storedPath !== null && is_file(BASE_PATH . '/storage/private/' . $storedPath)) {
                unlink(BASE_PATH . '/storage/private/' . $storedPath);
            }
            throw $e;
        }
    }

    public static function generatePnr(PDO $pdo): string
    {
        $stmt = $pdo->prepare('SELECT 1 FROM bookings WHERE pnr_code = ? LIMIT 1');
        $max = strlen(self::PNR_CHARACTERS) - 1;
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $pnr = '';
            for ($i = 0; $i < 6; $i++) {
                $pnr .= self::PNR_CHARACTERS[random_int(0, $max)];
            }
            $stmt->execute([$pnr]);
            if ($stmt->fetchColumn() === false) return $pnr;
        }
        throw new RuntimeException('PNR unik tidak dapat dibuat setelah beberapa percobaan.');
    }

    private static function toCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private static function fromCents(int $amount): string
    {
        return intdiv($amount, 100) . '.' . str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }
}
