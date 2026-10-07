<?php

/** @var array $rows */
?>
<h1>Riwayat Pesanan</h1>
<?php if (!$rows): ?>
  <p class="muted">Belum ada pesanan. <a href="/">Cari kamar</a></p>
<?php endif; ?>
<?php foreach ($rows as $r): ?>
  <a class="card row-link" href="/booking/<?= e($r['pnr_code']) ?>">
    <div>
      <strong><?= e($r['pnr_code']) ?></strong>
      <span class="badge"><?= e(STATUS_LABEL[$r['status']] ?? $r['status']) ?></span><br>
      <?= e($r['property_name']) ?> - <?= e($r['class_name']) ?> (<?= e($r['room_number']) ?>)<br>
      <small class="muted"><?= e($r['start_date']) ?> s/d <?= e($r['end_date']) ?></small>
    </div>
    <div><?= rupiah($r['total_amount']) ?></div>
  </a>
<?php endforeach; ?>