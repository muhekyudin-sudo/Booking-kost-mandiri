<?php
$paymentsByBooking = $paymentsByBooking ?? [];
$today = date('Y-m-d');
$baseUrl = rtrim((string) env('APP_PUBLIC_URL', ''), '/');
$paymentStatusLabels = ['pending' => 'Menunggu verifikasi', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'];
?>
<a class="back-link" href="<?= e(url($isAdmin ? '/admin' : '/resepsionis')) ?>"><i data-feather="arrow-left"></i> Kembali ke dashboard</a>


<section class="page-heading split">
  <div>
    <h1><?= $isAdmin ? 'Transaksi Semua Gedung' : 'Booking Gedung' ?></h1>
    <?php if (!$isAdmin): ?><p>Petugas jaga: <strong><?= e($staffName) ?></strong></p><?php endif; ?>
  </div>

</section>
<section class="card">
  <form method="get" action="<?= e(url($isAdmin ? '/admin/bookings' : '/resepsionis/bookings')) ?>" class="filters">
    <label for="pnr">Cari PNR</label>
    <input id="pnr" name="pnr" value="<?= e($search) ?>" maxlength="6" pattern="[23456789A-HJ-NP-Z]{6}" placeholder="6 karakter" autocomplete="off">
    <div class="actions"><button type="submit">Cari</button></div>
  </form>
  <?php if (!$isAdmin): ?>
    <p><a class="button" href="<?= e(url('/resepsionis/kasir')) ?>">Buat transaksi kasir tunai</a></p>
  <?php endif; ?>
</section>
<?php if (!$bookings): ?>
  <div class="card">
    <p>Tidak ada booking yang cocok.</p>
  </div>
<?php endif; ?>
<div class="stack">
  <?php foreach ($bookings as $booking): ?>
    <?php
      $payments = $paymentsByBooking[(int) $booking['id']] ?? [];
      $status = (string) $booking['booking_status'];
      $reviewable = in_array($status, ['waiting_approval', 'active'], true);
      $received = in_array($status, ['active', 'completed'], true);
      $hasRemaining = $reviewable && (float) $booking['remaining_amount'] > 0;
      $isOverdue = $status === 'active' && (float) $booking['remaining_amount'] > 0
          && $booking['remaining_due_date'] !== null && $booking['remaining_due_date'] < $today;
      $hasPendingProof = false;
      foreach ($payments as $pay) {
          if ($pay['status'] === 'pending' && $pay['proof_file'] !== null) $hasPendingProof = true;
      }
      $reminderUrl = null;
      if (!$isAdmin && $status === 'active' && $hasRemaining && !$hasPendingProof) {
          $reminderText = 'Halo ' . $booking['tenant_name'] . ', DP booking PNR ' . $booking['pnr_code']
              . ' (' . $booking['property_name'] . ', kamar ' . $booking['room_number'] . ') sudah kami terima. '
              . 'Sisa pembayaran Rp ' . number_format((float) $booking['remaining_amount'], 0, ',', '.')
              . ($booking['remaining_due_date'] !== null ? ' paling lambat ' . $booking['remaining_due_date'] : '')
              . ($isOverdue ? ' (sudah lewat jatuh tempo)' : '')
              . '. ' . ($baseUrl !== ''
                  ? 'Silakan bayar lewat: ' . $baseUrl . '/tenant/bookings/' . (int) $booking['id']
                  : 'Silakan masuk ke akun Kost Mandiri Anda untuk membayar.');
          $reminderUrl = tenant_whatsapp_url($booking['tenant_phone'] ?? null, $reminderText);
      }
    ?>
    <article class="card">
      <div class="split">
        <div>
          <h2>
            PNR <?= e($booking['pnr_code']) ?>
            <span class="badge"><?= e(str_replace('_', ' ', ucfirst($status))) ?></span>
            <?php if ($isOverdue): ?><span class="badge danger">Lewat jatuh tempo</span><?php endif; ?>
          </h2>
          <p><?= e($booking['property_name']) ?> — <?= e($booking['room_class_name']) ?>, kamar <?= e($booking['room_number']) ?></p>
        </div>
        <span class="badge"><?= $booking['booking_type'] === 'offline' ? 'Kasir tunai' : 'Booking online' ?></span>
      </div>
      <dl class="details">
        <dt>Penyewa</dt>
        <dd><?= e($booking['tenant_name']) ?> (<?= e($booking['tenant_email']) ?>)</dd>
        <?php if (!$isAdmin && !empty($booking['tenant_phone'])): ?>
          <dt>Telepon</dt>
          <dd><?= e($booking['tenant_phone']) ?></dd>
        <?php endif; ?>
        <dt>Periode</dt>
        <dd><?= e($booking['start_date']) ?> s.d. <?= e($booking['end_date']) ?></dd>
        <dt>Total</dt>
        <dd>Rp <?= e(number_format((float) $booking['total_amount'], 0, ',', '.')) ?> · <?= $received ? 'diterima' : 'tagihan pertama' ?> Rp <?= e(number_format((float) $booking['paid_amount'], 0, ',', '.')) ?></dd>
        <?php if ($hasRemaining): ?>
          <dt>Sisa pembayaran</dt>
          <dd>Rp <?= e(number_format((float) $booking['remaining_amount'], 0, ',', '.')) ?></dd>
          <?php if ($booking['remaining_due_date'] !== null): ?>
            <dt>Jatuh tempo</dt>
            <dd><?= e($booking['remaining_due_date']) ?></dd>
          <?php endif; ?>
        <?php endif; ?>
      </dl>

      <?php if ($payments): ?>
        <h3>Riwayat pembayaran</h3>
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
              <?php if ($pay['approved_at'] && $pay['staff_name']): ?>
                <br><span class="hint">Diproses petugas <?= e($pay['staff_name']) ?></span>
              <?php endif; ?>
              <?php if ($pay['rejection_reason']): ?>
                <br><span class="hint">Alasan penolakan: <?= e($pay['rejection_reason']) ?></span>
              <?php endif; ?>
            </dd>
          <?php endforeach; ?>
        </dl>
      <?php endif; ?>

      <?php if (!$isAdmin && $reviewable): ?>
        <?php foreach ($payments as $pay): ?>
          <?php if ($pay['status'] !== 'pending' || $pay['proof_file'] === null) continue; ?>
          <p><strong>Verifikasi <?= e(BookingService::paymentTypeLabel((string) $pay['payment_type'])) ?> — Rp <?= e(number_format((float) $pay['amount'], 0, ',', '.')) ?></strong></p>
          <div class="split">
            <form method="post" action="<?= e(url('/resepsionis/payments/' . (int) $pay['id'] . '/approve')) ?>">
              <?= csrf_field() ?>
              <button type="submit">Setujui pembayaran</button>
            </form>
            <form method="post" action="<?= e(url('/resepsionis/payments/' . (int) $pay['id'] . '/reject')) ?>">
              <?= csrf_field() ?>
              <label for="reason-<?= e($pay['id']) ?>">Alasan penolakan (wajib)</label>
              <textarea id="reason-<?= e($pay['id']) ?>" name="rejection_reason" maxlength="2000" required></textarea>
              <button type="submit">Tolak pembayaran</button>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if (!$isAdmin && $status === 'active' && $hasRemaining && !$hasPendingProof): ?>
        <?php if ($reminderUrl): ?>
          <p><a class="button secondary" href="<?= e($reminderUrl) ?>" target="_blank" rel="noopener">Ingatkan sisa pembayaran via WhatsApp</a></p>
        <?php else: ?>
          <p class="hint">Nomor HP penyewa tidak valid untuk WhatsApp.</p>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/resepsionis/bookings/' . (int) $booking['id'] . '/settle-cash')) ?>" onsubmit="return confirm('Catat pelunasan tunai sebesar Rp <?= e(number_format((float) $booking['remaining_amount'], 0, ',', '.')) ?>?');">
          <?= csrf_field() ?>
          <button type="submit" class="button secondary">Catat pelunasan tunai</button>
          <span class="hint">Untuk penyewa yang membayar sisa langsung di tempat.</span>
        </form>
      <?php endif; ?>

      <?php if (!$isAdmin && $status === 'active'): ?>
        <form method="post" action="<?= e(url('/resepsionis/bookings/' . (int) $booking['id'] . '/cancel')) ?>" onsubmit="return confirm('Batalkan booking dan lepaskan kamar? Refund diproses di luar sistem.');">
          <?= csrf_field() ?>
          <button type="submit">Proses pembatalan</button>
          <span class="hint">Refund (jika ada) diproses manual di luar sistem.</span>
        </form>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</div>
