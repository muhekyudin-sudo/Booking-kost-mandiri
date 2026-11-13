<?php
final class AccountMenuController
{
    public function security(array $p): void
    {
        view('accountmenu/security', ['user' => current_user()]);
    }

    public function settings(array $p): void
    {
        $account = $this->loadAccount((int) current_user()['id']);
        if (!$account) {
            logout_user();
            redirect('/login');
        }

        view('accountmenu/settings', [
            'extraCss' => ['/assets/css/settings.css'],
            'account'  => $account,
            // Isian lama dikembalikan kalau validasi gagal; kalau tidak, pakai data dari database.
            'oldName'  => flash('old_name') ?? (string) $account['name'],
            'oldPhone' => flash('old_phone') ?? (string) ($account['phone'] ?? ''),
        ]);
    }

    public function updateProfile(array $p): void
    {
        // ID selalu dari session, tidak pernah dari form.
        $user = current_user();
        $userId = (int) $user['id'];

        $name = $this->postText('name');
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        // Spasi, tanda hubung, titik, dan kurung dibuang agar "0812-3456-7890" ikut valid.
        $phone = (string) preg_replace('/[\s().-]/', '', $this->postText('phone'));

        flash('old_name', $name);
        flash('old_phone', $this->postText('phone'));

        if ($name === '') {
            $this->failSettings('Nama wajib diisi.');
        }
        if (mb_strlen($name) < 2) {
            $this->failSettings('Nama minimal 2 karakter.');
        }
        if (mb_strlen($name) > 100) {
            $this->failSettings('Nama maksimal 100 karakter.');
        }
        if (preg_match('/\p{C}/u', $name)) {
            $this->failSettings('Nama mengandung karakter yang tidak diizinkan.');
        }

        if ($phone === '' && $user['role'] === 'tenant') {
            $this->failSettings('Nomor telepon wajib diisi.');
        }
        // Format nomor HP Indonesia: 08xx..., 628xx..., atau +628xx... (10-14 digit setelah 8).
        if ($phone !== '' && !preg_match('/^(?:\+62|62|0)8\d{8,12}$/', $phone)) {
            $this->failSettings('Nomor telepon tidak valid. Contoh: 081234567890 atau +6281234567890.');
        }

        $stmt = db()->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?');
        $stmt->execute([$name, $phone === '' ? null : $phone, $userId]);

        // Nama di navbar dan sidebar diambil dari session, jadi ikut diperbarui.
        $_SESSION['user']['name'] = $name;

        // Isian lama tidak diperlukan lagi setelah berhasil disimpan.
        unset($_SESSION['_flash']['old_name'], $_SESSION['_flash']['old_phone']);
        flash('success', 'Profil berhasil diperbarui.');
        redirect('/accountmenu/settings.php');
    }

    private function loadAccount(int $userId): ?array
    {
        $stmt = db()->prepare(
            'SELECT u.id, u.name, u.email, u.phone, u.role, u.created_at, p.name AS property_name
             FROM users u
             LEFT JOIN properties p ON p.id = u.property_id
             WHERE u.id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Ambil isian form sebagai teks; kalau bukan string (mis. array), anggap kosong. */
    private function postText(string $key): string
    {
        $value = $_POST[$key] ?? '';
        return is_string($value) ? trim($value) : '';
    }

    private function failSettings(string $message): never
    {
        flash('error', $message);
        redirect('/accountmenu/settings.php');
    }
}
