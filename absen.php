<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';

// Ambil query
$d = $_GET['d'] ?? '';
$p = $_GET['p'] ?? '';
$t = $_GET['t'] ?? '';

include __DIR__ . '/layout/header.php';
?>
<div class="row g-3">
  <div class="col-12 col-lg-8 mx-auto">
    <div class="card card-soft">
      <div class="card-body p-4">

<?php
// Validasi parameter awal (tampilkan pesan ramah di UI)
$today = today_id();
$errors = [];

if ($d !== $today) $errors[] = "QR ini hanya untuk tanggal <b>$today</b>.";
if (!is_valid_prayer($p)) $errors[] = "Jenis sholat tidak valid.";
if ($t !== make_token($d, $p)) $errors[] = "Token QR tidak valid.";
if (!is_within_window($mysqli, $p)) $errors[] = "Di luar jam absen untuk sholat <b>$p</b>.";

if (!empty($errors)):
?>
        <div class="alert alert-danger mb-0">
          <div class="fw-bold mb-1">Tidak dapat memproses absen:</div>
          <ul class="mb-0">
            <?php foreach ($errors as $e): ?>
              <li><?= $e ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div></div></div></div>
<?php
  include __DIR__ . '/layout/footer.php'; exit;
endif;

// Wajib login
if (empty($_SESSION['user'])) {
  echo '<div class="alert alert-info">Silakan masuk terlebih dahulu untuk melanjutkan absen.</div>';
  echo '<a class="btn btn-primary btn-pill" href="/login.php?next=' . urlencode($_SERVER['REQUEST_URI']) . '">Masuk</a>';
  echo '</div></div></div></div>';
  include __DIR__ . '/layout/footer.php'; exit;
}
?>

        <div class="text-center mb-3">
          <div class="badge bg-success-subtle text-success border border-success">Siap Absen</div>
          <h1 class="h5 mt-2 mb-0">Sholat <?= sanitize($p) ?> — <?= sanitize($d) ?></h1>
          <p class="text-muted small mb-0">Wajib unggah foto selfie dengan sebagian poster QR terlihat.</p>
        </div>

        <form id="absenForm" class="mt-3" method="post" action="/absen_submit.php" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="d" value="<?= sanitize($d) ?>">
          <input type="hidden" name="p" value="<?= sanitize($p) ?>">
          <input type="hidden" name="t" value="<?= sanitize($t) ?>">
          <input type="hidden" name="device_id" id="device_id">
          <input type="hidden" name="lat" id="lat">
          <input type="hidden" name="lng" id="lng">
          <input type="hidden" name="acc" id="acc">

          <div class="mb-3">
            <label class="form-label">Foto Selfie (kamera belakang disarankan)</label>
            <!-- <input class="form-control" type="file" name="photo" accept="image/*" capture="environment" required> -->
            <input class="form-control" type="file" id="photo" name="photo" accept="image/*" capture="environment" required>

            <div class="form-text">Pastikan wajah & bagian poster QR (emoji/kode hari) terlihat jelas.</div>
          </div>

          <div class="d-grid gap-2">
            <button class="btn btn-primary btn-pill" type="submit">Kirim Absen</button>
            <a class="btn btn-outline-secondary btn-pill" href="/">Batal</a>
          </div>
        </form>

      </div>
    </div>
  </div>
</div>

<script>
// Device ID ringan (localStorage)
(function(){
  try{
    let id = localStorage.getItem('appsholat_device_id');
    if(!id){ id = (Math.random().toString(36).slice(2) + Date.now().toString(36)); localStorage.setItem('appsholat_device_id', id); }
    document.getElementById('device_id').value = id;
  }catch(e){}
})();

// Coba ambil lokasi (opsional)
if (navigator.geolocation) {
  navigator.geolocation.getCurrentPosition(function(pos){
    document.getElementById('lat').value = pos.coords.latitude.toFixed(6);
    document.getElementById('lng').value = pos.coords.longitude.toFixed(6);
    document.getElementById('acc').value = Math.round(pos.coords.accuracy);
  }, function(){ /* user menolak, tidak masalah */ }, { enableHighAccuracy:true, timeout:5000, maximumAge:0 });
}
</script>

<script>
const form = document.getElementById('absenForm');
const input = document.getElementById('photo');

form.addEventListener('submit', async (e) => {
  if (!input.files || !input.files[0]) return;
  e.preventDefault();

  const file = input.files[0];
  const img = new Image();
  img.src = URL.createObjectURL(file);
  await img.decode();

  const maxSide = 1600; // samakan dengan server
  let {width:w, height:h} = img;
  const scale = Math.min(1, maxSide / Math.max(w,h));
  const nw = Math.round(w*scale), nh = Math.round(h*scale);

  const canvas = document.createElement('canvas');
  canvas.width = nw; canvas.height = nh;
  const ctx = canvas.getContext('2d');
  ctx.fillStyle = '#fff'; ctx.fillRect(0,0,nw,nh); // bg putih utk png/heic
  ctx.drawImage(img, 0,0, nw,nh);

  const blob = await new Promise(res => canvas.toBlob(res, 'image/jpeg', 0.78));
  if (!blob) { form.submit(); return; } // fallback

  // ganti file input dengan blob terkompres
  const dt = new DataTransfer();
  dt.items.add(new File([blob], 'selfie.jpg', {type:'image/jpeg'}));
  input.files = dt.files;

  URL.revokeObjectURL(img.src);
  form.submit();
});
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
