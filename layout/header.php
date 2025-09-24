<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>App Sholat</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{background:#f7f9fb}
    .brand{font-weight:700; letter-spacing:.3px}
    .card-soft{border:1px solid #eef1f4; border-radius:1rem; box-shadow:0 8px 24px rgba(0,0,0,.05)}
    .btn-pill{border-radius:999px}
    .nav-scroller{overflow-x:auto; white-space:nowrap}
  </style>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>
<nav class="navbar navbar-light bg-white border-bottom sticky-top">
  <div class="container-fluid">
    <a class="navbar-brand brand" href="/">🕌 App Sholat</a>
    <div>

      <!-- di header.php, ketika role admin/guru -->
<?php if (!empty($_SESSION['user']) && in_array($_SESSION['user']['role_name'], ['admin','guru'])): ?>
  <a href="/admin/index.php" class="btn btn-sm btn-outline-secondary btn-pill me-1">Dashboard</a>
  <a href="/admin/rekap.php" class="btn btn-sm btn-outline-secondary btn-pill me-1">Rekapitulasi</a>
  <a href="/admin/users.php" class="btn btn-sm btn-outline-secondary btn-pill me-1">User</a>
  <a href="/admin/jadwal.php" class="btn btn-sm btn-outline-secondary btn-pill me-1">Jadwal</a>
  <a href="/admin/poster_mingguan.php" class="btn btn-sm btn-outline-secondary btn-pill me-1">Poster</a>
<?php endif; ?>
<a href="/scan.php" class="btn btn-sm btn-primary btn-pill">Scan</a>



      <?php if (!empty($_SESSION['user'])): ?>
        <span class="me-2 small text-muted"><?= htmlspecialchars($_SESSION['user']['name'] ?? '') ?></span>
        <a href="/logout.php" class="btn btn-sm btn-outline-secondary btn-pill">Keluar</a>
      <?php else: ?>
        <a href="/login.php" class="btn btn-sm btn-primary btn-pill">Masuk</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
<div class="container my-3">
