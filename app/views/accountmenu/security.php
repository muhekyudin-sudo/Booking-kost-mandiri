<section class="page-heading">
  <div>
    <h1>Privasi dan keamanan</h1>
    <p>Kelola keamanan akun Anda.</p>
  </div>
</section>

<section class="card">
  <h2>Ubah password</h2>
  <p>Kami akan mengirim link untuk membuat password baru ke email akun Anda:</p>
  <p><strong><?= e($user['email']) ?></strong></p>
  <p>Link hanya berlaku selama 15 menit.</p>
  <form method="post" action="<?= e(url('/accountmenu/security/password')) ?>">
    <?= csrf_field() ?>
    <button type="submit">Kirim link ubah password</button>
  </form>
</section>