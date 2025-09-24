<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';
require_login();

/* -------------------------------------------------
 * Helper tampilan sederhana
 * ------------------------------------------------- */
function show_page(string $title, string $stateIcon, string $accentClass, string $bodyHtml, ?string $redirectUrl=null, int $redirectSec=0): void {
  include __DIR__ . '/layout/header.php';
  ?>
  <div class="row justify-content-center">
    <div class="col-12 col-md-8">
      <div class="card card-soft">
        <div class="card-body p-4 text-center">
          <div class="display-6 mb-2"><?= $stateIcon ?></div>
          <h1 class="h5 mb-2"><?= sanitize($title) ?></h1>
          <div class="<?= $accentClass ?> mx-auto text-start" style="max-width:720px">
            <?= $bodyHtml /* sudah ter-sanitize sesuai kebutuhan */ ?>
          </div>
          <div class="d-grid gap-2 mt-3">
            <a href="/" class="btn btn-primary btn-pill">Kembali ke Beranda</a>
            <button class="btn btn-outline-secondary btn-pill" onclick="history.back()">Kembali ke Halaman Absen</button>
          </div>
          <?php if ($redirectUrl && $redirectSec>0): ?>
            <div class="small text-muted mt-3">Mengalihkan dalam <span id="rsec"><?= (int)$redirectSec ?></span> detik…</div>
            <script>
              (function(){
                var s = <?= (int)$redirectSec ?>;
                var el = document.getElementById('rsec');
                var t = setInterval(function(){
                  s--; if(el) el.textContent = s;
                  if(s<=0){ clearInterval(t); location.href = <?= json_encode($redirectUrl) ?>; }
                },1000);
              })();
            </script>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php
  include __DIR__ . '/layout/footer.php';
  exit;
}

/* -------------------------------------------------
 * Validasi CSRF
 * ------------------------------------------------- */
if (!csrf_check($_POST['csrf'] ?? '')) {
  show_page(
    'Gagal: Keamanan Form',
    '⚠️',
    'alert alert-danger mt-2',
    '<p>Token keamanan (CSRF) tidak valid atau kedaluwarsa. Silakan muat ulang halaman absen dan coba lagi.</p>'
  );
}

/* -------------------------------------------------
 * Ambil & validasi parameter
 * ------------------------------------------------- */
$d = $_POST['d'] ?? '';
$p = $_POST['p'] ?? '';
$t = $_POST['t'] ?? '';
$device_id = substr($_POST['device_id'] ?? '', 0, 64);
$lat = $_POST['lat'] ?? null; 
$lng = $_POST['lng'] ?? null; 
$acc = $_POST['acc'] ?? null;

$today = today_id();
$errors = [];

if ($d !== $today)               $errors[] = 'Tanggal pada QR tidak sesuai dengan hari ini.';
if (!is_valid_prayer($p))        $errors[] = 'Jenis sholat tidak valid.';
if (!hash_equals(make_token($d,$p), $t)) $errors[] = 'Token QR tidak valid.';
if (!is_within_window($mysqli,$p))       $errors[] = 'Saat ini di luar jendela waktu absen.';

// Tampilkan error validasi awal (lebih ramah)
if (!empty($errors)) {
  $lis = '<ul class="mb-0">';
  foreach ($errors as $e) $lis .= '<li>'.sanitize($e).'</li>';
  $lis .= '</ul>';
  show_page(
    'Tidak dapat memproses absen',
    '❌',
    'alert alert-danger mt-2',
    '<p class="mb-2">Mohon periksa hal berikut:</p>'.$lis
  );
}

/* -------------------------------------------------
 * Simpan foto (JPG/PNG, opsional HEIC->JPG jika Imagick tersedia)
 * ------------------------------------------------- */
$uid = user_id();
$ts  = time();
$fnameBase = sprintf('%s_%s_%d_%d', str_replace('-','',$d), $p, $uid, $ts);

// Pastikan direktori upload ada & writable (gunakan UPLOAD_DIR jika didefinisikan di config.php)
$uploadDir = defined('UPLOAD_DIR') ? UPLOAD_DIR : (__DIR__ . '/uploads/attendance');
$rel = save_photo_upload($_FILES['photo'] ?? [], $uploadDir, $fnameBase);

