<section class="page-heading">
  <div>
    <h1>Dashboard Admin</h1>
    <p>Halo, Ini ringkasan kost kamu saat ini.</p>
  </div>
</section>

<!-- 4 kartu angka -->
<div class="dash-stats">
  <div class="stat">
    <span class="stat-icon"><i data-feather="grid"></i></span>
    <div><small>Gedung</small><strong><?= e($stats['gedung']) ?></strong></div>
  </div>
  <div class="stat">
    <span class="stat-icon"><i data-feather="key"></i></span>
    <div><small>Unit tersedia</small><strong><?= e($stats['tersedia']) ?></strong></div>
  </div>
  <div class="stat">
    <span class="stat-icon"><i data-feather="user-check"></i></span>
    <div><small>Unit terisi</small><strong><?= e($stats['terisi']) ?></strong></div>
  </div>
  <div class="stat">
    <span class="stat-icon"><i data-feather="clock"></i></span>
    <div><small>Menunggu verifikasi</small><strong><?= e($stats['menunggu']) ?></strong></div>
  </div>
</div>

<h2 class="dash-title">Menu cepat</h2>

<!-- 2 kartu menu -->
<div class="menu-grid">
  <a class="menu-card" href="<?= e(url('/admin/master/cities')) ?>">
    <span class="stat-icon"><i data-feather="database"></i></span>
    <div>
      <h3>Master Data</h3>
      <p>Kelola kota, gedung, kelas kamar, unit, harga, resepsionis, dan petugas jaga.</p>
    </div>
    <i data-feather="chevron-right" class="arrow"></i>
  </a>

  <a class="menu-card" href="<?= e(url('/admin/bookings')) ?>">
    <span class="stat-icon"><i data-feather="credit-card"></i></span>
    <div>
      <h3>Transaksi Booking</h3>
      <p>Lihat booking dan pembayaran (hanya baca).</p>
    </div>
    <i data-feather="chevron-right" class="arrow"></i>
  </a>
</div>

<?php
$monthNames = [
  1 => 'Jan',
  2 => 'Feb',
  3 => 'Mar',
  4 => 'Apr',
  5 => 'Mei',
  6 => 'Jun',
  7 => 'Jul',
  8 => 'Agu',
  9 => 'Sep',
  10 => 'Okt',
  11 => 'Nov',
  12 => 'Des',
];
$chartMax = max(1, ceil(max($revenueByMonth) / 4) * 4);
$formatCompactRupiah = static function (float $amount): string {
  if ($amount >= 1000000000) {
    return 'Rp ' . number_format($amount / 1000000000, 1, ',', '.') . ' M';
  }
  if ($amount >= 1000000) {
    return 'Rp ' . number_format($amount / 1000000, 1, ',', '.') . ' jt';
  }
  if ($amount >= 1000) {
    return 'Rp ' . number_format($amount / 1000, 1, ',', '.') . ' rb';
  }
  return 'Rp ' . number_format($amount, 0, ',', '.');
};
// Dua ukuran grafik: desktop (viewBox 900x320) dan HP (viewBox 320x230).
// Hanya satu yang tampil, diatur CSS (lihat dashboard.css, @media max-width 700px).
$charts = [
  'desktop' => ['w' => 900, 'h' => 320, 'left' => 76, 'right' => 884, 'top' => 20, 'bottom' => 264, 'xLabelY' => 294, 'dot' => 4],
  'mobile'  => ['w' => 320, 'h' => 230, 'left' => 64, 'right' => 304, 'top' => 14, 'bottom' => 190, 'xLabelY' => 212, 'dot' => 3.5],
];
?>
<section class="revenue-card" aria-labelledby="revenue-title">
  <div class="revenue-heading">
    <div>
      <h2 id="revenue-title">Pendapatan Bulanan</h2>
      <p>Total pembayaran yang disetujui pada setiap bulan.</p>
    </div>
    <span class="revenue-year"><?= e($revenueYear) ?></span>
  </div>
  <div class="revenue-chart">
    <?php foreach ($charts as $name => $c):
      $chartHeight = $c['bottom'] - $c['top'];
      $chartPoints = [];
      foreach ($monthNames as $month => $monthName) {
        $chartPoints[] = [
          'name'  => $monthName,
          'value' => $revenueByMonth[$month],
          'x'     => round($c['left'] + (($month - 1) / 11) * ($c['right'] - $c['left']), 2),
          'y'     => round($c['bottom'] - ($revenueByMonth[$month] / $chartMax) * $chartHeight, 2),
        ];
      }
    ?>
      <svg class="revenue-svg revenue-svg--<?= e($name) ?>" viewBox="0 0 <?= $c['w'] ?> <?= $c['h'] ?>" role="img" aria-labelledby="revenue-chart-title-<?= e($name) ?>">
        <title id="revenue-chart-title-<?= e($name) ?>">Grafik pendapatan tahun <?= e($revenueYear) ?>: total pembayaran yang disetujui dari Januari sampai Desember.</title>
        <?php for ($tick = 0; $tick <= 4; $tick++):
          $value = $chartMax * $tick / 4;
          $y = $c['bottom'] - ($chartHeight * $tick / 4);
        ?>
          <line class="chart-gridline" x1="<?= $c['left'] ?>" y1="<?= $y ?>" x2="<?= $c['right'] ?>" y2="<?= $y ?>" />
          <text class="chart-y-label" x="<?= $c['left'] - 10 ?>" y="<?= $y + 4 ?>" text-anchor="end"><?= e($formatCompactRupiah($value)) ?></text>
        <?php endfor; ?>
        <polyline
          class="revenue-line"
          points="<?= e(implode(' ', array_map(static fn(array $pt): string => $pt['x'] . ',' . $pt['y'], $chartPoints))) ?>" />
        <?php foreach ($chartPoints as $pt): ?>
          <circle class="revenue-point" cx="<?= $pt['x'] ?>" cy="<?= $pt['y'] ?>" r="<?= $c['dot'] ?>">
            <title><?= e($pt['name'] . ': Rp ' . number_format($pt['value'], 0, ',', '.')) ?></title>
          </circle>
          <text class="chart-x-label" x="<?= $pt['x'] ?>" y="<?= $c['xLabelY'] ?>" text-anchor="middle"><?= e($pt['name']) ?></text>
        <?php endforeach; ?>
      </svg>
    <?php endforeach; ?>
  </div>
</section>
