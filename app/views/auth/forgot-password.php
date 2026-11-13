<section class="auth-card">
  <div class="auth-head">
    <span class="auth-badge" aria-hidden="true">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path>
      </svg>
    </span>
    <div>
      <p class="auth-eyebrow">Pemulihan akun</p>
      <p class="auth-eyebrow-title">Atur ulang password</p>
    </div>
  </div>

  <h1 class="auth-title">Lupa password?</h1>
  <p class="auth-sub">Masukkan email akun Anda. Kami akan mengirim link untuk membuat password baru. Link berlaku 15 menit.</p>

  <form method="post" action="<?= e(url('/lupa-password')) ?>">
    <?= csrf_field() ?>

    <div class="auth-field">
      <label for="forgot_email">Email</label>
      <div class="auth-input">
        <i data-feather="mail"></i>
        <input id="forgot_email" type="email" name="email" placeholder="nama@email.com"
               autocomplete="email" maxlength="255" required autofocus>
      </div>
    </div>

    <button type="submit" class="auth-submit">Kirim link reset</button>
  </form>

  <p class="auth-foot">Ingat password Anda? <a href="<?= e(url('/login')) ?>">Masuk</a></p>
</section>
