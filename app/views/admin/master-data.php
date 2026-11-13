<a class="back-link" href="<?= e(url('/admin')) ?>"><i data-feather="arrow-left"></i> Kembali ke dashboard</a>

<?php
  // Resource yang punya foto: provinsi, kota, gedung, dan kelas kamar.
  $hasImage = in_array($resource, ['cities', 'provinces', 'properties', 'room-classes'], true);
  $imageField = match ($resource) {
    'provinces' => 'province_image',
    'properties' => 'property_image',
    'room-classes' => 'class_image',
    default => 'city_image',
  };
  $imageLabel = match ($resource) {
    'provinces' => 'provinsi',
    'properties' => 'gedung',
    'room-classes' => 'kelas kamar',
    default => 'kota',
  };
?>
<div class="card">
  <h2>Manajemen Master Data</h2>
  <nav class="master-nav" aria-label="Jenis master data">
    <?php foreach ($resources as $item): ?>
      <a href="<?= e(url('/admin/master/' . $item['key'])) ?>" <?= $item['key'] === $resource ? ' aria-current="page"' : '' ?>><?= e($item['title']) ?></a>
    <?php endforeach; ?>
  </nav>
</div>

<?php if ($searchable || $filters): ?>
<div class="card">
  <h2>Filter <?= e($resourceTitle) ?></h2>
  <form method="get" action="<?= e(url('/admin/master/' . $resource)) ?>" class="filter-form">
    <?php if ($searchable): ?>
      <div class="filter-field">
        <label for="filter_q">Cari</label>
        <input id="filter_q" type="search" name="q" value="<?= e($search) ?>" maxlength="100" placeholder="Ketik untuk mencari...">
      </div>
    <?php endif; ?>
    <?php foreach ($filters as $filter): ?>
      <div class="filter-field">
        <label for="filter_<?= e($filter['name']) ?>"><?= e($filter['label']) ?></label>
        <select id="filter_<?= e($filter['name']) ?>" name="<?= e($filter['name']) ?>">
          <option value="">Semua</option>
          <?php foreach ($filter['choices'] as $choiceValue => $choiceLabel): ?>
            <option value="<?= e($choiceValue) ?>"<?= (string) ($filterValues[$filter['name']] ?? '') === (string) $choiceValue ? ' selected' : '' ?>><?= e($choiceLabel) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endforeach; ?>
    <div class="filter-actions">
      <button type="submit">Terapkan filter</button>
      <a class="button secondary" href="<?= e(url('/admin/master/' . $resource)) ?>">Reset</a>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h2>Tambah <?= e($resourceTitle) ?></h2>
  <form method="post" <?= $hasImage ? ' enctype="multipart/form-data"' : '' ?> action="<?= e(url('/admin/master/' . $resource)) ?>">
    <?= csrf_field() ?>
    <?php foreach ($fields as $name => $field): ?>
      <?php $value = $field['type'] === 'active' ? '1' : ''; ?>
      <label for="new_<?= e($name) ?>"><?= e($field['label']) ?></label>
      <?php if ($field['type'] === 'textarea' || $field['type'] === 'json'): ?>
        <textarea id="new_<?= e($name) ?>" name="<?= e($name) ?>" rows="3" <?= !empty($field['required']) ? ' required' : '' ?>><?= e($field['type'] === 'json' ? '[]' : $value) ?></textarea>
      <?php elseif ($field['type'] === 'select' || $field['type'] === 'active'): ?>
        <select id="new_<?= e($name) ?>" name="<?= e($name) ?>" <?= !empty($field['required']) ? ' required' : '' ?>>
          <option value="">Pilih</option>
          <?php foreach (($options[$name] ?? ['0' => 'Nonaktif', '1' => 'Aktif']) as $optionValue => $label): ?>
            <option value="<?= e($optionValue) ?>" <?= (string) $optionValue === $value ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      <?php else: ?>
        <input id="new_<?= e($name) ?>" type="<?= e($field['type'] === 'price' ? 'number' : $field['type']) ?>" name="<?= e($name) ?>" <?= $field['type'] === 'price' ? ' min="0.01" step="0.01"' : '' ?><?= !empty($field['required']) || ($field['type'] === 'password') ? ' required' : '' ?>>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($hasImage): ?>
      <!-- Foto dikirim sebagai file terpisah dari data teks. -->
      <label for="new_<?= e($imageField) ?>">Foto <?= e($imageLabel) ?> (JPG, PNG, WEBP; maksimal 5 MB)</label>
      <input id="new_<?= e($imageField) ?>" type="file" name="<?= e($imageField) ?>" accept="image/jpeg,image/png,image/webp">
    <?php endif; ?>
    <button type="submit">Tambah <?= e($resourceTitle) ?></button>
  </form>
