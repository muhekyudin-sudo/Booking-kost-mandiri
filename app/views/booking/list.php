<?php $today = date('Y-m-d'); ?>
<section class="page-heading split">
  <div><h1>Booking Saya</h1><p>Riwayat pemesanan dan status pembayaran.</p></div>
  <a class="button secondary" href="<?= e(url('/')) ?>">Kembali ke katalog</a>
</section>
<?php if (!$bookings): ?>
  <div class="card"><p>Belum ada booking.</p></div>
<?php endif; ?>
<div class="stack">
  <?php foreach ($bookings as $booking): ?>
    <?php
      $hasRemaining = in_array($booking['status'], ['waiting_approval', 'active'], true) && (float) $booking['remaining_amount'] > 0;
      $isOverdue = $booking['status'] === 'active' && $hasRemaining
          && $booking['remaining_due_date'] !== null && $booking['remaining_due_date'] < $today;
    ?>
    <article class="card">
      <div class="split">
        <h2><?= e($booking['property_name']) ?> — <?= e($booking['room_class_name']) ?></h2>
        <span>
          <span class="badge"><?= e(str_replace('_', ' ', ucfirst($booking['status']))) ?></span>
          <?php if ($isOverdue): ?><span class="badge danger">Lewat jatuh tempo</span><?php endif; ?>
        </span>
      </div>
      <p>Kode PNR: <strong><?= e($booking['pnr_code']) ?></strong> · Kamar <?= e($booking['room_number']) ?></p>
      <p>Total: Rp <?= e(number_format((float) $booking['total_amount'], 0, ',', '.')) ?></p>
      <?php if ($hasRemaining): ?>
        <p>Sisa pembayaran: Rp <?= e(number_format((float) $booking['remaining_amount'], 0, ',', '.')) ?><?php if ($booking['remaining_due_date'] !== null): ?> · jatuh tempo <?= e($booking['remaining_due_date']) ?><?php endif; ?></p>
      <?php endif; ?>
      <a href="<?= e(url('/tenant/bookings/' . (int) $booking['id'])) ?>">Lihat detail booking</a>
    </article>
  <?php endforeach; ?>
</div>
