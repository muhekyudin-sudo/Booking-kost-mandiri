<?php
  // Isian tadi dikembalikan ke kolom kalau pendaftaran gagal (lihat AuthController::failRegister).
  $oldName  = (string) (flash('reg_name') ?? '');
  $oldEmail = (string) (flash('reg_email') ?? '');
  $oldPhone = (string) (flash('reg_phone') ?? '');
  // Kalau 3 kolom pertama sudah terisi, kursor langsung ke password.
  $focusPassword = $oldName !== '' && $oldEmail !== '' && $oldPhone !== '';
?>
<section class="auth-card">
  <div class="auth-head">
    <span class="auth-badge" aria-hidden="true">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
        <circle cx="8.5" cy="7" r="4"></circle>
        <line x1="20" y1="8" x2="20" y2="14"></line>
        <line x1="23" y1="11" x2="17" y2="11"></line>
      </svg>
    </span>
    <div>
      <p class="auth-eyebrow">Area penghuni</p>
      <p class="auth-eyebrow-title">Buat akun baru</p>
    </div>
  </div>

  <h1 class="auth-title">Daftar sebagai penyewa</h1>
  <p class="auth-sub">Isi data berikut untuk mulai memesan kamar di Kost Mandiri.</p>

  <form id="register_form" method="post" action="<?= e(url('/register')) ?>">
    <?= csrf_field() ?>

    <div class="auth-field">
      <label for="name">Nama lengkap</label>
      <div class="auth-input">
        <i data-feather="user"></i>
        <input id="name" type="text" name="name" value="<?= e($oldName) ?>"
               placeholder="Nama sesuai identitas" autocomplete="name" maxlength="255" required
               <?= $focusPassword ? '' : 'autofocus' ?>>
      </div>
    </div>

    <div class="auth-field">
      <label for="email">Email</label>
      <div class="auth-input">
        <i data-feather="mail"></i>
        <input id="email" type="email" name="email" value="<?= e($oldEmail) ?>"
               placeholder="nama@email.com" autocomplete="email" maxlength="255" required>
      </div>
    </div>

    <div class="auth-field">
      <label for="phone">Nomor telepon</label>
      <div class="auth-input">
        <i data-feather="phone"></i>
        <input id="phone" type="tel" name="phone" value="<?= e($oldPhone) ?>"
               placeholder="08xxxxxxxxxx" autocomplete="tel" inputmode="tel" maxlength="20" required>
      </div>
    </div>

    <div class="auth-field">
      <label for="password">Password</label>
      <div class="auth-input">
        <i data-feather="lock"></i>
        <input id="password" type="password" name="password" placeholder="Minimal 8 karakter"
               autocomplete="new-password" minlength="8" maxlength="72" required
               <?= $focusPassword ? 'autofocus' : '' ?>>
        <button type="button" class="auth-toggle" data-toggle-password="password"
                aria-label="Tampilkan password" aria-pressed="false">
          <span data-eye="show"><i data-feather="eye"></i></span>
          <span data-eye="hide" hidden><i data-feather="eye-off"></i></span>
        </button>
      </div>
      <p class="auth-hint">Gunakan minimal 8 karakter.</p>
    </div>

    <div class="auth-field">
      <label for="password_confirmation">Konfirmasi password</label>
      <div class="auth-input">
        <i data-feather="lock"></i>
        <input id="password_confirmation" type="password" name="password_confirmation"
               placeholder="Ulangi password" autocomplete="new-password" minlength="8" maxlength="72" required>
        <button type="button" class="auth-toggle" data-toggle-password="password_confirmation"
                aria-label="Tampilkan password" aria-pressed="false">
          <span data-eye="show"><i data-feather="eye"></i></span>
          <span data-eye="hide" hidden><i data-feather="eye-off"></i></span>
        </button>
      </div>
    </div>

    <button type="submit" class="auth-submit">Buat akun</button>
  </form>

  <p class="auth-foot">Sudah punya akun? <a href="<?= e(url('/login')) ?>">Masuk</a></p>
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

  // Cek konfirmasi password langsung di browser (server tetap memeriksa lagi)
  (function () {
    var first = document.getElementById('password');
    var second = document.getElementById('password_confirmation');
    function check() {
      second.setCustomValidity(second.value !== '' && first.value !== second.value
        ? 'Konfirmasi password tidak cocok.' : '');
    }
    first.addEventListener('input', check);
    second.addEventListener('input', check);
  })();
</script>