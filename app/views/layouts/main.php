<?php
$u = current_user();

// Tujuan klik logo: admin dan resepsionis ke dashboard-nya, lainnya ke katalog
$logoUrl = '/';
if ($u && $u['role'] === 'admin')        $logoUrl = '/admin';
if ($u && $u['role'] === 'receptionist') $logoUrl = '/resepsionis';
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Kost Mandiri</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(url('/assets/css/base.css')) ?>?v=5">
  <link rel="stylesheet" href="<?= e(url('/assets/css/profile-sidebar.css')) ?>?v=3">
  <?php foreach ((array) ($extraCss ?? []) as $css): ?>
    <link rel="stylesheet" href="<?= e(url($css)) ?>?v=5">
  <?php endforeach; ?>
</head>

<body>
  <header>
    <a class="brand" href="<?= e(url($logoUrl)) ?>" aria-label="Kost Mandiri"><span class="brand-icon"><i data-feather="home"></i></span><strong>Kost Mandiri</strong></a>
    <nav>
      <?php if ($u): ?>
        <?php if ($u['role'] === 'admin'): ?>
          <a class="button secondary nav-dashboard" href="<?= e(url('/admin')) ?>">Dashboard<span class="nav-dashboard-extra"> Admin</span></a>
        <?php endif; ?>
        <button type="button" class="account-trigger" id="account-trigger" aria-haspopup="dialog" aria-controls="account-sidebar" aria-expanded="false">
          <i data-feather="user"></i>
          <span class="account-name"><?= e($u['name']) ?><span class="account-role-inline"> (<?= e($u['role']) ?>)</span></span>
          <i data-feather="chevron-down"></i>
        </button>
      <?php else: ?>
        <a class="button header-login" href="<?= e(url('/login')) ?>">
          <i data-feather="log-in" aria-hidden="true"></i>
          <span>Masuk</span>
        </a>
      <?php endif; ?>
    </nav>
  </header>
  <?php if ($u): ?>
    <div class="account-sidebar-backdrop" id="account-sidebar-backdrop" hidden></div>
    <aside class="account-sidebar" id="account-sidebar" role="dialog" aria-modal="true" aria-labelledby="account-sidebar-title" hidden>
      <button type="button" class="account-sidebar-close" aria-label="Tutup sidebar akun">
        <i data-feather="x"></i>
      </button>

      <div class="account-profile">
        <div class="account-avatar" aria-hidden="true"><i data-feather="user"></i></div>
        <h2 id="account-sidebar-title"><?= e($u['name']) ?></h2>
        <p><?= e($u['email'] ?? ucfirst((string) $u['role'])) ?></p>
        <span class="account-role"><?= e(ucfirst((string) $u['role'])) ?></span>
      </div>

      <ul class="account-menu">
        <?php if ($u['role'] === 'tenant'): ?>
          <li><a href="<?= e(url('/tenant/bookings')) ?>"><i data-feather="calendar" aria-hidden="true"></i><span>Booking Saya</span></a></li>
        <?php endif; ?>
        <li><i data-feather="bell" aria-hidden="true"></i><span>Fitur ini segera hadir</span></li>
        <li><a href="<?= e(url('/accountmenu/security.php')) ?>"><i data-feather="shield" aria-hidden="true"></i><span>Privasi dan keamanan</span></a></li>
        <li><a href="<?= e(url('/accountmenu/settings.php')) ?>"><i data-feather="settings" aria-hidden="true"></i><span>Pengaturan</span></a></li>
        <li>
          <form method="post" action="<?= e(url('/logout')) ?>">
            <?= csrf_field() ?>
            <button type="submit"><i data-feather="log-out" aria-hidden="true"></i><span>Logout</span></button>
          </form>
        </li>
      </ul>

      <div class="account-sidebar-footer">
        <span><i data-feather="info" aria-hidden="true"></i> Pengaturan akun segera hadir</span>
      </div>
    </aside>
  <?php endif; ?>
  <main>
    <?php if ($m = flash('error')): ?><div class="alert error"><?= e($m) ?></div><?php endif; ?>
    <?php if ($m = flash('success')): ?><div class="alert ok"><?= e($m) ?></div><?php endif; ?>
    <?= $content ?>
  </main>
  <footer class="site-footer">
    <div class="site-footer-content">
      <span>&copy; <?= e(date('Y')) ?> Kost Mandiri</span>
      <span>Solusi hunian nyaman dan praktis.</span>
    </div>
  </footer>
  <script src="https://cdn.jsdelivr.net/npm/feather-icons@4.29.2/dist/feather.min.js"></script>
  <script src="<?= e(url('/assets/js/searchable-select.js')) ?>?v=2"></script>
  <?php if ($u): ?>
    <script src="<?= e(url('/assets/js/account-sidebar.js')) ?>?v=1"></script>
  <?php endif; ?>
  <script>
    feather.replace();
  </script>
</body>

</html>