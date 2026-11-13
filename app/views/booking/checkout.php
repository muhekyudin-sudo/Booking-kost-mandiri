<p><a href="<?= e(url('/katalog/gedung/' . (int) $unit['property_id'])) ?>">&larr; Kembali ke gedung</a></p>
<section class="card">
  <h1>Checkout kamar</h1>
  <p><strong><?= e($unit['property_name']) ?></strong> — <?= e($unit['room_class_name']) ?>,
    kamar <?= e($unit['room_number']) ?></p>
  <p>Harga per durasi dihitung dari matriks harga kelas kamar.</p>
  <?php if (!$qrisImageUrl): ?>
    <p class="alert error">Booking belum dapat dibuat karena QRIS belum dikonfigurasi pengelola.</p>
  <?php endif; ?>
  <?php
    $firstPriceType = null;
    foreach (['daily', 'weekly', 'monthly', 'yearly'] as $type) {
        if ($unit[$type . '_price'] !== null) {
            $firstPriceType = $type;
            break;
        }
    }
  ?>
  <?php if ($qrisImageUrl): ?>
  <form method="post" action="<?= e(url('/booking/checkout/' . (int) $unit['unit_id'])) ?>">
    <?= csrf_field() ?>
    <label for="duration_type">Durasi sewa</label>
    <select id="duration_type" name="duration_type" required>
      <?php foreach (['daily' => 'Harian', 'weekly' => 'Mingguan', 'monthly' => 'Bulanan', 'yearly' => 'Tahunan'] as $key => $label): ?>
        <option value="<?= e($key) ?>" data-price="<?= e($unit[$key . '_price'] ?? '') ?>" <?= $unit[$key . '_price'] === null ? 'disabled' : '' ?> <?= $key === $firstPriceType ? 'selected' : '' ?>>
          <?= e($label) ?><?= $unit[$key . '_price'] === null ? ' (harga belum tersedia)' : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
    <p id="selected-price" class="hint" aria-live="polite"></p>

    <label for="duration_value">Jumlah durasi</label>
    <input id="duration_value" type="number" name="duration_value" min="1" max="1000" value="1" required>

    <label for="start_date">Tanggal mulai sewa</label>
    <input id="start_date" type="date" name="start_date" min="<?= e($today) ?>" value="<?= e($today) ?>" required>

    <label for="payment_scheme">Skema pembayaran</label>
    <select id="payment_scheme" name="payment_scheme" required>
      <option value="full">Lunas 100%</option>
      <option value="dp">DP 50% (tidak tersedia untuk sewa harian)</option>
    </select>
    <p id="estimated-total" class="hint" aria-live="polite"></p>
    <p class="hint">Booking menahan kamar selama 1 jam. Unggah bukti bayar sebelum hitung mundur berakhir.</p>
    <button type="submit">Buat booking dan invoice</button>
  </form>
  <?php endif; ?>
</section>
<?php if ($qrisImageUrl): ?>
<script>
(() => {
  const duration = document.getElementById('duration_type');
  const scheme = document.getElementById('payment_scheme');
  const quantity = document.getElementById('duration_value');
  const price = document.getElementById('selected-price');
  const total = document.getElementById('estimated-total');
  const currency = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
  const update = () => {
    const option = duration.options[duration.selectedIndex];
    const daily = option.value === 'daily';
    scheme.options[1].disabled = daily;
    if (daily && scheme.value === 'dp') scheme.value = 'full';
    if (option.dataset.price) {
      const amount = Number(option.dataset.price) * Number(quantity.value || 1);
      const dueNow = scheme.value === 'dp' ? Math.ceil(amount * 100 / 2) / 100 : amount;
      price.textContent = 'Harga per durasi: Rp ' + currency.format(Number(option.dataset.price));
      total.textContent = 'Total perkiraan: Rp ' + currency.format(amount)
        + ' · Dibayar sekarang: Rp ' + currency.format(dueNow);
    } else {
      price.textContent = 'Harga untuk durasi ini belum tersedia.';
      total.textContent = '';
    }
  };
  duration.addEventListener('change', update);
  quantity.addEventListener('input', update);
  scheme.addEventListener('change', update);
  update();
})();
</script>
<?php endif; ?>
