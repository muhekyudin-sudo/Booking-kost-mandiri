<?php
final class AuthController
{
    public function showLogin(array $p): void
    {
        if ($u = current_user()) $this->redirectAfterLogin($u['role']);
        view('auth/login', ['extraCss' => ['/assets/css/auth.css']]);
    }

    public function showRegister(array $p): void
    {
        if ($u = current_user()) $this->redirectAfterLogin($u['role']);
        view('auth/register', ['extraCss' => ['/assets/css/auth.css']]);
    }

    public function login(array $p): void
    {
        $errors = validate($_POST, ['email' => 'required|email', 'password' => 'required']);
        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('/login');
        }

        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([trim($_POST['email'])]);
        $user = $stmt->fetch();

        if (
            !$user || !$user['password'] || strlen((string) $_POST['password']) > 72 || !(int) $user['is_active']
            || !password_verify((string) $_POST['password'], $user['password'])
        ) {
            flash('old_email', trim((string) $_POST['email']));
            flash('error', 'Email atau password salah.');
            redirect('/login');
        }
        login_user($user);
        $this->redirectAfterLogin($user['role']);
    }

    public function register(array $p): void
    {
        $errors = validate($_POST, [
            'name' => 'required|max:255',
            'email' => 'required|email',
            'phone' => 'required|max:20',
        ]);
        $password = $_POST['password'] ?? null;
        $confirmation = $_POST['password_confirmation'] ?? null;
        if ($errors) {
            $this->failRegister(implode(' ', $errors));
        }
        if (strlen(trim((string) $_POST['phone'])) > 20) {
            $this->failRegister('Nomor telepon maksimal 20 byte.');
        }
        if (!is_string($password) || mb_strlen($password) < 8) {
            $this->failRegister('Password wajib diisi dan minimal 8 karakter.');
        }
        if (strlen($password) > 72) {
            $this->failRegister('Password maksimal 72 byte.');
        }
        if (!is_string($confirmation) || !hash_equals($password, $confirmation)) {
            $this->failRegister('Konfirmasi password tidak cocok.');
        }

        $name = trim((string) $_POST['name']);
        $email = mb_strtolower(trim((string) $_POST['email']));
        $phone = trim((string) $_POST['phone']);
        $hash = password_hash($password, PASSWORD_BCRYPT);
        if ($hash === false) throw new RuntimeException('Password could not be hashed.');

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $existing = $pdo->prepare('SELECT id, role, password, is_active FROM users WHERE email = ? FOR UPDATE');
            $existing->execute([$email]);
            $account = $existing->fetch();
            if ($account) {
                if ($account['role'] !== 'tenant' || $account['password'] !== null || !(int) $account['is_active']) {
                    $pdo->rollBack();
                    $this->failRegister('Email sudah digunakan. Silakan masuk dengan akun tersebut.');
                }
                $stmt = $pdo->prepare(
                    "UPDATE users SET name = ?, phone = ?, password = ?
                     WHERE id = ? AND role = 'tenant' AND password IS NULL AND is_active = 1"
                );
                $stmt->execute([$name, $phone, $hash, (int) $account['id']]);
                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('Walk-in tenant account could not be claimed.');
                }
                $userId = (int) $account['id'];
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO users (name, email, phone, password, role, is_active) VALUES (?, ?, ?, ?, 'tenant', 1)"
                );
                $stmt->execute([$name, $email, $phone, $hash]);
                $userId = (int) $pdo->lastInsertId();
            }
            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e->getCode() !== '23000') throw $e;
            error_log('Tenant registration rejected by a database constraint: ' . $e->getMessage());
            $this->failRegister('Email sudah digunakan atau data pendaftaran tidak valid.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) throw new RuntimeException('New tenant account could not be loaded after registration.');

        login_user($user);
        $this->redirectAfterLogin($user['role']);
    }

    /** Gagal mendaftar: ingat isian (kecuali password), tampilkan pesan, kembali ke form. */
    private function failRegister(string $message): never
    {
        flash('reg_name', trim((string) ($_POST['name'] ?? '')));
        flash('reg_email', trim((string) ($_POST['email'] ?? '')));
        flash('reg_phone', trim((string) ($_POST['phone'] ?? '')));
        flash('error', $message);
        redirect('/register');
    }

    private function redirectAfterLogin(string $role): never
    {
        $unitId = filter_var($_SESSION['booking_intent'] ?? null, FILTER_VALIDATE_INT);
        unset($_SESSION['booking_intent']);
        if ($role === 'tenant' && $unitId !== false && $unitId !== null && $unitId > 0) {
            redirect('/booking/checkout/' . $unitId);
        }
        redirect(home_for_role($role));
    }

    public function logout(array $p): void
    {
        logout_user();
        redirect('/login');
    }
}