<?php
declare(strict_types=1);

final class StaffController
{
    public function select(array $params): void
    {
        $user = current_user();
        $stmt = db()->prepare(
            'SELECT id, name FROM receptionist_staff WHERE property_id = ? AND is_active = 1 ORDER BY name'
        );
        $stmt->execute([$user['property_id']]);
        view('receptionist/select-staff', ['staff' => $stmt->fetchAll()]);
    }

    public function storeSelection(array $params): void
    {
        $staffId = filter_var($_POST['staff_id'] ?? null, FILTER_VALIDATE_INT);
        if ($staffId === false || $staffId === null || $staffId < 1) {
            flash('error', 'Pilih petugas jaga yang valid.');
            redirect('/resepsionis/pilih-petugas');
        }

        $user = current_user();
        $stmt = db()->prepare(
            'SELECT id, name FROM receptionist_staff WHERE id = ? AND property_id = ? AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([$staffId, $user['property_id']]);
        $staff = $stmt->fetch();
        if (!$staff) {
            flash('error', 'Petugas tidak aktif atau bukan milik gedung Anda. Silakan pilih lagi.');
            redirect('/resepsionis/pilih-petugas');
        }

        $_SESSION['active_staff_id'] = (int) $staff['id'];
        $_SESSION['active_staff_name'] = $staff['name'];
        redirect('/resepsionis');
    }

    public function change(array $params): void
    {
        unset($_SESSION['active_staff_id'], $_SESSION['active_staff_name']);
        redirect('/resepsionis/pilih-petugas');
    }
}
