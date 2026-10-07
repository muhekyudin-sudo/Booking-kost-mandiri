<?php

/** @var array $unit */
/** @var array $prices */
/** @var array $old */
/** @var array $errors */
?>
<h1>Checkout</h1>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>

<div class="card">
  <p class="muted"><?= e($unit['city_name']) ?></p>
  <h3><?= e($unit['property_name']) ?> - <?= e($unit['class_name']) ?></h3>
  <p>Kamar nomor <strong><?= e($unit['room_number']) ?></strong></p>
</div>

<form method="post" class="card" id="checkout-form">
  <?= csrf_field() ?>
  <label>Jenis durasi
    <select name="duration_type" id="duration_type">
      <?php foreach ($prices as $d => $price): ?>
        <option value="<?= e($d) ?>" data-price="<?= e($price) ?>" <?= $old['duration_type'] === $d ? 'selected' : '' ?>>
          <?= e(DURATION_LABEL[$d]) ?> (<?= e(rupiah($price)) ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Jumlah durasi
    <input type="number" name="duration_value" id="duration_value" min="1" max="36" value="<?= (int)$old['duration_value'] ?>">
  </label>
  <label>Tanggal mulai
    <input type="date" name="start_date" min="<?= date('Y-m-d') ?>" value="<?= e($old['start_date']) ?>">
  </label>
  <label>Skema pembayaran
    <select name="payment_scheme" id="payment_scheme">
      <option value="dp" <?= $old['payment_scheme'] === 'dp' ? 'selected' : '' ?>>DP 50% (sisa dilunasi dalam 7 hari)</option>
      <option value="full" <?= $old['payment_scheme'] === 'full' ? 'selected' : '' ?>>Lunas 100%</option>
    </select>
  </label>

  <div class="summary">
    <div>Total sewa: <strong id="sum-total">-</strong></div>
    <div>Bayar sekarang: <strong id="sum-pay">-</strong></div>
  </div>
  <p class="muted">Setelah pesanan dibuat, kamar ditahan <?= (int)config('hold_minutes') ?> menit. Segera upload bukti bayar.</p>
  <button class="btn" type="submit">Buat Pesanan</button>
</form>

<script>
  // Ringkasan ini hanya tampilan. Angka sah tetap dihitung ulang oleh PHP.
  const typeEl = document.getElementById('duration_type');
  const valEl = document.getElementById('duration_value');
  const schEl = document.getElementById('payment_scheme');
  const rp = n => 'Rp ' + Math.round(n).toLocaleString('id-ID');

  function hitung() {
    const price = parseFloat(typeEl.selectedOptions[0].dataset.price);
    const total = price * (parseInt(valEl.value) || 0);
    document.getElementById('sum-total').textContent = rp(total);
    document.getElementById('sum-pay').textContent = rp(schEl.value === 'dp' ? total / 2 : total);
  }
  [typeEl, valEl, schEl].forEach(el => el.addEventListener('input', hitung));
  hitung();
</script>