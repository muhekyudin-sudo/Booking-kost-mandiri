<?php

/** @var array $errors */
/** @var array $old */
?>
<div class="card narrow">
  <h1>Daftar Akun</h1>
  <form method="post">
    <?= csrf_field() ?>
    <label>Nama lengkap
      <input type="text" name="name" value="<?= e($old['name']) ?>" required>
      <?php if (isset($errors['name'])): ?><small class="err"><?= e($errors['name']) ?></small><?php endif; ?>
    </label>
    <label>Email
      <input type="email" name="email" value="<?= e($old['email']) ?>" required>
      <?php if (isset($errors['email'])): ?><small class="err"><?= e($errors['email']) ?></small><?php endif; ?>
    </label>
    <label>No. HP / WhatsApp
      <input type="tel" name="phone" value="<?= e($old['phone']) ?>" required>
      <?php if (isset($errors['phone'])): ?><small class="err"><?= e($errors['phone']) ?></small><?php endif; ?>
    </label>
    <label>Password (min. 8 karakter)
      <input type="password" name="password" required>
      <?php if (isset($errors['password'])): ?><small class="err"><?= e($errors['password']) ?></small><?php endif; ?>
    </label>
    <label>Ulangi password
      <input type="password" name="password_confirmation" required>
      <?php if (isset($errors['password_confirmation'])): ?><small class="err"><?= e($errors['password_confirmation']) ?></small><?php endif; ?>
    </label>
    <button class="btn" type="submit">Daftar</button>
  </form>
  <p class="muted">Sudah punya akun? <a href="/login">Masuk</a></p>
</div>