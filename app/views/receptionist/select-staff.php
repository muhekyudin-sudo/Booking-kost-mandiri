<div class="card">
  <h2>Pilih Petugas Jaga</h2>
  <p>Pilih nama petugas aktif untuk mulai menggunakan dashboard gedung ini.</p>
  <?php if ($staff === []): ?>
    <p>Belum ada petugas aktif. Hubungi administrator gedung.</p>
  <?php else: ?>
    <form method="post" action="<?= e(url('/resepsionis/pilih-petugas')) ?>">
      <?= csrf_field() ?>
      <label for="staff_id">Nama petugas</label>
      <select id="staff_id" name="staff_id" required>
        <option value="">Pilih nama</option>
        <?php foreach ($staff as $person): ?>
          <option value="<?= e($person['id']) ?>"><?= e($person['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit">Lanjut ke Dashboard</button>
    </form>
  <?php endif; ?>
</div>
