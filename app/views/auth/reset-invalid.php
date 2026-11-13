<section class="auth-card">
  <div class="auth-head">
    <span class="auth-badge" aria-hidden="true">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"></circle>
        <line x1="12" y1="8" x2="12" y2="12"></line>
        <line x1="12" y1="16" x2="12.01" y2="16"></line>
      </svg>
    </span>
    <div>
      <p class="auth-eyebrow">Pemulihan akun</p>
      <p class="auth-eyebrow-title">Link tidak bisa dipakai</p>
    </div>
  </div>

  <h1 class="auth-title">Link tidak valid atau sudah kedaluwarsa</h1>
  <p class="auth-sub">Link reset password hanya berlaku 15 menit dan hanya bisa dipakai satu kali. Silakan minta link yang baru.</p>

  <form method="get" action="<?= e(url('/lupa-password')) ?>">
    <button type="submit" class="auth-submit">Minta link baru</button>
  </form>

  <p class="auth-foot"><a href="<?= e(url('/login')) ?>">Kembali ke login</a></p>
</section>
