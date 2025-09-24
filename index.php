<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';

$today = today_id();
$tz    = new DateTimeZone('Asia/Jakarta');

// Ambil jadwal hari ini dari tabel jendela_sholat
function get_window_row(mysqli $db, string $prayer): ?array {
  $dow = current_dow_id();
  $stmt = $db->prepare("SELECT jam_mulai, jam_selesai, aktif FROM jendela_sholat WHERE dow=? AND sholat=? LIMIT 1");
  $stmt->bind_param('is', $dow, $prayer);
  $stmt->execute();
  $res = $stmt->get_result();
  return $res->fetch_assoc() ?: null;
}

$wDz = get_window_row($mysqli, 'dzuhur');
$wAs = get_window_row($mysqli, 'ashar');

$openDz = $wDz && is_within_window($mysqli, 'dzuhur');
$openAs = $wAs && is_within_window($mysqli, 'ashar');

// Token QR “hari ini” (untuk tombol cepat & poster)
$tokDz = make_token($today, 'dzuhur');
$tokAs = make_token($today, 'ashar');

// Jika siswa login, ambil status absen hari ini
$me = $_SESSION['user'] ?? null;
$myToday = ['dzuhur'=>null, 'ashar'=>null];

if ($me) {
  $stmt = $mysqli->prepare("
    SELECT sholat, status_verifikasi, path_foto, waktu_scan
    FROM kehadiran
    WHERE user_id=? AND tanggal=? AND sholat IN ('dzuhur','ashar')
  ");
  $uid = (int)$me['id'];
  $stmt->bind_param('is', $uid, $today);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($r = $res->fetch_assoc()) {
    $myToday[$r['sholat']] = $r;
  }
}

include __DIR__ . '/layout/header.php';
?>
<div class="row g-3">
  <div class="col-12">
    <div class="p-3 p-md-4 bg-white border rounded-4 card-soft">
      <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-md-between gap-3">
        <div>
          <div class="badge bg-success-subtle text-success border border-success">Beta</div>
          <h1 class="h4 mt-2 mb-1">🕌 App Sholat Sekolah</h1>
          <p class="text-muted mb-0">Absensi sholat <b>Dzuhur</b> & <b>Ashar</b> pakai QR + selfie. Tanggal: <b><?= sanitize($today) ?></b>.</p>
        </div>
        <div class="d-flex gap-2">
          <a href="/scan.php" class="btn btn-primary btn-pill">🎯 Scan QR</a>
          <?php if (empty($me)): ?>
            <a href="/login.php" class="btn btn-outline-secondary btn-pill">Masuk</a>
          <?php else: ?>
            <?php if (in_array($me['role_name'], ['admin','guru'])): ?>
              <a href="/admin/index.php" class="btn btn-outline-secondary btn-pill">Panel Admin</a>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <?php if (!empty($me)): ?>
  <div class="col-12">
    <div class="alert alert-info d-flex align-items-center gap-2 mb-0">
      <span class="fw-semibold">Halo, <?= sanitize($me['name'] ?? '') ?></span>
      <span class="badge bg-dark-subtle text-dark ms-1"><?= strtoupper(sanitize($me['role_name'] ?? '')) ?></span>
    </div>
  </div>
  <?php endif; ?>

  <!-- Kartu Jadwal & Aksi Cepat -->
  <div class="col-12 col-md-6">
    <div class="card card-soft h-100">
      <div class="card-body p-3 p-md-4">
        <div class="d-flex align-items-center justify-content-between">
          <h2 class="h6 mb-0">Dzuhur — Jadwal Hari Ini</h2>
          <?php if ($wDz && $wDz['aktif']): ?>
            <span class="badge <?= $openDz ? 'bg-success' : 'bg-secondary' ?>">
              <?= $openDz ? 'Sedang Dibuka' : 'Tutup' ?>
            </span>
          <?php else: ?>
            <span class="badge bg-warning text-dark">Belum Diatur</span>
          <?php endif; ?>
        </div>
        <div class="mt-2 small text-muted">
          <?php if ($wDz): ?>
            Jam: <b><?= substr($wDz['jam_mulai'],0,5) ?>–<?= substr($wDz['jam_selesai'],0,5) ?></b> WIB
          <?php else: ?>
            Tidak ada jadwal untuk hari ini (atur di menu Admin &raquo; Jadwal).
          <?php endif; ?>
        </div>

        <div class="d-grid gap-2 mt-3">
          <a class="btn btn-outline-primary btn-pill"
             href="/absen.php?d=<?= urlencode($today) ?>&p=dzuhur&t=<?= urlencode($tokDz) ?>">
             Buka Halaman Absen Dzuhur
          </a>
        </div>

        <?php if ($me && isset($myToday['dzuhur'])):
          $st = $myToday['dzuhur']['status_verifikasi'];
          $badge = $st==='approved'?'success':($st==='rejected'?'danger':'warning text-dark');
        ?>
          <div class="mt-3">
            <div class="small text-muted">Status Absen Anda:</div>
            <span class="badge bg-<?= $badge ?>"><?= $st ?></span>
            <div class="small text-muted">Scan: <?= sanitize($myToday['dzuhur']['waktu_scan']) ?></div>
            <?php if (!empty($myToday['dzuhur']['path_foto'])): ?>
              <div class="ratio ratio-16x9 mt-2">
                <img class="w-100 h-100 object-fit-cover rounded"
                     src="/uploads/attendance/<?= sanitize($myToday['dzuhur']['path_foto']) ?>" alt="bukti dzuhur">
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-md-6">
    <div class="card card-soft h-100">
      <div class="card-body p-3 p-md-4">
        <div class="d-flex align-items-center justify-content-between">
          <h2 class="h6 mb-0">Ashar — Jadwal Hari Ini</h2>
          <?php if ($wAs && $wAs['aktif']): ?>
            <span class="badge <?= $openAs ? 'bg-success' : 'bg-secondary' ?>">
              <?= $openAs ? 'Sedang Dibuka' : 'Tutup' ?>
            </span>
          <?php else: ?>
            <span class="badge bg-warning text-dark">Belum Diatur</span>
          <?php endif; ?>
        </div>
        <div class="mt-2 small text-muted">
          <?php if ($wAs): ?>
            Jam: <b><?= substr($wAs['jam_mulai'],0,5) ?>–<?= substr($wAs['jam_selesai'],0,5) ?></b> WIB
          <?php else: ?>
            Tidak ada jadwal untuk hari ini (atur di menu Admin &raquo; Jadwal).
          <?php endif; ?>
        </div>

        <div class="d-grid gap-2 mt-3">
          <a class="btn btn-outline-primary btn-pill"
             href="/absen.php?d=<?= urlencode($today) ?>&p=ashar&t=<?= urlencode($tokAs) ?>">
             Buka Halaman Absen Ashar
          </a>
        </div>

        <?php if ($me && isset($myToday['ashar'])):
          $st = $myToday['ashar']['status_verifikasi'];
          $badge = $st==='approved'?'success':($st==='rejected'?'danger':'warning text-dark');
        ?>
          <div class="mt-3">
            <div class="small text-muted">Status Absen Anda:</div>
            <span class="badge bg-<?= $badge ?>"><?= $st ?></span>
            <div class="small text-muted">Scan: <?= sanitize($myToday['ashar']['waktu_scan']) ?></div>
            <?php if (!empty($myToday['ashar']['path_foto'])): ?>
              <div class="ratio ratio-16x9 mt-2">
                <img class="w-100 h-100 object-fit-cover rounded"
                     src="/uploads/attendance/<?= sanitize($myToday['ashar']['path_foto']) ?>" alt="bukti ashar">
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($me && in_array($me['role_name'], ['admin','guru'])): ?>
  <!-- Shortcut Admin -->
  <div class="col-12">
    <div class="card card-soft">
      <div class="card-body p-3 p-md-4">
        <h2 class="h6 mb-3">Shortcut Admin</h2>
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-outline-secondary btn-pill" href="/admin/index.php">📊 Ringkasan Hari Ini</a>
          <a class="btn btn-outline-secondary btn-pill" href="/admin/attendance.php?tanggal=<?= urlencode($today) ?>&sholat=dzuhur">🖼️ Verifikasi Dzuhur</a>
          <a class="btn btn-outline-secondary btn-pill" href="/admin/attendance.php?tanggal=<?= urlencode($today) ?>&sholat=ashar">🖼️ Verifikasi Ashar</a>
          <a class="btn btn-outline-secondary btn-pill" href="/admin/poster_mingguan.php">🧾 Poster QR Mingguan</a>
          <a class="btn btn-outline-secondary btn-pill" href="/admin/jadwal.php">⏰ Pengaturan Jadwal</a>
          <a class="btn btn-outline-secondary btn-pill" href="/admin/export_csv.php?from=<?= urlencode($today) ?>&to=<?= urlencode($today) ?>&prayer=all&status=all">⬇️ Export CSV (Hari Ini)</a>
        </div>
      </div>
    </div>
  </div>
  <?php else: ?>
  <!-- Tips Penggunaan -->
  <div class="col-12">
    <div class="card card-soft">
      <div class="card-body p-3 p-md-4">
        <h2 class="h6 mb-2">Cara Pakai Cepat</h2>
        <ol class="mb-0 small text-muted">
          <li>Datang ke masjid saat jam absen dibuka.</li>
          <li>Tekan <b>Scan QR</b> atau gunakan kamera untuk scan poster.</li>
          <li>Login bila diminta, lalu unggah <b>selfie dengan poster terlihat</b>.</li>
          <li>Tunggu verifikasi guru/admin. Status tampil di halaman ini.</li>
        </ol>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/layout/footer.php'; ?>
