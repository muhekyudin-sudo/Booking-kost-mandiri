<?php
  // Ubah ke true setelah fitur "Lupa password" dipasang (alamatnya /lupa-password).
  $forgotEnabled = true;
  // Email yang tadi diketik dikembalikan ke kolom kalau login gagal (opsional, lihat AuthController).
  $oldEmail = (string) (flash('old_email') ?? '');
?>
<section class="auth-card">
  <div class="auth-head">
    <span class="auth-badge" aria-hidden="true">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
        <polyline points="9 12 11 14 15 10"></polyline>
      </svg>
    </span>
    <div>
      <p class="auth-eyebrow">Area penghuni</p>
      <p class="auth-eyebrow-title">Akses akun Anda</p>
    </div>
  </div>

  <h1 class="auth-title">Selamat datang kembali</h1>
  <p class="auth-sub">Silakan masuk untuk melanjutkan ke Kost Mandiri.</p>

  <form method="post" action="<?= e(url('/login')) ?>">
    <?= csrf_field() ?>

    <div class="auth-field">
      <label for="login_email">Email</label>
      <div class="auth-input">
        <i data-feather="mail"></i>
        <input id="login_email" type="email" name="email" value="<?= e($oldEmail) ?>"
               placeholder="nama@email.com" autocomplete="email" maxlength="255" required
               <?= $oldEmail === '' ? 'autofocus' : '' ?>>
      </div>
    </div>

    <div class="auth-field">
      <div class="auth-label-row">
        <label for="login_password">Password</label>
        <?php if ($forgotEnabled): ?>
          <a href="<?= e(url('/lupa-password')) ?>">Lupa password?</a>
        <?php endif; ?>
      </div>
      <div class="auth-input">
        <i data-feather="lock"></i>
        <input id="login_password" type="password" name="password"
               placeholder="Masukkan password" autocomplete="current-password" required
               <?= $oldEmail !== '' ? 'autofocus' : '' ?>>
        <button type="button" class="auth-toggle" data-toggle-password="login_password"
                aria-label="Tampilkan password" aria-pressed="false">
          <span data-eye="show"><i data-feather="eye"></i></span>
          <span data-eye="hide" hidden><i data-feather="eye-off"></i></span>
        </button>
      </div>
    </div>

    <button type="submit" class="auth-submit">Masuk ke akun</button>
  </form>

  <p class="auth-foot">Belum punya akun? <a href="<?= e(url('/register')) ?>">Daftar sebagai penyewa</a></p>
</section>

<script>
  // Tombol mata: tampilkan / sembunyikan password
  document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
    button.addEventListener('click', function () {
      var input = document.getElementById(button.getAttribute('data-toggle-password'));
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      button.querySelector('[data-eye="show"]').hidden = show;
      button.querySelector('[data-eye="hide"]').hidden = !show;
      button.setAttribute('aria-pressed', show ? 'true' : 'false');
      button.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
      input.focus();
    });
  });
</script>