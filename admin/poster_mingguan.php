<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';
require_role(['admin','guru']);

$baseUrl = 'https://appsholat.ayosinau.id/absen.php';
$tz = new DateTimeZone('Asia/Jakarta');

/** Tentukan Senin minggu yang dipilih (default: minggu ini) */
$awal = $_GET['awal'] ?? null; // format YYYY-MM-DD (Senin)
if ($awal) {
  $monday = DateTime::createFromFormat('Y-m-d', $awal, $tz);
} else {
  $today = new DateTime('now', $tz);
  $w = (int)$today->format('N'); // 1=Senin..7=Minggu
  $monday = (clone $today)->modify('-' . ($w-1) . ' days');
}
$monday->setTime(0,0,0);

$days = [];
for ($i=0;$i<5;$i++) { // Senin..Jumat
  $d = (clone $monday)->modify("+$i day");
  $days[] = $d;
}

/** Helper membuat QR URL */
function qr_src(string $txt, int $size=260): string {
  // QuickChart QR (butuh internet). Alternatif: ganti ke lib lokal bila tersedia.
  $enc = urlencode($txt);
  return "https://quickchart.io/qr?text={$enc}&size={$size}&margin=2";
}

include __DIR__ . '/../layout/header.php';
?>
<div class="row mb-3">
  <div class="col-12 col-lg-10 mx-auto">
    <div class="card card-soft">
      <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
          <h1 class="h5 mb-0">Poster QR Mingguan</h1>
          <form class="d-flex gap-2" method="get">
            <label class="form-label mb-0 me-2 small">Senin mulai</label>
            <input type="date" class="form-control form-control-sm" name="awal" value="<?= htmlspecialchars($monday->format('Y-m-d')) ?>">
            <button class="btn btn-sm btn-outline-secondary btn-pill">Buat</button>
            <?php if (class_exists('Dompdf\Dompdf')): ?>
              <a class="btn btn-sm btn-primary btn-pill" href="?awal=<?= urlencode($monday->format('Y-m-d')) ?>&pdf=1">Unduh PDF</a>
            <?php endif; ?>
          </form>
        </div>
        <hr>
        <p class="small text-muted mb-3">
          Cetak di kertas A4. Tempel dekat area masjid. Setiap kotak berlaku khusus **hari & jam**-nya.
        </p>

        <div class="d-print-block">
          <div class="row g-3">
            <?php
            $idDays = ['Senin','Selasa','Rabu','Kamis','Jumat'];
            foreach ($days as $idx => $dt):
              $tgl = $dt->format('Y-m-d');
              foreach (['dzuhur','ashar'] as $p):
                $token = make_token($tgl, $p);
                $absenUrl = "{$baseUrl}?d={$tgl}&p={$p}&t={$token}";
            ?>
            <div class="col-12 col-sm-6 col-lg-6">
              <div class="border rounded-4 p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <div class="fw-bold"><?= $idDays[$idx] ?> — <?= htmlspecialchars($tgl) ?></div>
                  <div class="badge bg-dark-subtle text-dark"><?= strtoupper($p) ?></div>
                </div>
                <div class="ratio ratio-1x1 bg-white rounded d-flex align-items-center justify-content-center">
                  <img src="<?= qr_src($absenUrl) ?>" alt="QR" class="w-100 h-100 object-fit-contain">
                </div>
                <div class="small text-muted mt-2">
                  Scan QR ini lalu lakukan selfie. Berlaku hanya pada jam absen <?= $p ?>.
                  <div class="text-wrap" style="word-break:break-all"><code class="small"><?= htmlspecialchars($absenUrl) ?></code></div>
                </div>
              </div>
            </div>
            <?php endforeach; endforeach; ?>
          </div>
        </div>

        <div class="mt-3 d-print-none">
          <button class="btn btn-success btn-pill" onclick="window.print()">Cetak</button>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
// Opsi PDF via Dompdf bila tersedia & ?pdf=1
if (isset($_GET['pdf']) && class_exists('Dompdf\Dompdf')) {
  ob_start(); // kumpulkan HTML ulang dengan layout minimal
  ?>
  <html><head>
  <meta charset="utf-8"><style>
  body { font-family: sans-serif; }
  .grid{display:flex;flex-wrap:wrap;gap:12px}
  .card{border:1px solid #ccc;border-radius:12px;padding:10px;width:calc(50% - 12px)}
  .qr{width:100%;height:auto}
  .meta{font-size:12px;color:#555}
  </style></head><body>
  <h3>Poster QR Mingguan (<?= htmlspecialchars($monday->format('Y-m-d')) ?> s.d. <?= htmlspecialchars(end($days)->format('Y-m-d')) ?>)</h3>
  <div class="grid">
  <?php
  $idDays = ['Senin','Selasa','Rabu','Kamis','Jumat'];
  foreach ($days as $idx => $dt):
    $tgl = $dt->format('Y-m-d');
    foreach (['dzuhur','ashar'] as $p):
      $token = make_token($tgl, $p);
      $absenUrl = "{$baseUrl}?d={$tgl}&p={$p}&t={$token}";
  ?>
    <div class="card">
      <div><b><?= $idDays[$idx] ?></b> — <?= htmlspecialchars($tgl) ?> — <b><?= strtoupper($p) ?></b></div>
      <img class="qr" src="<?= qr_src($absenUrl, 420) ?>">
      <div class="meta">Berlaku hanya pada jam absen. <?= htmlspecialchars($absenUrl) ?></div>
    </div>
  <?php endforeach; endforeach; ?>
  </div></body></html>
  <?php
  $html = ob_get_clean();
  $dompdf = new Dompdf\Dompdf();
  $dompdf->loadHtml($html);
  $dompdf->setPaper('A4', 'portrait');
  $dompdf->render();
  $dompdf->stream('poster_qr_mingguan.pdf', ['Attachment'=>true]);
  exit;
}
include __DIR__ . '/../layout/footer.php';
