<?php
declare(strict_types=1);

/**
 * Lupa password lewat email.
 *
 *  1. GET/POST /lupa-password  : pengguna mengisi email. Kalau terdaftar, dibuat token acak
 *                                (berlaku 15 menit) dan linknya dikirim ke email.
 *  2. GET /reset-password      : link dari email. Token dicek, lalu tampil form password baru.
 *  3. POST /reset-password     : password baru disimpan (bcrypt), token dihanguskan,
 *                                email pemberitahuan dikirim, lalu diarahkan ke /login.
 */
final class PasswordResetController
{
    private const TOKEN_LIFETIME = 15 * 60;   // 15 menit, dalam detik
    private const PASSWORD_MIN = 8;           // karakter
    private const PASSWORD_MAX_BYTES = 72;    // batas bcrypt

    private const MESSAGE_SENT = 'Jika email terdaftar, link reset telah dikirim. Periksa kotak masuk dan folder spam. Link berlaku 15 menit.';

    private const BULAN = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    // ------------------------------------------------------------------
    // 1. Form "Lupa password"
    // ------------------------------------------------------------------
    public function showForgot(array $p): void
    {
        view('auth/forgot-password', ['extraCss' => ['/assets/css/auth.css']]);
    }

    public function forgot(array $p): void
    {
        $errors = validate($_POST, ['email' => 'required|email|max:255']);
        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('/lupa-password');
        }

        $stmt = db()->prepare('SELECT id, name, email FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([trim((string) $_POST['email'])]);
        $user = $stmt->fetch();

        $job = ($user ? $this->createResetJob($user) : null) ?? static function (): void {};

        // Pesan SAMA baik email terdaftar maupun tidak (mencegah email enumeration).
        flash('success', self::MESSAGE_SENT);
        $this->redirectThen('/lupa-password', $job);
    }

