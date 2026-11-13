<?php
final class DashboardController
{
    public function tenant(array $p): void
    {
        view('dashboard', ['title' => 'Dashboard Penyewa']);
    }
    public function admin(array $p): void
    {
        $stmt = db()->prepare(
            "SELECT (SELECT COUNT(*) FROM properties) AS gedung,
                (SELECT COUNT(*) FROM room_units WHERE status = 'available') AS tersedia,
                (SELECT COUNT(*) FROM room_units WHERE status = 'occupied') AS terisi,
                (SELECT COUNT(*) FROM payments pay
                  JOIN bookings b ON b.id = pay.booking_id
                  WHERE pay.status = 'pending' AND pay.proof_file IS NOT NULL
                    AND b.status IN ('waiting_approval', 'active')) AS menunggu"
        );
        $stmt->execute();
        $year = (int) date('Y');
        $revenueStmt = db()->prepare(
            "SELECT MONTH(approved_at) AS month, SUM(amount) AS revenue
             FROM payments
             WHERE status = 'approved'
               AND approved_at >= ?
               AND approved_at < ?
             GROUP BY MONTH(approved_at)"
        );
        $revenueStmt->execute([
            sprintf('%04d-01-01 00:00:00', $year),
            sprintf('%04d-01-01 00:00:00', $year + 1),
        ]);
        $revenueByMonth = array_fill(1, 12, 0.0);
        foreach ($revenueStmt->fetchAll() as $row) {
            $revenueByMonth[(int) $row['month']] = (float) $row['revenue'];
        }

        view('admin/dashboard', [
            'extraCss' => ['/assets/css/dashboard.css'],
            'stats' => $stmt->fetch(),
            'revenueByMonth' => $revenueByMonth,
            'revenueYear' => $year,
        ]);
    }
    public function receptionist(array $p): void
    {
        $propertyId = (int) current_user()['property_id'];
        $stmt = db()->prepare(
            "SELECT
            (SELECT name FROM properties WHERE id = ?) AS gedung,
            (SELECT COUNT(*) FROM room_units ru
              JOIN room_classes rc ON rc.id = ru.room_class_id
              WHERE rc.property_id = ? AND ru.status = 'available') AS tersedia,
            (SELECT COUNT(*) FROM room_units ru
              JOIN room_classes rc ON rc.id = ru.room_class_id
              WHERE rc.property_id = ? AND ru.status = 'occupied') AS terisi,
            (SELECT COUNT(*) FROM payments pay
              JOIN bookings b ON b.id = pay.booking_id
              JOIN room_units ru ON ru.id = b.room_unit_id
              JOIN room_classes rc ON rc.id = ru.room_class_id
              WHERE rc.property_id = ? AND pay.status = 'pending' AND pay.proof_file IS NOT NULL
                AND b.status IN ('waiting_approval', 'active')) AS menunggu,
            (SELECT COUNT(*) FROM bookings b
              JOIN room_units ru ON ru.id = b.room_unit_id
              JOIN room_classes rc ON rc.id = ru.room_class_id
              WHERE rc.property_id = ? AND b.status = 'active') AS aktif,
            (SELECT COUNT(*) FROM bookings b
              JOIN room_units ru ON ru.id = b.room_unit_id
              JOIN room_classes rc ON rc.id = ru.room_class_id
              WHERE rc.property_id = ? AND b.status = 'active' AND b.remaining_amount > 0
                AND b.remaining_due_date < ?) AS lewat_tempo"
        );
        $stmt->execute([$propertyId, $propertyId, $propertyId, $propertyId, $propertyId, $propertyId, date('Y-m-d')]);
        view('receptionist/dashboard', [
            'extraCss' => ['/assets/css/dashboard.css'],
            'stats' => $stmt->fetch(),
        ]);
    }
}
