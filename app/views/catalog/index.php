<?php

/** @var array $rows */
/** @var array $cities */
/** @var array $classes */
/** @var int $cityId */
/** @var int $classId */
?>
<h1>Cari Kost</h1>

<form method="get" class="filters">
  <select name="city_id">
    <option value="0">Semua kota</option>
    <?php foreach ($cities as $c): ?>
      <option value="<?= (int)$c['id'] ?>" <?= $cityId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="room_class_id">
    <option value="0">Semua kelas</option>
    <?php foreach ($classes as $k): ?>
      <option value="<?= (int)$k['id'] ?>" <?= $classId === (int)$k['id'] ? 'selected' : '' ?>>
        <?= e($k['property_name'] . ' - ' . $k['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-small" type="submit">Terapkan</button>
</form>

<?php if (!$rows): ?>
  <p class="muted">Tidak ada kamar yang cocok dengan filter.</p>
<?php endif; ?>

<div class="grid">
  <?php foreach ($rows as $r): ?>
    <?php $fac = json_decode($r['facilities'] ?? '[]', true) ?: []; ?>
    <article class="card">
      <small class="muted"><?= e($r['city_name']) ?></small>
      <h3><a href="/kost/<?= (int)$r['property_id'] ?>"><?= e($r['property_name']) ?></a></h3>
      <p><span class="badge"><?= e($r['class_name']) ?></span></p>
      <p class="muted"><?= e(implode(' · ', $fac)) ?></p>
      <p class="price">
        <?= $r['monthly_price'] !== null ? rupiah($r['monthly_price']) . ' <small>/bulan</small>' : 'Harga belum diatur' ?>
      </p>
      <p><?= (int)$r['available_units'] > 0
            ? '<span class="ok">' . (int)$r['available_units'] . ' kamar tersedia</span>'
            : '<span class="err">Penuh</span>' ?></p>
      <a class="btn btn-small" href="/kost/<?= (int)$r['property_id'] ?>">Lihat kamar</a>
    </article>
  <?php endforeach; ?>
</div>