    public function requestForCurrentUser(array $p): void
    {
        $stmt = db()->prepare('SELECT id, name, email FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([(int) current_user()['id']]);
        $user = $stmt->fetch();
        if (!$user) {
            abort(403, 'Akun tidak aktif atau tidak dapat ditemukan.');
        }

        $job = $this->createResetJob($user);
        if ($job === null) {
            flash('error', 'Link ubah password tidak dapat dibuat saat ini. Silakan coba lagi nanti.');
            redirect('/accountmenu/security.php');
        }

        flash('success', 'Link untuk mengubah password telah dikirim ke email akun Anda. Link berlaku 15 menit.');
        $this->redirectThen('/accountmenu/security.php', $job);
    }

    // ------------------------------------------------------------------
    // 2. Link dari email: cek token, tampilkan form
    // ------------------------------------------------------------------
    public function showReset(array $p): void
    {
        $this->privateHeaders();
        $token = $this->tokenFrom($_GET['token'] ?? null);
        if ($token === null || $this->findValid($token) === null) {
            $this->invalid();
        }
        view('auth/reset-password', ['token' => $token, 'extraCss' => ['/assets/css/auth.css']]);
    }

    // ------------------------------------------------------------------
    // 3. Simpan password baru
    // ------------------------------------------------------------------
    public function reset(array $p): void
    {
        $this->privateHeaders();
        $token = $this->tokenFrom($_POST['token'] ?? null);
        $row = $token !== null ? $this->findValid($token) : null;
        if ($row === null) $this->invalid();

        $password = $_POST['password'] ?? null;
        $confirmation = $_POST['password_confirmation'] ?? null;
        $back = '/reset-password?token=' . $token;     // $token sudah dipastikan 64 karakter heksadesimal

        if (!is_string($password) || !is_string($confirmation) || $password === '') {
            flash('error', 'Password baru dan konfirmasinya wajib diisi.');
            redirect($back);
        }
        if (mb_strlen($password) < self::PASSWORD_MIN) {
            flash('error', 'Password baru minimal ' . self::PASSWORD_MIN . ' karakter.');
            redirect($back);
        }
        if (strlen($password) > self::PASSWORD_MAX_BYTES) {
            flash('error', 'Password baru maksimal ' . self::PASSWORD_MAX_BYTES . ' byte.');
            redirect($back);
        }
        if (!hash_equals($password, $confirmation)) {
            flash('error', 'Konfirmasi password tidak cocok.');
            redirect($back);
        }

        $now = date('Y-m-d H:i:s');
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Tandai token terpakai LEBIH DULU. Kalau dua orang memakai link yang sama bersamaan,
            // hanya satu yang berhasil (jumlah baris = 1); yang lain ditolak.
            $use = $pdo->prepare('UPDATE password_resets SET used_at = ? WHERE id = ? AND used_at IS NULL AND expires_at > ?');
            $use->execute([$now, (int) $row['id'], $now]);
            if ($use->rowCount() !== 1) {
                $pdo->rollBack();
                $this->invalid();
            }
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_BCRYPT), (int) $row['user_id']]);
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND id <> ?')
                ->execute([(int) $row['user_id'], (int) $row['id']]);               // token lain milik akun ini hangus
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        $when = $this->formatWhen(time());
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'tidak diketahui');
        $job = static function () use ($row, $when, $ip): void {
            try {
                EmailService::sendPasswordChanged((string) $row['email'], (string) $row['name'], $when, $ip);
            } catch (Throwable $e) {
                error_log('[password-reset] gagal kirim email pemberitahuan ke akun #' . $row['user_id'] . ': ' . $e->getMessage());
            }
        };

        flash('success', 'Password berhasil diubah. Silakan masuk dengan password baru.');
        $this->redirectThen('/login', $job);
    }

    // ------------------------------------------------------------------
    // Pembantu
    // ------------------------------------------------------------------

    /** Membuat token reset dan menyiapkan pengiriman link melalui email. */
    private function createResetJob(array $user): ?callable
    {
        $base = $this->publicBaseUrl();
        if ($base === null) {
            error_log('[password-reset] APP_PUBLIC_URL kosong/tidak valid; email reset tidak dikirim.');
            return null;
        }

        $now = time();
        $pdo = db();
        $pdo->prepare('DELETE FROM password_resets WHERE expires_at < ?')
            ->execute([date('Y-m-d H:i:s', $now - 86400)]);
        $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')
            ->execute([(int) $user['id']]);

        $token = bin2hex(random_bytes(32));
        $pdo->prepare('INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (?, ?, ?, ?)')
            ->execute([(int) $user['id'], $token, date('Y-m-d H:i:s', $now + self::TOKEN_LIFETIME), date('Y-m-d H:i:s', $now)]);

        $link = $base . '/reset-password?token=' . $token;
        return function () use ($user, $link): void {
            try {
                EmailService::sendPasswordReset((string) $user['email'], (string) $user['name'], $link, intdiv(self::TOKEN_LIFETIME, 60));
            } catch (Throwable $e) {
                error_log('[password-reset] gagal kirim email ke akun #' . $user['id'] . ': ' . $e->getMessage());
                $this->devLogLink((string) $user['email'], $link);
            }
        };
    }

    /** Token hanya sah kalau persis 64 karakter heksadesimal huruf kecil. */
    private function tokenFrom(mixed $value): ?string
    {
        return is_string($value) && preg_match('/\A[a-f0-9]{64}\z/', $value) === 1 ? $value : null;
    }

    /** Token valid = ada, belum dipakai, belum lewat batas waktu, dan akunnya aktif. */
    private function findValid(string $token): ?array
    {
        $stmt = db()->prepare(
            'SELECT r.id, r.user_id, u.name, u.email
             FROM password_resets r
             JOIN users u ON u.id = r.user_id
             WHERE r.token = ? AND r.used_at IS NULL AND r.expires_at > ? AND u.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([$token, date('Y-m-d H:i:s')]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Halaman "link tidak valid / kedaluwarsa". */
    private function invalid(): never
    {
        while (ob_get_level() > 0) ob_end_clean();
        http_response_code(400);
        view('auth/reset-invalid', ['extraCss' => ['/assets/css/auth.css']]);
        exit;
    }

    /**
     * Link reset ada di alamat URL. Supaya token tidak bocor lewat header Referer
     * ke situs lain (mis. CDN ikon/font) atau tersimpan di cache, kirim dua header ini.
     */
    private function privateHeaders(): void
    {
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store');
    }

    /**
     * Alamat dasar untuk link di email, dari .env (APP_PUBLIC_URL).
     * SENGAJA tidak diambil dari header Host milik browser, karena bisa dipalsukan
     * (password reset poisoning). Kosong/tidak valid => null (email tidak dikirim).
     */
    private function publicBaseUrl(): ?string
    {
        $url = rtrim(trim((string) env('APP_PUBLIC_URL', '')), '/');
        $parts = parse_url($url);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL) || !is_array($parts)
            || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }
        if (env('APP_ENV') === 'production' && $parts['scheme'] !== 'https') return null;
        return $url;
    }

    /**
     * Kirim respons pengalihan SEKARANG, baru setelah itu menjalankan $work (kirim email).
     * Dengan begini, pengguna yang emailnya terdaftar dan yang tidak sama-sama dilayani
     * secepat itu, jadi tidak bisa dibedakan dari lama-tidaknya halaman membalas.
     */
    private function redirectThen(string $path, callable $work): never
    {
        ignore_user_abort(true);
        set_time_limit(60);
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();   // pesan flash sudah tersimpan
        header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)), true, 302);
        header('Connection: close');
        header('Content-Length: 0');
        while (ob_get_level() > 0) ob_end_flush();
        flush();
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        try {
            $work();
        } catch (Throwable $e) {
            error_log('[password-reset] ' . $e->getMessage());
        }
        exit;
    }

    /**
     * Hanya untuk belajar di komputer sendiri (APP_ENV=local): kalau email gagal terkirim
     * (misalnya SMTP belum diisi), link ditulis ke storage/logs/reset-links.log supaya
     * bisa disalin dan dicoba. TIDAK dilakukan di server asli, karena isinya bisa dipakai membajak akun.
     */
    private function devLogLink(string $email, string $link): void
    {
        if (env('APP_ENV') !== 'local') return;
        @file_put_contents(
            BASE_PATH . '/storage/logs/reset-links.log',
            date('Y-m-d H:i:s') . "  {$email}  {$link}\n",
            FILE_APPEND | LOCK_EX
        );
    }

    private function formatWhen(int $timestamp): string
    {
        return (int) date('j', $timestamp) . ' ' . self::BULAN[(int) date('n', $timestamp)] . ' ' . date('Y, H:i T', $timestamp);
    }
}
