<?php

/** @var string|null $error */
/** @var string $email */
?>
<div class="card narrow">
  <h1>Masuk</h1>
  <?php if (!empty($_SESSION['checkout_intent'])): ?>
    <p class="muted">Login dulu untuk melanjutkan pemesanan kamar Anda.</p>
  <?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Email
      <input type="email" name="email" value="<?= e($email) ?>" required>
    </label>
    <label>Password
      <input type="password" name="password" required>
    </label>
    <button class="btn" type="submit">Masuk</button>
  </form>
  <p class="muted">Belum punya akun? <a href="/register">Daftar</a></p>
</div>