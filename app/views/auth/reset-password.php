<section class="auth-card">
  <div class="auth-head">
    <span class="auth-badge" aria-hidden="true">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
      </svg>
    </span>
    <div>
      <p class="auth-eyebrow">Pemulihan akun</p>
      <p class="auth-eyebrow-title">Buat password baru</p>
    </div>
  </div>

  <h1 class="auth-title">Password baru</h1>
  <p class="auth-sub">Pilih password baru untuk akun Anda. Gunakan kombinasi yang belum pernah dipakai di tempat lain.</p>

  <form method="post" action="<?= e(url('/reset-password')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">

    <div class="auth-field">
      <label for="password">Password baru</label>
      <div class="auth-input">
        <i data-feather="lock"></i>
        <input id="password" type="password" name="password" placeholder="Minimal 8 karakter"
               autocomplete="new-password" minlength="8" maxlength="72" required autofocus>
        <button type="button" class="auth-toggle" data-toggle-password="password"
                aria-label="Tampilkan password" aria-pressed="false">
          <span data-eye="show"><i data-feather="eye"></i></span>
          <span data-eye="hide" hidden><i data-feather="eye-off"></i></span>
        </button>
      </div>
      <p class="auth-hint">Gunakan minimal 8 karakter.</p>
    </div>

    <div class="auth-field">
      <label for="password_confirmation">Konfirmasi password baru</label>
      <div class="auth-input">
        <i data-feather="lock"></i>
        <input id="password_confirmation" type="password" name="password_confirmation" placeholder="Ulangi password baru"
               autocomplete="new-password" minlength="8" maxlength="72" required>
        <button type="button" class="auth-toggle" data-toggle-password="password_confirmation"
                aria-label="Tampilkan password" aria-pressed="false">
          <span data-eye="show"><i data-feather="eye"></i></span>
          <span data-eye="hide" hidden><i data-feather="eye-off"></i></span>
        </button>
      </div>
    </div>

    <button type="submit" class="auth-submit">Simpan password baru</button>
  </form>

  <p class="auth-foot"><a href="<?= e(url('/login')) ?>">Kembali ke login</a></p>
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
