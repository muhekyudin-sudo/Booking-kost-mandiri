<?php
$roleLabels = [
  'tenant'       => 'Penyewa',
  'receptionist' => 'Resepsionis',
  'admin'        => 'Admin',
];
$roleLabel = $roleLabels[$account['role']] ?? ucfirst((string) $account['role']);
$joined = $account['created_at'] ? date('d/m/Y', strtotime((string) $account['created_at'])) : '-';
$phoneRequired = $account['role'] === 'tenant';
?>
<section class="page-heading">
  <div>
    <h1>Pengaturan</h1>
    <p>Kelola informasi profil akun Anda.</p>
  </div>
</section>

<div class="settings-grid">
  <section class="card settings-card" aria-labelledby="profile-title">
    <h2 id="profile-title">Profil</h2>
    <form class="settings-form" method="post" action="<?= e(url('/accountmenu/settings/profile')) ?>">
      <?= csrf_field() ?>

      <label for="name">Nama</label>
      <input id="name" type="text" name="name" value="<?= e($oldName) ?>"
             autocomplete="name" maxlength="255" required>

      <label for="phone">Nomor telepon</label>
      <input id="phone" type="tel" inputmode="tel" name="phone" value="<?= e($oldPhone) ?>"
             autocomplete="tel" maxlength="20" placeholder="081234567890"
             <?= $phoneRequired ? 'required' : '' ?>>
      <p class="hint">
        Contoh: 081234567890 atau +6281234567890.
        <?= $phoneRequired ? '' : 'Boleh dikosongkan.' ?>
      </p>

      <button type="submit">Simpan perubahan</button>
    </form>
  </section>

  <section class="card settings-card" aria-labelledby="info-title">
    <h2 id="info-title">Informasi akun</h2>
    <dl class="info-list">
      <div>
        <dt>Email</dt>
        <dd><?= e($account['email']) ?></dd>
      </div>
      <div>
        <dt>Peran</dt>
        <dd><?= e($roleLabel) ?></dd>
      </div>
      <?php if ($account['role'] === 'receptionist' && !empty($account['property_name'])): ?>
        <div>
          <dt>Gedung</dt>
          <dd><?= e($account['property_name']) ?></dd>
        </div>
      <?php endif; ?>
      <div>
        <dt>Bergabung sejak</dt>
        <dd><?= e($joined) ?></dd>
      </div>
    </dl>
    <p class="hint">Email dipakai untuk login dan tidak dapat diubah di sini.</p>
  </section>
</div>
