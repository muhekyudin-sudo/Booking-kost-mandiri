<section class="page-heading">
  <div>
    <h1>Dashboard Resepsionis</h1>
    <p><?= e($stats['gedung']) ?> · Petugas jaga: <strong><?= e($_SESSION['active_staff_name'] ?? '') ?></strong></p>
  </div>
  <form method="post" action="<?= e(url('/resepsionis/ganti-petugas')) ?>" style="margin:0">
    <?= csrf_field() ?>
    <button type="submit" class="button secondary"><i data-feather="repeat"></i> Ganti Petugas</button>
  </form>
</section>

<div class="dash-stats">
  <div class="stat">
    <span class="stat-icon"><i data-feather="key"></i></span>
    <div><small>Unit tersedia</small><strong><?= e($stats['tersedia']) ?></strong></div>
  </div>
  <div class="stat">
    <span class="stat-icon"><i data-feather="user-check"></i></span>
    <div><small>Unit terisi</small><strong><?= e($stats['terisi']) ?></strong></div>
  </div>
  <div class="stat">
    <span class="stat-icon"><i data-feather="clock"></i></span>
    <div><small>Menunggu verifikasi</small><strong><?= e($stats['menunggu']) ?></strong></div>
  </div>
  <div class="stat">
    <span class="stat-icon"><i data-feather="check-circle"></i></span>
    <div><small>Booking aktif</small><strong><?= e($stats['aktif']) ?></strong></div>
  </div>
</div>

<h2 class="dash-title">Menu cepat</h2>

<div class="menu-grid">
  <a class="menu-card" href="<?= e(url('/resepsionis/bookings')) ?>">
    <span class="stat-icon"><i data-feather="clipboard"></i></span>
    <div>
      <h3>
        Booking Gedung
        <?php if ((int) $stats['menunggu'] > 0): ?>
          <span class="badge unavailable"><?= e($stats['menunggu']) ?> menunggu verifikasi</span>
        <?php endif; ?>
        <?php if ((int) $stats['lewat_tempo'] > 0): ?>
          <span class="badge danger"><?= e($stats['lewat_tempo']) ?> lewat jatuh tempo</span>
        <?php endif; ?>
      </h3>
      <p>Cek booking masuk, verifikasi pembayaran, dan batalkan booking.</p>
    </div>
    <i data-feather="chevron-right" class="arrow"></i>
  </a>

  <a class="menu-card" href="<?= e(url('/resepsionis/kasir')) ?>">
    <span class="stat-icon"><i data-feather="dollar-sign"></i></span>
    <div>
      <h3>Kasir tunai / walk-in</h3>
      <p>Buat transaksi untuk penyewa yang datang langsung.</p>
    </div>
    <i data-feather="chevron-right" class="arrow"></i>
  </a>
</div>
