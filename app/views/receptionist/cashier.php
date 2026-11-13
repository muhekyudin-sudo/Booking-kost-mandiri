<p><a href="<?= e(url('/resepsionis')) ?>">&larr; Kembali ke dashboard</a></p>
<section class="card">
  <h1>Kasir tunai / walk-in</h1>
  <p>Transaksi tunai langsung mencatat booking aktif, pembayaran disetujui, dan kamar terisi.</p>
  <?php if (!$units): ?>
    <p class="alert error">Gedung Anda tidak memiliki kamar tersedia untuk transaksi baru.</p>
  <?php else: ?>
    <form method="post" action="<?= e(url('/resepsionis/kasir')) ?>">
      <?= csrf_field() ?>
      <label for="unit_id">Kamar tersedia di gedung Anda</label>
      <select id="unit_id" name="unit_id" required>
        <option value="">Pilih kamar</option>
        <?php foreach ($units as $unit): ?>
          <option value="<?= e($unit['id']) ?>">
            <?= e($unit['room_class_name']) ?> — <?= e($unit['room_number']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <h2>Data penyewa</h2>
      <p class="hint">Email yang sudah memiliki akun penyewa akan menggunakan akun tersebut. Email baru akan dibuatkan akun penyewa yang dapat diaktifkan melalui pendaftaran</p>
      <label for="tenant_name">Nama penyewa</label>
      <input id="tenant_name" name="tenant_name" maxlength="255" autocomplete="name" required>
      <label for="tenant_email">Email penyewa</label>
      <input id="tenant_email" name="tenant_email" type="email" maxlength="255" autocomplete="email" required>
      <label for="tenant_phone">Nomor telepon</label>
      <input id="tenant_phone" name="tenant_phone" type="tel" maxlength="20" autocomplete="tel" required>

      <label for="duration_type">Durasi sewa</label>
      <select id="duration_type" name="duration_type" required>
        <option value="daily">Harian — DP tidak tersedia</option>
        <option value="weekly">Mingguan</option>
        <option value="monthly">Bulanan</option>
        <option value="yearly">Tahunan</option>
      </select>
      <label for="duration_value">Jumlah durasi</label>
      <input id="duration_value" type="number" name="duration_value" min="1" max="1000" value="1" required>
      <label for="payment_scheme">Pembayaran tunai</label>
      <select id="payment_scheme" name="payment_scheme" required>
        <option value="full">Lunas 100%</option>
        <option value="dp">DP 50% (mingguan, bulanan, atau tahunan)</option>
      </select>
      <label for="start_date">Tanggal mulai sewa</label>
      <input id="start_date" type="date" name="start_date" min="<?= e($today) ?>" max="<?= e($latestStartDate) ?>" value="<?= e($today) ?>" required>
      <button type="submit">Catat transaksi tunai</button>
    </form>
  <?php endif; ?>
</section>
<script>
(() => {
  const duration = document.getElementById('duration_type');
  const scheme = document.getElementById('payment_scheme');
  if (!duration || !scheme) return;
  const dp = scheme.querySelector('option[value="dp"]');
  duration.addEventListener('change', () => {
    dp.disabled = duration.value === 'daily';
    if (dp.disabled && scheme.value === 'dp') scheme.value = 'full';
  });
})();
</script>
