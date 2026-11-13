<section class="page-heading split">
  <div><h1>Booking Saya</h1><p>Riwayat pemesanan dan status pembayaran.</p></div>
  <a class="button secondary" href="<?= e(url('/')) ?>">Kembali ke katalog</a>
</section>
<?php if (!$bookings): ?>
  <div class="card"><p>Belum ada booking.</p></div>
<?php endif; ?>
<div class="stack">
  <?php foreach ($bookings as $booking): ?>
    <article class="card">
      <div class="split">
        <h2><?= e($booking['property_name']) ?> — <?= e($booking['room_class_name']) ?></h2>
        <span class="badge"><?= e(str_replace('_', ' ', ucfirst($booking['status']))) ?></span>
      </div>
      <p>Kode PNR: <strong><?= e($booking['pnr_code']) ?></strong> · Kamar <?= e($booking['room_number']) ?></p>
      <p>Total: Rp <?= e(number_format((float) $booking['total_amount'], 0, ',', '.')) ?></p>
      <a href="<?= e(url('/tenant/bookings/' . (int) $booking['id'])) ?>">Lihat detail booking</a>
    </article>
  <?php endforeach; ?>
</div>
