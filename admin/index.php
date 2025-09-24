<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';
require_role(['admin','guru']);

$today = today_id();
$tz    = new DateTimeZone('Asia/Jakarta');

/* -----------------------------
 * Helper jadwal/window
 * ----------------------------- */
function get_window_row(mysqli $db, string $prayer): ?array {
  $dow = current_dow_id();
  $stmt = $db->prepare("SELECT jam_mulai, jam_selesai, aktif FROM jendela_sholat WHERE dow=? AND sholat=? LIMIT 1");
  $stmt->bind_param('is', $dow, $prayer);
  $stmt->execute();
  $res = $stmt->get_result();
  return $res->fetch_assoc() ?: null;
}
function fmt_time(?string $t): string {
  return $t ? substr($t,0,5) : '—';
}

/* -----------------------------
 * Status jendela hari ini
 * ----------------------------- */
$wDz = get_window_row($mysqli, 'dzuhur');
$wAs = get_window_row($mysqli, 'ashar');
$openDz = $wDz && $wDz['aktif'] && is_within_window($mysqli, 'dzuhur');
$openAs = $wAs && $wAs['aktif'] && is_within_window($mysqli, 'ashar');

/* -----------------------------
 * KPI hari ini per sholat
 * ----------------------------- */
$stmt = $mysqli->prepare("
  SELECT sholat,
         COUNT(*) total_scan,
         SUM(status_verifikasi='approved') total_approved,
         SUM(status_verifikasi='pending')  total_pending,
         SUM(status_verifikasi='rejected') total_rejected
  FROM kehadiran
  WHERE tanggal=?
  GROUP BY sholat
");
$stmt->bind_param('s', $today);
$stmt->execute();
$res = $stmt->get_result();
$kpi = [];
while ($r = $res->fetch_assoc()) $kpi[$r['sholat']] = $r;

/* -----------------------------
 * Rekap per kelas (hari ini)
 * ----------------------------- */
$stmt = $mysqli->prepare("
  SELECT kls.id AS kelas_id, kls.nama AS kelas_nama,
         SUM(k.sholat='dzuhur') total_dzuhur,
         SUM(k.sholat='ashar')  total_ashar,
         SUM(k.status_verifikasi='approved') approved,
         SUM(k.status_verifikasi='pending')  pending,
         SUM(k.status_verifikasi='rejected') rejected
  FROM kehadiran k
  JOIN users u ON u.id = k.user_id
  LEFT JOIN kelas kls ON kls.id = u.class_id
  WHERE k.tanggal = ?
  GROUP BY kls.id, kls.nama
  ORDER BY kls.nama ASC
");
$stmt->bind_param('s', $today);
$stmt->execute();
$byClass = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* -----------------------------
 * Antrian verifikasi (pending) hari ini
 * ----------------------------- */
$stmt = $mysqli->prepare("
  SELECT k.id, k.user_id, u.name AS nama, IFNULL(kls.nama,'—') AS kelas_nama,
         k.tanggal, k.sholat, k.waktu_scan, k.path_foto
  FROM kehadiran k
  JOIN users u ON u.id = k.user_id
  LEFT JOIN kelas kls ON kls.id = u.class_id
  WHERE k.tanggal=? AND k.status_verifikasi='pending'
  ORDER BY k.waktu_scan ASC
  LIMIT 12
");
$stmt->bind_param('s', $today);
$stmt->execute();
$pending = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* -----------------------------
 * Tren 7 hari terakhir (per sholat)
 * ----------------------------- */
$start7 = (new DateTime('now', $tz))->modify('-6 days')->format('Y-m-d');
$stmt = $mysqli->prepare("
  SELECT tanggal, sholat,
         COUNT(*) total_scan,
         SUM(status_verifikasi='approved') approved,
         SUM(status_verifikasi='pending')  pending
  FROM kehadiran
  WHERE tanggal BETWEEN ? AND ?
  GROUP BY tanggal, sholat
  ORDER BY tanggal ASC, sholat ASC
");
$stmt->bind_param('ss', $start7, $today);
$stmt->execute();
$res7 = $stmt->get_result();
$trend = []; // $trend[YYYY-MM-DD]['dzuhur'|'ashar'] = ['total_scan'=>..,'approved'=>..,'pending'=>..]
while ($r = $res7->fetch_assoc()) {
  $trend[$r['tanggal']][$r['sholat']] = $r;
}

/* -----------------------------
 * Token hari ini (shortcut ke halaman absen)
 * ----------------------------- */
$tokDz = make_token($today, 'dzuhur');
$tokAs = make_token($today, 'ashar');

include __DIR__ . '/../layout/header.php';
?>
<style>
  .kpi-card{border:1px solid #eef1f4;border-radius:1rem;padding:14px;box-shadow:0 8px 24px rgba(0,0,0,.05)}
  .kpi-title{font-size:.8rem;color:#6c757d;text-transform:uppercase;letter-spacing:.6px}
  .kpi-value{font-size:1.6rem;font-weight:700}
  .mini{font-size:.85rem}
  .thumb{object-fit:cover}
  .table-sticky th{position:sticky;top:0;background:#fff;z-index:1}
</style>

<div class="row g-3">
  <!-- Header + Status Window -->
  <div class="col-12">
    <div class="p-3 p-md-4 bg-white border rounded-4 card-soft">
      <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-md-between gap-3">
        <div>
          <h1 class="h4 mb-1">📊 Dashboard Absensi — <?= sanitize($today) ?></h1>
          <div class="text-muted mini">Pantau ringkasan harian, antrian verifikasi, dan tren 7 hari terakhir.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <a href="/admin/jadwal.php" class="btn btn-outline-secondary btn-pill">⏰ Pengaturan Jadwal</a>
          <a href="/admin/poster_mingguan.php" class="btn btn-outline-secondary btn-pill">🧾 Poster QR</a>
          <a href="/admin/export_csv.php?from=<?= urlencode($start7) ?>&to=<?= urlencode($today) ?>&prayer=all&status=all" class="btn btn-outline-secondary btn-pill">⬇️ Export 7 Hari</a>
        </div>
      </div>
      <hr>
      <div class="row g-3">
        <div class="col-6 col-md-3">
          <div class="kpi-card h-100">
            <div class="kpi-title">Dzuhur — Window</div>
            <?php if ($wDz && $wDz['aktif']): ?>
              <div class="kpi-value"><?= $openDz ? 'Dibuka' : 'Tutup' ?></div>
              <div class="mini text-muted">Jam: <b><?= fmt_time($wDz['jam_mulai']) ?>–<?= fmt_time($wDz['jam_selesai']) ?></b> WIB</div>
              <a class="mini d-inline-block mt-2" href="/absen.php?d=<?= urlencode($today) ?>&p=dzuhur&t=<?= urlencode($tokDz) ?>">Buka Halaman Absen →</a>
            <?php else: ?>
              <div class="kpi-value">Belum diatur</div>
              <div class="mini text-muted">Set di menu Jadwal</div>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="kpi-card h-100">
            <div class="kpi-title">Ashar — Window</div>
            <?php if ($wAs && $wAs['aktif']): ?>
              <div class="kpi-value"><?= $openAs ? 'Dibuka' : 'Tutup' ?></div>
              <div class="mini text-muted">Jam: <b><?= fmt_time($wAs['jam_mulai']) ?>–<?= fmt_time($wAs['jam_selesai']) ?></b> WIB</div>
              <a class="mini d-inline-block mt-2" href="/absen.php?d=<?= urlencode($today) ?>&p=ashar&t=<?= urlencode($tokAs) ?>">Buka Halaman Absen →</a>
            <?php else: ?>
              <div class="kpi-value">Belum diatur</div>
              <div class="mini text-muted">Set di menu Jadwal</div>
            <?php endif; ?>
          </div>
        </div>
        <?php
          $dzk = $kpi['dzuhur'] ?? ['total_scan'=>0,'total_approved'=>0,'total_pending'=>0,'total_rejected'=>0];
          $ask = $kpi['ashar']  ?? ['total_scan'=>0,'total_approved'=>0,'total_pending'=>0,'total_rejected'=>0];
        ?>
        <div class="col-6 col-md-3">
          <div class="kpi-card h-100">
            <div class="kpi-title">Scan Hari Ini — Dzuhur</div>
            <div class="kpi-value"><?= (int)$dzk['total_scan'] ?></div>
            <div class="mini">✅ <?= (int)$dzk['total_approved'] ?> &nbsp; ⏳ <?= (int)$dzk['total_pending'] ?> &nbsp; ❌ <?= (int)$dzk['total_rejected'] ?></div>
            <a class="mini d-inline-block mt-2" href="/admin/attendance.php?tanggal=<?= urlencode($today) ?>&sholat=dzuhur&status=pending">Verifikasi Dzuhur →</a>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="kpi-card h-100">
            <div class="kpi-title">Scan Hari Ini — Ashar</div>
            <div class="kpi-value"><?= (int)$ask['total_scan'] ?></div>
            <div class="mini">✅ <?= (int)$ask['total_approved'] ?> &nbsp; ⏳ <?= (int)$ask['total_pending'] ?> &nbsp; ❌ <?= (int)$ask['total_rejected'] ?></div>
            <a class="mini d-inline-block mt-2" href="/admin/attendance.php?tanggal=<?= urlencode($today) ?>&sholat=ashar&status=pending">Verifikasi Ashar →</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Rekap Per Kelas (Hari Ini) -->
  <div class="col-12">
    <div class="card card-soft">
      <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-md-between gap-2 mb-2">
          <h2 class="h6 mb-0">Rekap Per Kelas — Hari Ini</h2>
          <a class="btn btn-sm btn-outline-secondary btn-pill"
             href="/admin/export_csv.php?from=<?= urlencode($today) ?>&to=<?= urlencode($today) ?>&prayer=all&status=all">Export CSV (Hari Ini)</a>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0 table-sticky">
            <thead>
              <tr>
                <th style="min-width:160px">Kelas</th>
                <th>Dzuhur</th>
                <th>Ashar</th>
                <th>Approved</th>
                <th>Pending</th>
                <th>Rejected</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($byClass)): ?>
                <tr><td colspan="6" class="text-muted">Belum ada data untuk hari ini.</td></tr>
              <?php else: foreach ($byClass as $row): ?>
                <tr>
                  <td><?= sanitize($row['kelas_nama'] ?? '—') ?></td>
                  <td><?= (int)$row['total_dzuhur'] ?></td>
                  <td><?= (int)$row['total_ashar'] ?></td>
                  <td><span class="badge bg-success"><?= (int)$row['approved'] ?></span></td>
                  <td><span class="badge bg-warning text-dark"><?= (int)$row['pending'] ?></span></td>
                  <td><span class="badge bg-danger"><?= (int)$row['rejected'] ?></span></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Antrian Verifikasi (Pending) -->
  <div class="col-12">
    <div class="card card-soft">
      <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-md-between gap-2 mb-2">
          <h2 class="h6 mb-0">Antrian Verifikasi — Pending (Maks 12 Terbaru)</h2>
          <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-secondary btn-pill" href="/admin/attendance.php?tanggal=<?= urlencode($today) ?>&sholat=dzuhur&status=pending">Verifikasi Dzuhur</a>
            <a class="btn btn-sm btn-outline-secondary btn-pill" href="/admin/attendance.php?tanggal=<?= urlencode($today) ?>&sholat=ashar&status=pending">Verifikasi Ashar</a>
          </div>
        </div>
        <?php if (empty($pending)): ?>
          <div class="text-muted">Tidak ada antrian pending saat ini.</div>
        <?php else: ?>
          <div class="row g-3">
            <?php foreach ($pending as $r): ?>
              <div class="col-6 col-md-3 col-lg-2">
                <div class="border rounded-4 overflow-hidden">
                  <div class="ratio ratio-1x1 bg-light">
                    <?php if (!empty($r['path_foto'])): ?>
                      <img src="/uploads/attendance/<?= sanitize($r['path_foto']) ?>" class="w-100 h-100 thumb" loading="lazy" alt="bukti">
                    <?php else: ?>
                      <div class="d-flex align-items-center justify-content-center text-muted">No Photo</div>
                    <?php endif; ?>
                  </div>
                  <div class="p-2 small">
                    <div class="fw-semibold text-truncate" title="<?= sanitize($r['nama']) ?>"><?= sanitize($r['nama']) ?></div>
                    <div class="text-muted text-truncate" title="<?= sanitize($r['kelas_nama']) ?>"><?= sanitize($r['kelas_nama']) ?></div>
                    <div><span class="badge bg-dark-subtle text-dark"><?= strtoupper(sanitize($r['sholat'])) ?></span></div>
                    <div class="text-muted"><?= sanitize($r['waktu_scan']) ?></div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Tren 7 Hari Terakhir -->
  <div class="col-12">
    <div class="card card-soft">
      <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-md-between gap-2 mb-2">
          <h2 class="h6 mb-0">Tren 7 Hari Terakhir</h2>
          <div class="mini text-muted">Per sholat per hari (total & approved)</div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0 table-sticky">
            <thead>
              <tr>
                <th style="min-width:120px">Tanggal</th>
                <th>Dzuhur — Total</th>
                <th>Dzuhur — Approved</th>
                <th>Ashar — Total</th>
                <th>Ashar — Approved</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($trend)): ?>
                <tr><td colspan="5" class="text-muted">Belum ada data 7 hari terakhir.</td></tr>
              <?php else: foreach ($trend as $tgl => $rows): 
                $dz = $rows['dzuhur'] ?? ['total_scan'=>0,'approved'=>0];
                $as = $rows['ashar']  ?? ['total_scan'=>0,'approved'=>0];
              ?>
                <tr>
                  <td><?= sanitize($tgl) ?></td>
                  <td><?= (int)$dz['total_scan'] ?></td>
                  <td><span class="badge bg-success"><?= (int)$dz['approved'] ?></span></td>
                  <td><?= (int)$as['total_scan'] ?></td>
                  <td><span class="badge bg-success"><?= (int)$as['approved'] ?></span></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
