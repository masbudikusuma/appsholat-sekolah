<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';
require_login(); // siswa/guru/admin boleh

include __DIR__ . '/layout/header.php';
?>
<div class="row justify-content-center">
  <div class="col-12 col-sm-10 col-md-8">
    <div class="card card-soft">
      <div class="card-body p-3 p-md-4">
        <h1 class="h5 mb-2">Scan QR Absen</h1>
        <p class="text-muted small">Arahkan kamera ke poster QR yang ditempel di area masjid. Setelah terdeteksi, kamu akan diarahkan ke halaman absen.</p>
        <div id="qr-reader" style="width:100%; max-width:420px" class="mx-auto"></div>
        <div id="qr-result" class="mt-3 small text-muted"></div>
      </div>
    </div>
  </div>
</div>

<script src="https://unpkg.com/html5-qrcode" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  function handleDecoded(text){
    const out = document.getElementById('qr-result');
    out.textContent = 'Terbaca: ' + text;

    // Jika QR berisi URL appsholat -> langsung arahkan
    try {
      if (text.startsWith('http')) {
        const u = new URL(text);
        if (u.hostname.includes('appsholat.ayosinau.id') && u.pathname === '/absen') {
          window.location.href = text;
          return;
        }
      }
    } catch(e){}

    // Jika berisi payload kode (opsional masa depan): v=1.d=YYYY-MM-DD.p=dzuhur.t=TOKEN
    if (/v=1\.d=\d{4}-\d{2}-\d{2}\.p=(dzuhur|ashar)\.t=[a-f0-9]{16}/i.test(text)) {
      const d = /d=(\d{4}-\d{2}-\d{2})/i.exec(text)[1];
      const p = /p=(dzuhur|ashar)/i.exec(text)[1];
      const t = /t=([a-f0-9]{16})/i.exec(text)[1];
      const url = `/absen?d=${encodeURIComponent(d)}&p=${encodeURIComponent(p)}&t=${encodeURIComponent(t)}`;
      window.location.href = url;
      return;
    }

    alert('Format QR tidak dikenali');
  }

  function startScanner(){
    const qr = new Html5Qrcode("qr-reader");
    Html5Qrcode.getCameras().then(devs => {
      const camId = (devs && devs[0]) ? devs[0].id : null;
      if (!camId) { document.getElementById('qr-result').textContent='Kamera tidak ditemukan.'; return; }
      qr.start(camId, { fps: 10, qrbox: 260 }, handleDecoded, err => {});
    }).catch(() => { document.getElementById('qr-result').textContent='Tidak bisa mengakses kamera.'; });
  }
  startScanner();
});
</script>
<?php include __DIR__ . '/layout/footer.php'; ?>
