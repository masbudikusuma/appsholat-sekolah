<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';
require_role(['admin','guru']);

$hariLabel = [0=>'Minggu',1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) { http_response_code(400); exit('CSRF invalid'); }
  // Expect fields: jam_mulai[dow][prayer], jam_selesai[dow][prayer]
  foreach ($_POST['jam_mulai'] ?? [] as $dow => $prayers) {
    foreach ($prayers as $p => $start) {
      $end = $_POST['jam_selesai'][$dow][$p] ?? null;
      if (!in_array($p, ['dzuhur','ashar'], true)) continue;
      if (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) continue;
      $start .= ':00'; $end .= ':00';
      // Upsert
      $stmt = $mysqli->prepare("INSERT INTO jendela_sholat (dow,sholat,jam_mulai,jam_selesai,aktif)
        VALUES (?,?,?,?,1)
        ON DUPLICATE KEY UPDATE jam_mulai=VALUES(jam_mulai), jam_selesai=VALUES(jam_selesai), aktif=1");
      $dowI = (int)$dow;
      $stmt->bind_param('isss', $dowI, $p, $start, $end);
      $stmt->execute();
    }
  }
  $msg = 'Jadwal tersimpan.';
}

// Ambil jadwal sekarang
$rows = [];
$res = $mysqli->query("SELECT dow, sholat, jam_mulai, jam_selesai, aktif FROM jendela_sholat");
while ($r = $res->fetch_assoc()) {
  $rows[(int)$r['dow']][$r['sholat']] = $r;
}

include __DIR__ . '/../layout/header.php';
?>
<div class="row">
  <div class="col-12 col-lg-10 mx-auto">
    <div class="card card-soft">
      <div class="card-body p-3 p-md-4">
        <h1 class="h5 mb-3">Pengaturan Jadwal Absen</h1>
        <?php if (!empty($msg)): ?><div class="alert alert-success py-2"><?= sanitize($msg) ?></div><?php endif; ?>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <div class="table-responsive">
            <table class="table align-middle">
              <thead><tr><th>Hari</th><th>Sholat</th><th>Mulai</th><th>Selesai</th></tr></thead>
              <tbody>
              <?php for ($dow=1;$dow<=5;$dow++): // Senin..Jumat ?>
                <?php foreach (['dzuhur','ashar'] as $p): 
                  $mulai = isset($rows[$dow][$p]) ? substr($rows[$dow][$p]['jam_mulai'],0,5) : ($p==='dzuhur'?'12:00':'15:15');
                  $selesai = isset($rows[$dow][$p]) ? substr($rows[$dow][$p]['jam_selesai'],0,5) : ($p==='dzuhur'?'12:25':'15:40');
                ?>
                <tr>
                  <td><?= $hariLabel[$dow] ?></td>
                  <td class="text-uppercase"><?= $p ?></td>
                  <td style="max-width:130px">
                    <input type="time" class="form-control form-control-sm" name="jam_mulai[<?= $dow ?>][<?= $p ?>]" value="<?= $mulai ?>">
                  </td>
                  <td style="max-width:130px">
                    <input type="time" class="form-control form-control-sm" name="jam_selesai[<?= $dow ?>][<?= $p ?>]" value="<?= $selesai ?>">
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endfor; ?>
              </tbody>
            </table>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-primary btn-pill">Simpan</button>
            <a class="btn btn-outline-secondary btn-pill" href="/admin/index.php">Kembali</a>
          </div>
        </form>
        <p class="small text-muted mt-3">
          *Atur khusus <b>Jumat</b> (Dzuhur biasanya lebih awal/lebih panjang). Hari Sabtu/Minggu tidak ditampilkan di sini.
        </p>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../layout/footer.php'; ?>
