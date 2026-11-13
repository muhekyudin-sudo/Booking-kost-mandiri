<div class="card">
  <h2><?= e($title) ?></h2>
  <p>Login sebagai <strong><?= e(current_user()['name']) ?></strong>
     <?php if (current_user()['property_id']): ?>
       | property_id: <?= e(current_user()['property_id']) ?>
     <?php endif; ?></p>
  <?php if (current_user()['role'] === 'admin'): ?>
    <h3>Master Data</h3>
    <p><a href="<?= e(url('/admin/master/cities')) ?>">Kelola Kota, Gedung, Kelas Kamar, Unit, Harga, Resepsionis, dan Petugas Jaga</a></p>
    <p><a href="<?= e(url('/admin/bookings')) ?>">Lihat transaksi booking dan pembayaran (read-only)</a></p>
  <?php elseif (current_user()['role'] === 'receptionist'): ?>
    <p>Petugas jaga: <strong><?= e($_SESSION['active_staff_name'] ?? '') ?></strong></p>
    <p>
      <a href="<?= e(url('/resepsionis/bookings')) ?>">Booking gedung</a>
      <?php if (($pendingApprovals ?? 0) > 0): ?>
        <span class="badge unavailable"><?= e($pendingApprovals) ?> menunggu verifikasi</span>
      <?php endif; ?>
    </p>
    <p><a href="<?= e(url('/resepsionis/kasir')) ?>">Kasir tunai / walk-in</a></p>
    <form method="post" action="<?= e(url('/resepsionis/ganti-petugas')) ?>">
      <?= csrf_field() ?>
      <button type="submit">Ganti Petugas</button>
    </form>
  <?php endif; ?>
</div>
