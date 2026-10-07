<?php

/** @var array $property */
/** @var array $classes */
/** @var array $unitsByClass */
?>
<p><a href="/">&larr; Kembali</a></p>
<h1><?= e($property['name']) ?></h1>
<p class="muted"><?= e($property['city_name']) ?> · <?= e($property['address']) ?></p>
<?php if ($property['description']): ?><p><?= nl2br(e($property['description'])) ?></p><?php endif; ?>

<?php foreach ($classes as $k): ?>
  <?php $fac = json_decode($k['facilities'] ?? '[]', true) ?: []; ?>
  <section class="card">
    <h2><?= e($k['name']) ?></h2>
    <?php if ($k['description']): ?><p><?= e($k['description']) ?></p><?php endif; ?>
    <p class="muted">Fasilitas: <?= $fac ? e(implode(', ', $fac)) : '-' ?></p>

    <table class="table">
      <tr>
        <th>Harian</th>
        <th>Mingguan</th>
        <th>Bulanan</th>
        <th>Tahunan</th>
      </tr>
      <tr>
        <?php foreach (['daily', 'weekly', 'monthly', 'yearly'] as $d): ?>
          <td><?= $k[$d . '_price'] !== null ? rupiah($k[$d . '_price']) : '-' ?></td>
        <?php endforeach; ?>
      </tr>
    </table>

    <h4>Pilih kamar</h4>
    <div class="units">
      <?php foreach ($unitsByClass[$k['id']] ?? [] as $u): ?>
        <?php if ($u['status'] === 'available'): ?>
          <a class="unit unit-ok" href="/pesan?unit_id=<?= (int)$u['id'] ?>" title="Tersedia - klik untuk pesan">
            <?= e($u['room_number']) ?><small>Pesan</small>
          </a>
        <?php else: ?>
          <span class="unit unit-no" title="<?= e($u['status']) ?>">
            <?= e($u['room_number']) ?><small><?= $u['status'] === 'maintenance' ? 'Perawatan' : 'Terisi' ?></small>
          </span>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>