</div>

<div class="card">
  <h2>Daftar <?= e($resourceTitle) ?></h2>
  <?php if ($isFiltered): ?>
    <p class="filter-info">Menampilkan <?= count($rows) ?> dari <?= (int) $totalCount ?> data (filter aktif).</p>
  <?php endif; ?>
  <?php if ($rows === []): ?>
    <p><?= $isFiltered ? 'Tidak ada data yang cocok dengan filter.' : 'Belum ada data.' ?></p>
  <?php endif; ?>
  <?php foreach ($rows as $row): ?>
    <section class="record">
      <h3><?= e($row['name'] ?? $row['room_number'] ?? ('#' . $row['id'])) ?></h3>
      <form method="post" <?= $hasImage ? ' enctype="multipart/form-data"' : '' ?> action="<?= e(url('/admin/master/' . $resource . '/' . $row['id'])) ?>">
        <?= csrf_field() ?>
        <?php foreach ($fields as $name => $field): ?>
          <?php
          $value = $row[$name] ?? '';
          if ($field['type'] === 'json' && is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '[]';
          }
          ?>
          <label for="row_<?= e($row['id'] . '_' . $name) ?>"><?= e($field['label']) ?></label>
          <?php if ($field['type'] === 'password'): ?>
            <input id="row_<?= e($row['id'] . '_' . $name) ?>" type="password" name="<?= e($name) ?>" autocomplete="new-password">
          <?php elseif ($field['type'] === 'textarea' || $field['type'] === 'json'): ?>
            <textarea id="row_<?= e($row['id'] . '_' . $name) ?>" name="<?= e($name) ?>" rows="3" <?= !empty($field['required']) ? ' required' : '' ?>><?= e($value) ?></textarea>
          <?php elseif ($field['type'] === 'select' || $field['type'] === 'active'): ?>
            <select id="row_<?= e($row['id'] . '_' . $name) ?>" name="<?= e($name) ?>" required>
              <?php foreach (($options[$name] ?? ['0' => 'Nonaktif', '1' => 'Aktif']) as $optionValue => $label): ?>
                <option value="<?= e($optionValue) ?>" <?= (string) $optionValue === (string) $value ? ' selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <input id="row_<?= e($row['id'] . '_' . $name) ?>" type="<?= e($field['type'] === 'price' ? 'number' : $field['type']) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>" <?= $field['type'] === 'price' ? ' min="0.01" step="0.01"' : '' ?><?= !empty($field['required']) ? ' required' : '' ?>>
          <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($hasImage): ?>
          <!-- Foto yang sudah ada dapat diganti dengan unggahan baru atau dihapus. -->
          <label for="row_<?= e($row['id']) ?>_<?= e($imageField) ?>">Foto <?= e($imageLabel) ?> (JPG, PNG, WEBP; maksimal 5 MB)</label>
          <?php if ($row['image_url'] !== null): ?>
            <img class="city-image-preview" src="<?= e($row['image_url']) ?>" alt="Foto <?= e($row['name']) ?>">
          <?php endif; ?>
          <input id="row_<?= e($row['id']) ?>_<?= e($imageField) ?>" type="file" name="<?= e($imageField) ?>" accept="image/jpeg,image/png,image/webp">
          <?php if ($row['image_url'] !== null): ?>
            <label class="delete-city-image">
              <input type="checkbox" name="delete_image" value="1">
              Hapus Foto
            </label>
          <?php endif; ?>
        <?php endif; ?>
        <button type="submit">Simpan Perubahan</button>
      </form>
      <form method="post" action="<?= e(url('/admin/master/' . $resource . '/' . $row['id'] . '/delete')) ?>" onsubmit="return confirm('Hapus data ini?');">
        <?= csrf_field() ?>
        <button type="submit" class="danger">Hapus</button>
      </form>
    </section>
  <?php endforeach; ?>
</div>