if ($rel === null) {
  // Pesan lebih informatif ke pengguna
  $msg = '<p>Unggah foto gagal. Pastikan:</p>
  <ul>
    <li>Format gambar <b>JPG/PNG</b> (HEIC/iPhone perlu diubah ke JPG atau aktifkan “Most Compatible”).</li>
    <li>Ukuran foto tidak terlalu besar (maks &plusmn; 3&nbsp;MB).</li>
    <li>Berikan izin akses kamera/berkas jika diminta.</li>
  </ul>';
  show_page('Unggah Foto Gagal', '🖼️', 'alert alert-warning mt-2', $msg);
}

/* -------------------------------------------------
 * Insert / upsert kehadiran
 * Catatan: jika lat/lng/acc null, bind_param tipe numerik dapat menjadi 0.
 * Untuk benar-benar NULL, perlu penanganan terpisah. Untuk MVP ini, 0 aman.
 * ------------------------------------------------- */
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$latF = is_numeric($lat) ? (float)$lat : 0.0;
$lngF = is_numeric($lng) ? (float)$lng : 0.0;
$accI = is_numeric($acc) ? (int)$acc : 0;

$stmt = $mysqli->prepare("
  INSERT INTO kehadiran
  (user_id, tanggal, sholat, waktu_scan, path_foto, hash_token, id_perangkat, agen_pengguna, ip, lat, lng, akurasi_lokasi, status_verifikasi)
  VALUES (?, ?, ?, NOW(), ?, MD5(?), ?, ?, ?, ?, ?, ?, 'pending')
  ON DUPLICATE KEY UPDATE
    waktu_scan=VALUES(waktu_scan),
    path_foto=VALUES(path_foto),
    hash_token=VALUES(hash_token),
    id_perangkat=VALUES(id_perangkat),
    agen_pengguna=VALUES(agen_pengguna),
    ip=VALUES(ip),
    lat=VALUES(lat),
    lng=VALUES(lng),
    akurasi_lokasi=VALUES(akurasi_lokasi),
    status_verifikasi='pending',
    diperbarui_pada=NOW()
");
if (!$stmt) {
  show_page(
    'Gagal Menyimpan Absen',
    '⚠️',
    'alert alert-danger mt-2',
    '<p>Koneksi basis data bermasalah: '.sanitize($mysqli->error).'</p>'
  );
}
$stmt->bind_param(
  'isssssssddi',
  $uid, $d, $p, $rel, $t, $device_id, $ua, $ip, $latF, $lngF, $accI
);
$ok = $stmt->execute();

/* -------------------------------------------------
 * Tampilkan hasil yang lebih informatif & enak dilihat
 * ------------------------------------------------- */
if ($ok) {
  $imgUrl = '/uploads/attendance/' . rawurlencode($rel);
  $detail = sprintf(
    '<div class="row g-3 align-items-center">
      <div class="col-12 col-md-6">
        <div class="ratio ratio-1x1 bg-light rounded">
          <img src="%s" alt="foto absensi" class="w-100 h-100 object-fit-cover rounded">
        </div>
      </div>
      <div class="col-12 col-md-6">
        <ul class="list-unstyled mb-0 small">
          <li><b>Tanggal</b>: %s</li>
          <li><b>Sholat</b>: %s</li>
          <li><b>Perangkat</b>: %s</li>
          %s
        </ul>
        <div class="alert alert-info mt-3">
          Status: <b>menunggu verifikasi</b> oleh guru/admin.
        </div>
      </div>
    </div>',
    sanitize($imgUrl),
    sanitize($d),
    strtoupper(sanitize($p)),
    sanitize($device_id ?: '-'),
    ($latF && $lngF) ? '<li><b>Lokasi</b>: '.sanitize((string)$latF).', '.sanitize((string)$lngF).($accI? ' (±'.sanitize((string)$accI).' m)':'').'</li>' : ''
  );

  show_page(
    'Absen terkirim',
    '✅',
    'mt-2',
    $detail,
    '/', // redirect ke beranda
    6    // dalam 6 detik
  );
} else {
  $err = $mysqli->error ?: 'Tidak diketahui.';
  show_page(
    'Gagal Menyimpan Absen',
    '⚠️',
    'alert alert-danger mt-2',
    '<p>Terjadi masalah saat menyimpan kehadiran.</p><pre class="small mb-0">'.sanitize($err).'</pre>'
  );
}
