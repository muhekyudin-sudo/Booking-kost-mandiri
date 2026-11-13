<p><a href="<?= e(url('/tenant/bookings')) ?>">&larr; Kembali ke booking saya</a></p>
<?php
  $payments = $payments ?? [];
  $today = date('Y-m-d');
  $contactMessage = match ($booking['status']) {
      'waiting_approval' => 'Mohon dibantu cek pembayaran booking saya. PNR ' . $booking['pnr_code']
          . ', ' . $booking['property_name'] . ', kamar ' . $booking['room_number'] . '.',
      'active' => 'Saya ingin mengajukan pembatalan booking PNR ' . $booking['pnr_code']
          . ', ' . $booking['property_name'] . ', kamar ' . $booking['room_number'] . '.',
      default => 'Konfirmasi booking PNR ' . $booking['pnr_code'] . ', '
          . $booking['property_name'] . ', kamar ' . $booking['room_number'] . '.',
  };
  $whatsappUrl = owner_whatsapp_url($contactMessage);
  $confirmationUrl = owner_whatsapp_url(
      'Konfirmasi booking PNR ' . $booking['pnr_code'] . ', '
      . $booking['property_name'] . ', kamar ' . $booking['room_number'] . '.'
  );

  $received = in_array($booking['status'], ['active', 'completed'], true);
  $showRemaining = in_array($booking['status'], ['waiting_approval', 'active'], true) && (float) $booking['remaining_amount'] > 0;
  $hasRemaining = $booking['status'] === 'active' && (float) $booking['remaining_amount'] > 0;
  $isOverdue = $hasRemaining && $booking['remaining_due_date'] !== null && $booking['remaining_due_date'] < $today;
  $openSettlement = null;
  foreach ($payments as $pay) {
      if ($pay['payment_type'] === 'pelunasan_dp' && $pay['status'] === 'pending') $openSettlement = $pay;
  }
  $paymentStatusLabels = ['pending' => 'Menunggu verifikasi', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'];

  // Teks WhatsApp menyesuaikan konteks: pelunasan (belum/sudah kirim bukti) atau konfirmasi biasa.
  $bookingInfo = 'PNR ' . $booking['pnr_code'] . ', ' . $booking['property_name'] . ', kamar ' . $booking['room_number'];
  if ($hasRemaining) {
      $remainingText = 'Rp ' . number_format((float) $booking['remaining_amount'], 0, ',', '.');
      $confirmationText = ($openSettlement !== null && $openSettlement['proof_file'] !== null)
          ? 'Mohon dibantu cek bukti pelunasan booking saya. ' . $bookingInfo . ', sisa pembayaran ' . $remainingText . '.'
          : 'Saya ingin membayar sisa pelunasan booking ' . $bookingInfo . ', sisa pembayaran ' . $remainingText . '.';
  } else {
      $confirmationText = 'Konfirmasi booking ' . $bookingInfo . '.';
  }
  $confirmationUrl = owner_whatsapp_url($confirmationText);
?>
<section class="card">
  <div class="split">
    <h1>Booking <?= e($booking['pnr_code']) ?></h1>
    <div>
      <span class="badge"><?= e(str_replace('_', ' ', ucfirst($booking['status']))) ?></span>
      <?php if ($isOverdue): ?><span class="badge danger">Lewat jatuh tempo</span><?php endif; ?>
    </div>
  </div>
  <dl class="details">
    <dt>Gedung</dt><dd><?= e($booking['property_name']) ?></dd>
    <dt>Kamar</dt><dd><?= e($booking['room_class_name']) ?> — <?= e($booking['room_number']) ?></dd>
    <dt>Periode</dt><dd><?= e($booking['start_date']) ?> s.d. <?= e($booking['end_date']) ?></dd>
    <dt>Durasi</dt><dd><?= e($booking['duration_value']) ?> <?= e($booking['duration_type']) ?></dd>
    <dt>Total</dt><dd>Rp <?= e(number_format((float) $booking['total_amount'], 0, ',', '.')) ?></dd>
    <?php if ($received): ?>
      <dt>Sudah dibayar</dt><dd>Rp <?= e(number_format((float) $booking['paid_amount'], 0, ',', '.')) ?></dd>
    <?php else: ?>
      <dt>Tagihan saat ini</dt><dd>Rp <?= e(number_format((float) $booking['payment_amount'], 0, ',', '.')) ?></dd>
    <?php endif; ?>
    <dt>Skema</dt><dd><?= $booking['payment_scheme'] === 'dp' ? 'DP 50%' : 'Lunas' ?></dd>
    <?php if ($showRemaining): ?>
      <dt>Sisa pembayaran</dt><dd>Rp <?= e(number_format((float) $booking['remaining_amount'], 0, ',', '.')) ?></dd>
      <?php if ($booking['remaining_due_date']): ?>
        <dt>Jatuh tempo pelunasan</dt><dd><?= e($booking['remaining_due_date']) ?></dd>
      <?php endif; ?>
    <?php endif; ?>
  </dl>

  <?php if ($booking['status'] === 'pending_payment' && $expiresAt !== null): ?>
    <div class="alert error" id="payment-timer" data-expires-at="<?= e($expiresAt) ?>">
      Selesaikan pembayaran dalam <strong id="timer-value">--:--:--</strong>.
    </div>
    <h2>Pembayaran QRIS</h2>
    <?php if ($qrisImageUrl): ?>
      <img class="qris-image" src="<?= e($qrisImageUrl) ?>" alt="Kode QRIS pembayaran">
    <?php else: ?>
      <p class="alert error">Kode QRIS belum dikonfigurasi. Hubungi pengelola sebelum melakukan pembayaran.</p>
    <?php endif; ?>
    <p>Invoice: <strong><?= e($booking['invoice_code']) ?></strong></p>
    <?php if ($qrisImageUrl): ?>
    <form method="post" enctype="multipart/form-data" action="<?= e(url('/tenant/payments/' . (int) $booking['payment_id'] . '/proof')) ?>">
      <?= csrf_field() ?>
      <label for="proof">Unggah bukti pembayaran (JPG, PNG, WEBP; maksimal 5 MB)</label>
      <input id="proof" type="file" name="proof" accept="image/jpeg,image/png,image/webp" required>
      <button type="submit">Kirim bukti pembayaran</button>
    </form>
    <?php endif; ?>
  <?php elseif ($booking['status'] === 'waiting_approval'): ?>
    <p class="alert ok">Bukti diterima. Menunggu verifikasi resepsionis gedung.</p>
    <?php if ($whatsappUrl): ?><p><a class="button" href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener">Ingatkan pemilik via WhatsApp</a></p><?php endif; ?>
  <?php elseif ($booking['status'] === 'expired'): ?>
    <p class="alert error">Batas waktu pembayaran telah habis. Kamar sudah dilepas.</p>
  <?php endif; ?>

  <?php if ($payments && in_array($booking['status'], ['waiting_approval', 'active', 'completed', 'cancelled'], true)): ?>
    <h2>Riwayat pembayaran</h2>
    <dl class="details">
      <?php foreach ($payments as $pay): ?>
        <?php
          $payLabel = ($pay['status'] === 'pending' && $pay['proof_file'] === null)
              ? 'Menunggu bukti'
              : ($paymentStatusLabels[$pay['status']] ?? (string) $pay['status']);
        ?>
        <dt>
          <?= e(BookingService::paymentTypeLabel((string) $pay['payment_type'])) ?><br>
          <span class="badge"><?= e($payLabel) ?></span>
        </dt>
        <dd>
          Rp <?= e(number_format((float) $pay['amount'], 0, ',', '.')) ?> · <?= $pay['payment_method'] === 'cash' ? 'Tunai' : 'QRIS' ?>
          <?php if ($pay['proof_file']): ?>
            · <a href="<?= e(url('/files/bukti/' . (int) $pay['id'])) ?>" target="_blank" rel="noopener">Lihat bukti bayar</a>
          <?php endif; ?>
          <?php if ($pay['rejection_reason']): ?>
            <br><span class="hint">Alasan penolakan: <?= e($pay['rejection_reason']) ?></span>
          <?php endif; ?>
        </dd>
      <?php endforeach; ?>
    </dl>
  <?php endif; ?>

  <?php if ($hasRemaining): ?>
    <h2>Pelunasan</h2>
    <?php if ($isOverdue): ?>
      <p class="alert error">Pelunasan sudah melewati jatuh tempo (<?= e($booking['remaining_due_date']) ?>). Segera lunasi atau hubungi pemilik.</p>
    <?php endif; ?>
    <?php if ($openSettlement === null): ?>
      <p>Sisa pembayaran Rp <?= e(number_format((float) $booking['remaining_amount'], 0, ',', '.')) ?> dapat dibayar lewat QRIS.</p>
      <form method="post" action="<?= e(url('/tenant/bookings/' . (int) $booking['id'] . '/settlement')) ?>">
        <?= csrf_field() ?>
        <button type="submit">Bayar pelunasan</button>
      </form>
    <?php elseif ($openSettlement['proof_file'] === null): ?>
      <p>Bayar <strong>Rp <?= e(number_format((float) $openSettlement['amount'], 0, ',', '.')) ?></strong> lewat QRIS, lalu unggah bukti pembayaran.</p>
      <?php if ($qrisImageUrl): ?>
        <img class="qris-image" src="<?= e($qrisImageUrl) ?>" alt="Kode QRIS pembayaran">
        <p>Invoice: <strong><?= e($openSettlement['invoice_code']) ?></strong></p>
        <form method="post" enctype="multipart/form-data" action="<?= e(url('/tenant/payments/' . (int) $openSettlement['id'] . '/proof')) ?>">
          <?= csrf_field() ?>
          <label for="proof">Unggah bukti pelunasan (JPG, PNG, WEBP; maksimal 5 MB)</label>
          <input id="proof" type="file" name="proof" accept="image/jpeg,image/png,image/webp" required>
          <button type="submit">Kirim bukti pelunasan</button>
        </form>
      <?php else: ?>
        <p class="alert error">Kode QRIS belum dikonfigurasi. Hubungi pengelola sebelum melakukan pembayaran.</p>
      <?php endif; ?>
    <?php else: ?>
      <p class="alert ok">Bukti pelunasan diterima. Menunggu verifikasi resepsionis gedung.</p>
      <?php if ($confirmationUrl): ?><p><a class="button" href="<?= e($confirmationUrl) ?>" target="_blank" rel="noopener">Ingatkan pemilik via WhatsApp</a></p><?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($booking['status'] === 'active' && $whatsappUrl): ?>
    <p><a class="button" href="<?= e($confirmationUrl) ?>" target="_blank" rel="noopener"><?= $hasRemaining ? 'Hubungi pemilik soal pelunasan via WhatsApp' : 'Konfirmasi booking via WhatsApp' ?></a></p>
    <p><a class="button secondary" href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener">Ajukan pembatalan via WhatsApp</a></p>
    <p class="hint">Pengembalian dana (jika ada) diproses manual di luar sistem.</p>
  <?php elseif ($booking['status'] === 'pending_payment' && $whatsappUrl): ?>
    <p><a class="button secondary" href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener">Hubungi pemilik tentang booking</a></p>
  <?php endif; ?>
  <?php if (!$whatsappUrl): ?><p class="hint">WhatsApp pemilik belum dikonfigurasi.</p><?php endif; ?>
</section>
<?php if ($expiresAt !== null && $booking['status'] === 'pending_payment'): ?>
<script>
(() => {
  const timer = document.getElementById('payment-timer');
  const output = document.getElementById('timer-value');
  const expiresAt = Number(timer.dataset.expiresAt);
  let reloading = false;
  const tick = () => {
    const remaining = Math.max(0, Math.floor((expiresAt - Date.now()) / 1000));
    const hours = String(Math.floor(remaining / 3600)).padStart(2, '0');
    const minutes = String(Math.floor((remaining % 3600) / 60)).padStart(2, '0');
    const seconds = String(remaining % 60).padStart(2, '0');
    output.textContent = `${hours}:${minutes}:${seconds}`;
    if (remaining === 0 && !reloading) {
      reloading = true;
      window.setTimeout(() => window.location.reload(), 1000);
    }
  };
  tick();
  window.setInterval(tick, 1000);
})();
</script>
<?php endif; ?>