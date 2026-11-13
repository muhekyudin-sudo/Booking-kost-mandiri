<?php
declare(strict_types=1);

final class ReceptionistController
{
    public function bookings(array $params): void
    {
        $search = strtoupper(trim(is_string($_GET['pnr'] ?? null) ? $_GET['pnr'] : ''));
        if ($search !== '' && !preg_match('/^[23456789A-HJ-NP-Z]{6}$/', $search)) {
            flash('error', 'PNR harus terdiri dari 6 karakter yang valid.');
            redirect('/resepsionis/bookings');
        }

        $stmt = db()->prepare(
            "SELECT b.id, b.pnr_code, b.status AS booking_status, b.booking_type, b.start_date, b.end_date,
                    b.total_amount, b.paid_amount, b.remaining_amount, b.payment_scheme, b.remaining_due_date,
                    u.name AS tenant_name, u.email AS tenant_email, u.phone AS tenant_phone,
                    p.name AS property_name, rc.name AS room_class_name, ru.room_number, ru.status AS unit_status,
                    pay.id AS payment_id, pay.amount AS payment_amount, pay.payment_method,
                    pay.status AS payment_status, pay.proof_file, pay.rejection_reason,
                    approver.name AS approved_by_name, approver_staff.name AS approved_by_staff_name
             FROM bookings b
             JOIN users u ON u.id = b.user_id
             JOIN room_units ru ON ru.id = b.room_unit_id
             JOIN room_classes rc ON rc.id = ru.room_class_id
             JOIN properties p ON p.id = rc.property_id
             LEFT JOIN payments pay ON pay.id = (
                 SELECT MAX(p2.id) FROM payments p2 WHERE p2.booking_id = b.id
             )
             LEFT JOIN users approver ON approver.id = pay.approved_by_user_id
             LEFT JOIN receptionist_staff approver_staff ON approver_staff.id = pay.approved_by_staff_id
             WHERE p.id = ? AND (? = '' OR b.pnr_code = ?)
             ORDER BY (b.status = 'waiting_approval' OR EXISTS (
                 SELECT 1 FROM payments pp
                 WHERE pp.booking_id = b.id AND pp.status = 'pending' AND pp.proof_file IS NOT NULL
             )) DESC, b.id DESC"
        );
        $stmt->execute([(int) current_user()['property_id'], $search, $search]);
        $bookings = $stmt->fetchAll();
        view('receptionist/bookings', [
            'extraCss' => ['/assets/css/filters.css', '/assets/css/booking.css'],
            'bookings' => $bookings,
            'paymentsByBooking' => BookingService::paymentsForBookings(array_column($bookings, 'id')),
            'search' => $search,
            'staffName' => (string) $_SESSION['active_staff_name'],
            'isAdmin' => false,
        ]);
    }

    public function cashier(array $params): void
    {
        $stmt = db()->prepare(
            "SELECT ru.id, ru.room_number, rc.name AS room_class_name,
                    pm.daily_price, pm.weekly_price, pm.monthly_price, pm.yearly_price
             FROM room_units ru
             JOIN room_classes rc ON rc.id = ru.room_class_id
             LEFT JOIN price_matrices pm ON pm.room_class_id = rc.id
             WHERE rc.property_id = ? AND ru.status = 'available'
             ORDER BY rc.name, ru.room_number"
        );
        $stmt->execute([(int) current_user()['property_id']]);
        view('receptionist/cashier', [
            'units' => $stmt->fetchAll(),
            'today' => date('Y-m-d'),
            'latestStartDate' => date('Y-m-d', strtotime('+7 days')),
        ]);
    }

    public function storeCashier(array $params): void
    {
        $unitId = $this->postInt('unit_id');
        $durationValue = $this->postInt('duration_value');
        $name = $this->postString('tenant_name');
        $email = mb_strtolower($this->postString('tenant_email'));
        $phone = $this->postString('tenant_phone');
        $durationType = $this->postString('duration_type');
        $paymentScheme = $this->postString('payment_scheme');
        $startDate = $this->postString('start_date');

        if ($unitId < 1 || $durationValue < 1 || mb_strlen($name) < 1 || mb_strlen($name) > 255
            || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255
            || $phone === '' || mb_strlen($phone) > 20) {
            flash('error', 'Data penyewa, kamar, atau durasi tidak valid.');
            redirect('/resepsionis/kasir');
        }

        try {
            $bookingId = BookingService::createOfflineBooking(
                $unitId,
                (int) current_user()['id'],
                (int) $_SESSION['active_staff_id'],
                $name,
                $email,
                $phone,
                $durationType,
                $durationValue,
                $startDate,
                $paymentScheme
            );
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
            redirect('/resepsionis/kasir');
        }

        $stmt = db()->prepare('SELECT pnr_code FROM bookings WHERE id = ?');
        $stmt->execute([$bookingId]);
        $pnr = (string) $stmt->fetchColumn();
        try {
            EmailService::sendReceipt($bookingId);
            flash('success', 'Transaksi tunai berhasil dicatat. PNR: ' . $pnr . '. E-kuitansi dikirim ke penyewa.');
        } catch (RuntimeException|PDOException $e) {
            error_log('Offline booking receipt email failed for booking ' . $bookingId . ': ' . $e->getMessage());
            flash('error', 'Transaksi berhasil dicatat dengan PNR ' . $pnr . ', tetapi e-kuitansi gagal dikirim. Periksa konfigurasi SMTP.');
        }
        redirect('/resepsionis/bookings?pnr=' . rawurlencode($pnr));
    }

