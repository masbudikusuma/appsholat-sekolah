<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';
require_role(['admin','guru']);

$tanggal = $_GET['tanggal'] ?? today_id();
$sholat  = $_GET['sholat'] ?? 'dzuhur';
$status  = $_GET['status'] ?? 'pending'; // default fokus yang perlu verifikasi

$q = "
SELECT k.id, k.user_id, u.name AS nama, u.class_id,
       k.tanggal, k.sholat, k.waktu_scan, k.path_foto, k.status_verifikasi,
       k.lat, k.lng, k.akurasi_lokasi, k.id_perangkat
FROM kehadiran k
JOIN users u ON u.id = k.user_id
WHERE k.tanggal = ?
  AND k.sholat  = ?
";
$params = [$tanggal, $sholat];
$types  = 'ss';
if (in_array($status, ['pending','approved','rejected'], true)) {
  $q .= " AND k.status_verifikasi = ? ";
  $params[] = $status; $types .= 's';
}
$q .= " ORDER BY k.waktu_scan ASC ";

$stmt = $mysqli->prepare($q);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

include __DIR__ . '/../layout/header.php';
?>
<div class="row g-3">
  <div class="col-12">
    <div class="card card-soft">
      <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-md-between gap-2 mb-3">
          <h1 class="h5 mb-0">Verifikasi Kehadiran — <?= sanitize($tanggal) ?> (<?= sanitize($sholat) ?>)</h1>
          <form class="d-flex gap-2" method="get">
            <input type="date" class="form-control form-control-sm" name="tanggal" value="<?= sanitize($tanggal) ?>">
            <select name="sholat" class="form-select form-select-sm">
              <option value="dzuhur" <?= $sholat==='dzuhur'?'selected':'' ?>>Dzuhur</option>
              <option value="ashar"  <?= $sholat==='ashar'?'selected':''  ?>>Ashar</option>
            </select>
            <select name="status" class="form-select form-select-sm">
              <option <?= $status==='pending'?'selected':'' ?> value="pending">Pending</option>
              <option <?= $status==='approved'?'selected':'' ?> value="approved">Approved</option>
              <option <?= $status==='rejected'?'selected':'' ?> value="rejected">Rejected</option>
              <option <?= $status==='all'?'selected':'' ?> value="all">Semua</option>
            </select>
            <button class="btn btn-sm btn-outline-secondary btn-pill">Terapkan</button>
          </form>
        </div>

        <form id="bulkForm" method="post" action="/admin/attendance_verify.php">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <div class="row g-3">
          <?php while ($r = $res->fetch_assoc()): ?>
            <div class="col-6 col-md-4 col-lg-3">
              <div class="border rounded-4 overflow-hidden h-100 d-flex flex-column">
                <div class="ratio ratio-1x1 bg-light">
                  <img loading="lazy" src="/uploads/attendance/<?= sanitize($r['path_foto']) ?>" class="w-100 h-100 object-fit-cover" alt="foto">
                </div>
                <div class="p-2 small">
                  <div class="fw-semibold"><?= sanitize($r['nama']) ?> <span class="text-muted">#<?= (int)$r['user_id'] ?></span></div>
                  <div class="text-muted">Scan: <?= sanitize($r['waktu_scan']) ?></div>
                  <?php if (!empty($r['lat']) && !empty($r['lng'])): ?>
                    <div class="text-muted">Lok: <?= (float)$r['lat'] ?>, <?= (float)$r['lng'] ?> (±<?= (int)$r['akurasi_lokasi'] ?> m)</div>
                  <?php endif; ?>
                  <div class="mt-2 d-flex gap-1">
                    <label class="me-2"><input type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>"> pilih</label>
                    <span class="ms-auto badge <?= $r['status_verifikasi']==='approved'?'bg-success':($r['status_verifikasi']==='rejected'?'bg-danger':'bg-warning text-dark') ?>">
                      <?= $r['status_verifikasi'] ?>
                    </span>
                  </div>
                </div>
              </div>
            </div>
          <?php endwhile; ?>
          </div>

          <div class="d-flex gap-2 mt-3">
            <button name="action" value="approve" class="btn btn-success btn-pill">Setujui Terpilih</button>
            <button name="action" value="reject"  class="btn btn-outline-danger btn-pill">Tolak Terpilih</button>
          </div>

          <input type="hidden" name="tanggal" value="<?= sanitize($tanggal) ?>">
          <input type="hidden" name="sholat"  value="<?= sanitize($sholat) ?>">
        </form>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../layout/footer.php'; ?>
