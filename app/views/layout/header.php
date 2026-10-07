<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? config('app_name')) ?> - <?= e(config('app_name')) ?></title>
  <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="topbar">
  <a class="brand" href="/"><?= e(config('app_name')) ?></a>
  <nav>
    <a href="/">Katalog</a>
    <?php if ($u = current_user()): ?>
      <a href="/riwayat">Riwayat</a>
      <span class="who"><?= e($u['name']) ?></span>
      <form action="/logout" method="post" class="inline">
        <?= csrf_field() ?>
        <button type="submit" class="btn-link">Keluar</button>
      </form>
    <?php else: ?>
      <a href="/login">Masuk</a>
      <a class="btn btn-small" href="/register">Daftar</a>
    <?php endif; ?>
  </nav>
</header>
<main class="container">
<?php if ($f = flash()): ?>
  <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endif; ?>