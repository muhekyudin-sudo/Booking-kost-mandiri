<section class="page-heading page-heading--catalog">
  <div>
    <h1>Katalog Kost</h1>
    <p>Pilih kota dan kelas kamar untuk melihat gedung yang tersedia.</p>
  </div>
  <div class="stats">
    <div class="stat">
      <span class="stat-icon"><i data-feather="map-pin"></i></span>
      <div><small>Gedung</small><strong><?= e($stats['gedung']) ?></strong></div>
    </div>
    <div class="stat">
      <span class="stat-icon"><i data-feather="map-pin"></i></span>
      <div><small>Kota</small><strong><?= e($stats['kota']) ?></strong></div>
    </div>
    <div class="stat">
      <span class="stat-icon"><i data-feather="key"></i></span>
      <div><small>Unit tersedia</small><strong><?= e($stats['unit']) ?></strong></div>
    </div>
  </div>
</section>

<form class="card filters filters--compact<?= $selectedCity !== null ? ' filters--no-city' : '' ?>" method="get" action="<?= e(url('/')) ?>">
  <?php if ($selectedCity === null): ?>
    <label for="city_id">Kota</label>
    <div class="select-wrap">
      <i data-feather="map-pin"></i>
      <select id="city_id" name="city_id" data-searchable>
        <option value="">Semua kota</option>
        <?php foreach ($cityOptions as $city): ?>
          <option value="<?= e($city['id']) ?>" <?= $selectedCity === (int) $city['id'] ? 'selected' : '' ?>>
            <?= e($city['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php else: ?>
    <input type="hidden" name="city_id" value="<?= e($selectedCity) ?>">
  <?php endif; ?>

  <label for="room_class">Kelas kamar</label>
  <div class="select-wrap">
    <i data-feather="layers"></i>
    <select id="room_class" name="room_class">
      <option value="">Semua kelas</option>
      <?php foreach ($classOptions as $class): ?>
        <option value="<?= e($class['name']) ?>" <?= $selectedClass === $class['name'] ? 'selected' : '' ?>>
          <?= e($class['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <label for="kost_type">Jenis kost</label>
  <div class="select-wrap">
    <i data-feather="users"></i>
    <select id="kost_type" name="kost_type">
      <option value="">Semua jenis</option>
      <?php foreach (kost_types() as $code => $label): ?>
        <option value="<?= e($code) ?>" <?= $selectedKostType === $code ? 'selected' : '' ?>>
          <?= e($label) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="actions">
    <button type="submit"><i data-feather="sliders"></i> Terapkan filter</button>
    <a class="button secondary" href="<?= e(url('/')) ?>"><i data-feather="rotate-ccw"></i> Reset</a>
  </div>
</form>

<?php if ($showCities && !empty($provinces)): ?>
  <nav class="province-row" aria-label="Pilih provinsi">
    <?php foreach ($provinces as $province): ?>
      <?php $isActive = $selectedProvince === (int) $province['id']; ?>
      <a class="province-item <?= $isActive ? 'active' : '' ?>"
        href="<?= e(url('/?province_id=' . (int) $province['id'])) ?>"
        <?= $isActive ? 'aria-current="true"' : '' ?>>
        <span class="province-circle">
          <?php if ($province['photo_url'] !== null): ?>
            <img src="<?= e($province['photo_url']) ?>" alt="Foto <?= e($province['name']) ?>">
          <?php else: ?>
            <?= e(mb_strtoupper(mb_substr($province['name'], 0, 2))) ?>
          <?php endif; ?>
        </span>
        <span class="province-name"><?= e($province['name']) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <?php if ($selectedProvince !== null && $selectedProvinceName !== null): ?>
    <p class="province-filter-info">
      Menampilkan kota di <strong><?= e($selectedProvinceName) ?></strong>
      · <a href="<?= e(url('/')) ?>">Tampilkan semua</a>
    </p>
  <?php endif; ?>
<?php endif; ?>

<?php if ($showCities): ?>
  <div class="city-grid">
    <?php foreach ($cities as $city): ?>
      <a class="city-card" href="<?= e(url('/?city_id=' . (int) $city['id'])) ?>">
        <div class="city-card-media">
          <?php if ($city['photo_url'] !== null): ?>
            <!-- Gambar di-crop otomatis agar rasio foto tetap proporsional. -->
            <img src="<?= e($city['photo_url']) ?>" alt="Foto <?= e($city['name']) ?>">
          <?php else: ?>
            <span><?= e($city['name']) ?></span>
          <?php endif; ?>
        </div>
        <div class="city-card-content">
          <h2><?= e($city['name']) ?></h2>
          <?php if ($city['description'] !== null && trim((string) $city['description']) !== ''): ?>
            <p class="city-description"><?= e($city['description']) ?></p><!-- Deskripsi ditampilkan lengkap tanpa dipotong CSS. -->
          <?php endif; ?>
          <!-- tampilan harga mulai dari di kartu -->
          <?php
          $minPrice = $city['min_monthly_price'];
          $maxPrice = $city['max_monthly_price'];
          ?>
          <?php if ($minPrice !== null): ?>
            <p class="city-price">
              <?php if ((float) $minPrice === (float) $maxPrice): ?>
                Bulanan <strong>Rp <?= e(number_format((float) $minPrice, 0, ',', '.')) ?></strong>
              <?php else: ?>
                Bulanan mulai dari
                <strong>
                  <span class="price-min">Rp <?= e(number_format((float) $minPrice, 0, ',', '.')) ?></span>
                  <span class="price-max">– Rp <?= e(number_format((float) $maxPrice, 0, ',', '.')) ?></span>
                </strong>
              <?php endif; ?>
            </p>
          <?php endif; ?>

          <p class="city-stats">
            <span><?= e($city['property_count']) ?> gedung</span>
            <span aria-hidden="true">·</span>
            <span class="<?= (int) $city['available_units'] > 0 ? 'available' : '' ?>"><?= e($city['available_units']) ?> unit tersedia</span>
            <span class="arrow" aria-hidden="true">→</span>
          </p>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <section class="property-list">
    <div class="property-list-heading">
      <div>
        <?php if ($selectedCity !== null && $selectedCityName !== null): ?>
          <h2><?= e($selectedCityName) ?></h2>
        <?php else: ?>
          <h2>Daftar gedung</h2>
        <?php endif; ?>
      </div>
      <a href="<?= e(url('/')) ?>">Semua kota</a>
    </div>

    <?php if (!$properties): ?>
      <div class="card">
        <p>Tidak ada gedung yang cocok dengan filter tersebut.</p>
      </div>
    <?php else: ?>
      <div class="property-grid">
        <?php foreach ($properties as $property): ?>
          <article class="card property-card">
            <div class="property-card-media">
              <?php if ($property['photo_url'] !== null): ?>
                <img src="<?= e($property['photo_url']) ?>" alt="Foto <?= e($property['name']) ?>">
              <?php else: ?>
                <span aria-hidden="true">🏢</span>
              <?php endif; ?>
            </div>
            <div class="split">
              <div>
                <p class="eyebrow"><?= e($property['city_name']) ?></p>
                <h3><?= e($property['name']) ?></h3>
                <p><?= e($property['address']) ?></p>
                <span class="kost-type kost-type-<?= e($property['kost_type']) ?>"><?= e(kost_type_label($property['kost_type'])) ?></span>
              </div>
              <span class="badge <?= (int) $property['accepts_online'] ? 'available' : 'unavailable' ?>">
                <?= (int) $property['accepts_online'] ? 'Menerima booking online' : 'Belum menerima pemesanan online' ?>
              </span>
            </div>
            <p><strong><?= e($property['available_units']) ?> unit tersedia</strong></p>
            <div class="property-classes">
              <?php foreach ($classesByProperty[(int) $property['id']] ?? [] as $class): ?>
                <section class="class-summary">
                  <h4><?= e($class['name']) ?></h4>
                  <?php $priceShown = false; ?>
                  <?php foreach (['daily' => 'Harian', 'weekly' => 'Mingguan', 'monthly' => 'Bulanan', 'yearly' => 'Tahunan'] as $type => $label): ?>
                    <?php $price = $class[$type . '_price']; ?>
                    <?php if (!$priceShown && $price !== null): ?>
                      <p><?= e($label) ?>: <strong>Rp <?= e(number_format((float) $price, 0, ',', '.')) ?></strong></p>
                      <?php $priceShown = true; ?>
                    <?php endif; ?>
                  <?php endforeach; ?>
                  <?php if (!$priceShown): ?><p>Harga belum tersedia.</p><?php endif; ?>
                </section>
              <?php endforeach; ?>
            </div>
            <a class="button" href="<?= e(url('/katalog/gedung/' . (int) $property['id'])) ?>">Lihat kamar dan pesan</a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>