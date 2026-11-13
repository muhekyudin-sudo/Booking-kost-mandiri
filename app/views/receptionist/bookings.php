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
    <article class="card">
      <div class="split">
        <div>
          <h2>PNR <?= e($booking['pnr_code']) ?> <span class="badge"><?= e(str_replace('_', ' ', ucfirst($booking['booking_status']))) ?></span></h2>
          <p><?= e($booking['property_name']) ?> — <?= e($booking['room_class_name']) ?>, kamar <?= e($booking['room_number']) ?></p>
        </div>
        <span class="badge"><?= $booking['booking_type'] === 'offline' ? 'Kasir tunai' : 'Booking online' ?></span>
      </div>
      <dl class="details">
        <dt>Penyewa</dt>
        <dd><?= e($booking['tenant_name']) ?> (<?= e($booking['tenant_email']) ?>)</dd>
        <dt>Periode</dt>
        <dd><?= e($booking['start_date']) ?> s.d. <?= e($booking['end_date']) ?></dd>
        <dt>Pembayaran</dt>
        <dd><?= e(ucfirst((string) $booking['payment_method'])) ?> — <?= e(str_replace('_', ' ', ucfirst((string) $booking['payment_status']))) ?></dd>
        <dt>Total</dt>
        <dd>Rp <?= e(number_format((float) $booking['total_amount'], 0, ',', '.')) ?> · diterima Rp <?= e(number_format((float) $booking['paid_amount'], 0, ',', '.')) ?></dd>
        <?php if ((float) $booking['remaining_amount'] > 0): ?>
          <dt>Sisa DP</dt>
          <dd>Rp <?= e(number_format((float) $booking['remaining_amount'], 0, ',', '.')) ?></dd>
        <?php endif; ?>
        <?php if ($booking['approved_by_name']): ?>
          <dt>Diproses oleh</dt>
          <dd><?= e($booking['approved_by_name']) ?> — petugas <?= e($booking['approved_by_staff_name'] ?? 'tidak tersedia') ?></dd>
        <?php endif; ?>
      </dl>
      <?php if ($booking['proof_file']): ?>
        <p><a href="<?= e(url('/files/bukti/' . (int) $booking['payment_id'])) ?>" target="_blank" rel="noopener">Lihat bukti bayar</a></p>
      <?php endif; ?>
      <?php if ($booking['rejection_reason']): ?>
        <p class="alert error">Alasan penolakan: <?= e($booking['rejection_reason']) ?></p>
      <?php endif; ?>
      <?php if (!$isAdmin && $booking['booking_status'] === 'waiting_approval' && $booking['payment_id']): ?>
        <div class="split">
          <form method="post" action="<?= e(url('/resepsionis/payments/' . (int) $booking['payment_id'] . '/approve')) ?>">
            <?= csrf_field() ?>
            <button type="submit">Setujui pembayaran</button>
          </form>
          <form method="post" action="<?= e(url('/resepsionis/payments/' . (int) $booking['payment_id'] . '/reject')) ?>">
            <?= csrf_field() ?>
            <label for="reason-<?= e($booking['payment_id']) ?>">Alasan penolakan (wajib)</label>
            <textarea id="reason-<?= e($booking['payment_id']) ?>" name="rejection_reason" maxlength="2000" required></textarea>
            <button type="submit">Tolak pembayaran</button>
          </form>
        </div>
      <?php elseif (!$isAdmin && $booking['booking_status'] === 'active'): ?>
        <form method="post" action="<?= e(url('/resepsionis/bookings/' . (int) $booking['id'] . '/cancel')) ?>" onsubmit="return confirm('Batalkan booking dan lepaskan kamar? Refund diproses di luar sistem.');">
          <?= csrf_field() ?>
          <button type="submit">Proses pembatalan</button>
          <span class="hint">Refund (jika ada) diproses manual di luar sistem.</span>
        </form>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</div>