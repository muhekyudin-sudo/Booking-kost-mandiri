<p><a href="<?= e(url('/')) ?>">&larr; Kembali ke katalog</a></p>
<section class="card property-info">  <?php if ($property['photo_url'] !== null): ?>
    <img class="property-hero" src="<?= e($property['photo_url']) ?>" alt="Foto <?= e($property['name']) ?>">
  <?php endif; ?>
  <p class="eyebrow"><?= e($property['city_name']) ?></p>
  <h1><?= e($property['name']) ?></h1>
  <p><?= e($property['address']) ?></p>
  <p><span class="kost-type kost-type-<?= e($property['kost_type']) ?>"><?= e(kost_type_label($property['kost_type'])) ?></span></p>
  <?php if ($property['description']): ?><p><?= e($property['description']) ?></p><?php endif; ?>
  <span class="badge <?= (int) $property['accepts_online'] ? 'available' : 'unavailable' ?>">
    <?= (int) $property['accepts_online'] ? 'Menerima booking online' : 'Belum menerima pemesanan online' ?>
  </span>
</section>

<?php if (!$classes): ?>
  <div class="card">
    <p>Belum ada kelas kamar untuk gedung ini.</p>
  </div>
<?php endif; ?>

<div class="stack class-grid">
  <?php foreach ($classes as $class): ?>
    <article class="card class-card">
      <?php $hasPrice = count(array_filter([$class['daily_price'], $class['weekly_price'], $class['monthly_price'], $class['yearly_price']], static fn($price): bool => $price !== null)) > 0; ?>

      <div class="class-card-top<?= $class['photo_url'] !== null ? ' has-photo' : '' ?>">
        <div class="class-card-info">
          <h2><?= e($class['name']) ?></h2>
          <?php if ($class['description']): ?><p><?= e($class['description']) ?></p><?php endif; ?>
          <h3>Fasilitas</h3>
          <?php if ($class['facilities_list']): ?>
            <ul><?php foreach ($class['facilities_list'] as $facility): ?><li><?= e($facility) ?></li><?php endforeach; ?></ul>
          <?php else: ?>
            <p>Informasi fasilitas belum tersedia.</p>
          <?php endif; ?>
          <h3>Harga sewa</h3>
          <div class="price-list">
            <?php foreach (['daily' => 'Harian', 'weekly' => 'Mingguan', 'monthly' => 'Bulanan', 'yearly' => 'Tahunan'] as $type => $label): ?>
              <?php $price = $class[$type . '_price']; ?>
              <?php if ($price !== null): ?>
                <span><?= e($label) ?>: <strong>Rp <?= e(number_format((float) $price, 0, ',', '.')) ?></strong></span>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>

        <?php if ($class['photo_url'] !== null): ?>
          <div class="class-card-photo">
            <img src="<?= e($class['photo_url']) ?>" alt="Foto kelas <?= e($class['name']) ?>">
          </div>
        <?php endif; ?>
      </div>

      <h3>Unit tersedia</h3>
      <?php if (!$class['available_units']): ?>
        <p>Saat ini tidak ada unit tersedia.</p>
      <?php else: ?>
        <div class="unit-list">
          <?php foreach ($class['available_units'] as $unit): ?>
            <div class="unit">
              <span>Kamar <?= e($unit['room_number']) ?></span>
              <?php if ((int) $property['accepts_online'] && $hasPrice): ?>
                <a class="button" href="<?= e(url('/booking/checkout/' . (int) $unit['id'])) ?>">Pesan kamar</a>
              <?php elseif (!$hasPrice): ?>
                <button type="button" disabled>Harga belum tersedia</button>
              <?php else: ?>
                <button type="button" disabled>Pesan online belum tersedia</button>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</div>