    public function approve(array $params): void
    {
        $paymentId = $this->positiveId($params['payment_id'] ?? null);
        $isSettlement = BookingService::paymentType($paymentId) === 'pelunasan_dp';
        try {
            $bookingId = BookingService::reviewPayment(
                $paymentId,
                (int) current_user()['id'],
                (int) $_SESSION['active_staff_id'],
                true
            );
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
            redirect('/resepsionis/bookings');
        }

        $label = $isSettlement ? 'Pelunasan disetujui, booking kini lunas' : 'Pembayaran disetujui';
        try {
            EmailService::sendReceipt($bookingId);
            flash('success', $label . ' dan e-kuitansi dikirim ke penyewa.');
        } catch (RuntimeException|PDOException $e) {
            error_log('Approved payment receipt email failed for booking ' . $bookingId . ': ' . $e->getMessage());
            flash('error', $label . ', tetapi e-kuitansi gagal dikirim. Periksa konfigurasi SMTP.');
        }
        redirect('/resepsionis/bookings');
    }

    public function reject(array $params): void
    {
        $paymentId = $this->positiveId($params['payment_id'] ?? null);
        $reason = $this->postString('rejection_reason');
        $isSettlement = BookingService::paymentType($paymentId) === 'pelunasan_dp';
        try {
            $bookingId = BookingService::reviewPayment(
                $paymentId,
                (int) current_user()['id'],
                (int) $_SESSION['active_staff_id'],
                false,
                $reason
            );
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
            redirect('/resepsionis/bookings');
        }

        try {
            if ($isSettlement) {
                EmailService::sendSettlementRejected($bookingId, $reason);
                flash('success', 'Bukti pelunasan ditolak. Booking tetap aktif dan penyewa dapat mengunggah ulang.');
            } else {
                EmailService::sendCancellation($bookingId, $reason);
                flash('success', 'Bukti pembayaran ditolak. Kamar dilepas dan email pembatalan dikirim.');
            }
        } catch (RuntimeException|PDOException $e) {
            error_log('Rejected payment email failed for booking ' . $bookingId . ': ' . $e->getMessage());
            flash('error', $isSettlement
                ? 'Bukti pelunasan ditolak, tetapi email pemberitahuan gagal dikirim. Periksa konfigurasi SMTP.'
                : 'Bukti pembayaran ditolak dan kamar dilepas, tetapi email pembatalan gagal dikirim. Periksa konfigurasi SMTP.');
        }
        redirect('/resepsionis/bookings');
    }

    public function settleCash(array $params): void
    {
        $bookingId = $this->positiveId($params['booking_id'] ?? null);
        try {
            BookingService::recordCashSettlement(
                $bookingId,
                (int) current_user()['id'],
                (int) $_SESSION['active_staff_id']
            );
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
            redirect('/resepsionis/bookings');
        }

        try {
            EmailService::sendReceipt($bookingId);
            flash('success', 'Pelunasan tunai dicatat, booking kini lunas, dan e-kuitansi dikirim ke penyewa.');
        } catch (RuntimeException|PDOException $e) {
            error_log('Cash settlement receipt email failed for booking ' . $bookingId . ': ' . $e->getMessage());
            flash('error', 'Pelunasan tunai dicatat, tetapi e-kuitansi gagal dikirim. Periksa konfigurasi SMTP.');
        }
        redirect('/resepsionis/bookings');
    }

    public function cancel(array $params): void
    {
        $bookingId = $this->positiveId($params['booking_id'] ?? null);
        try {
            BookingService::cancelBooking(
                $bookingId,
                (int) current_user()['id'],
                (int) $_SESSION['active_staff_id']
            );
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
            redirect('/resepsionis/bookings');
        }

        try {
            EmailService::sendCancellation($bookingId, 'Pembatalan diproses oleh resepsionis atas permintaan penyewa.');
            flash('success', 'Booking dibatalkan, kamar dilepas, dan email pembatalan dikirim.');
        } catch (RuntimeException|PDOException $e) {
            error_log('Cancelled booking email failed for booking ' . $bookingId . ': ' . $e->getMessage());
            flash('error', 'Booking dibatalkan dan kamar dilepas, tetapi email pembatalan gagal dikirim. Periksa konfigurasi SMTP.');
        }
        redirect('/resepsionis/bookings');
    }

    private function postString(string $key): string
    {
        $value = $_POST[$key] ?? null;
        return is_string($value) ? trim($value) : '';
    }

    private function postInt(string $key): int
    {
        $value = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT);
        return $value === false || $value === null ? 0 : $value;
    }

    private function positiveId(mixed $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id === null || $id < 1) abort(404);
        return $id;
    }
}
