<?php

/** @var array $b */
/** @var array|false $payment */
/** @var int $secondsLeft */
/** @var string $waUrl */
?>
<p><a href="/riwayat">&larr; Riwayat</a></p>
<div class="card center">
  <small class="muted">Kode PNR Anda</small>
  <div class="pnr"><?= e($b['pnr_code']) ?></div>
  <span class="badge"><?= e(STATUS_LABEL[$b['status']] ?? $b['status']) ?></span>
</div>

<?php if ($b['status'] === 'pending_payment'): ?>
  <div class="card center">
    <p>Selesaikan pembayaran dalam</p>
    <div class="timer" id="timer" data-left="<?= (int)$secondsLeft ?>">--:--</div>
    <?php if ($payment): ?>
      <p>Transfer / scan QRIS sebesar <strong><?= rupiah($payment['amount']) ?></strong></p>
    <?php endif; ?>
    <p class="muted">(Fitur upload bukti bayar dibuat di tahap berikutnya.)</p>
  </div>
<?php endif; ?>

<div class="card">
  <h3>Detail pesanan</h3>
  <table class="table">
    <tr>
      <td>Gedung</td>
      <td><?= e($b['property_name']) ?>, <?= e($b['city_name']) ?></td>
    </tr>
    <tr>
      <td>Kelas / Kamar</td>
      <td><?= e($b['class_name']) ?> / <?= e($b['room_number']) ?></td>
    </tr>
    <tr>
      <td>Durasi</td>
      <td><?= (int)$b['duration_value'] ?> <?= e(DURATION_LABEL[$b['duration_type']]) ?></td>
    </tr>
    <tr>
      <td>Periode</td>
      <td><?= e($b['start_date']) ?> s/d <?= e($b['end_date']) ?></td>
    </tr>
    <tr>
      <td>Total</td>
      <td><?= rupiah($b['total_amount']) ?></td>
    </tr>
    <tr>
      <td>Skema</td>
      <td><?= $b['payment_scheme'] === 'dp' ? 'DP 50%' : 'Lunas 100%' ?></td>
    </tr>
  </table>
  <a class="btn" href="<?= e($waUrl) ?>" target="_blank" rel="noopener">Konfirmasi via WhatsApp</a>
</div>

<script>
  const t = document.getElementById('timer');
  if (t) {
    let left = parseInt(t.dataset.left);
    const hadTime = left > 0; // reload hanya jika waktu habis saat halaman terbuka
    const tick = () => {
      if (left <= 0) {
        t.textContent = '00:00';
        if (hadTime) location.reload();
        return;
      }
      const m = String(Math.floor(left / 60)).padStart(2, '0');
      const s = String(left % 60).padStart(2, '0');
      t.textContent = m + ':' + s;
      left--;
      setTimeout(tick, 1000);
    };
    tick();
  }
</script>