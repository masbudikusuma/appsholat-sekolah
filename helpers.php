<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php'; // $mysqli, SECRET_KEY, default TZ

/* ---------- SECURITY ---------- */
function csrf_token(): string {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}
function csrf_check(string $tokenFromPost): bool {
  return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $tokenFromPost);
}
function sanitize(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ---------- AUTH ---------- */
function require_login(): void {
  if (empty($_SESSION['user'])) {
    header('Location: /login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    exit;
  }
}
function require_role(array $roles): void {
  require_login();
  $role = $_SESSION['user']['role_name'] ?? '';
  if (!in_array($role, $roles, true)) {
    http_response_code(403);
    exit('Akses ditolak.');
  }
}

/* ---------- TOKEN & WINDOW ---------- */
function make_token(string $date, string $prayer): string {
  $raw = hash_hmac('sha256', $date . '|' . $prayer, SECRET_KEY, false);
  return substr($raw, 0, 16);
}
function is_valid_prayer(string $p): bool {
  return in_array($p, ['dzuhur','ashar'], true);
}
function today_id(): string {
  $tz = new DateTimeZone('Asia/Jakarta');
  return (new DateTime('now', $tz))->format('Y-m-d');
}
function current_dow_id(): int {
  // 0=Ahad(Minggu) ... 6=Sabtu
  $tz = new DateTimeZone('Asia/Jakarta');
  return (int)(new DateTime('now',$tz))->format('w');
}
function is_within_window(mysqli $db, string $prayer): bool {
  $dow = current_dow_id();
  $stmt = $db->prepare("SELECT jam_mulai, jam_selesai FROM jendela_sholat WHERE dow=? AND sholat=? AND aktif=1 LIMIT 1");
  $stmt->bind_param('is', $dow, $prayer);
  $stmt->execute();
  $res = $stmt->get_result();
  if (!$row = $res->fetch_assoc()) return false;

  $tz = new DateTimeZone('Asia/Jakarta');
  $now = new DateTime('now', $tz);

  $start = DateTime::createFromFormat('H:i:s', $row['jam_mulai'], $tz);
  $end   = DateTime::createFromFormat('H:i:s', $row['jam_selesai'], $tz);
  $start->setDate((int)$now->format('Y'), (int)$now->format('m'), (int)$now->format('d'));
  $end->setDate((int)$now->format('Y'), (int)$now->format('m'), (int)$now->format('d'));

  return ($now >= $start && $now <= $end);
}

/* ---------- FILE UPLOAD ---------- */
function ensure_dir(string $dir): void {
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
}
function save_photo_upload(array $file, string $destDir, string $filenameBase): ?string {
  // Jika ada konstanta UPLOAD_DIR, pakai itu agar konsisten
  if (defined('UPLOAD_DIR')) {
    $destDir = UPLOAD_DIR;
  }

  if (!isset($file['error']) || is_array($file['error'])) { error_log('upload: invalid array'); return null; }
  if ($file['error'] !== UPLOAD_ERR_OK) { error_log('upload: error code '.$file['error']); return null; }
  if ($file['size'] > 3_000_000) { error_log('upload: too big'); return null; } // 3 MB

  // Pastikan dir ada & writable
  if (!is_dir($destDir)) {
    @mkdir($destDir, 0775, true);
  }
  if (!is_dir($destDir) || !is_writable($destDir)) {
    error_log('upload: directory not writable '.$destDir);
    return null;
  }

  // Deteksi MIME
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime  = $finfo->file($file['tmp_name']) ?: '';
  $mime = strtolower($mime);

  // Normalisasi: terima jpg/png; dukung heic/heif jika Imagick ada
  $ext = '';
  if ($mime === 'image/jpeg') $ext = '.jpg';
  elseif ($mime === 'image/png') $ext = '.png';
  elseif (in_array($mime, ['image/heic','image/heif'], true)) {
    if (class_exists('Imagick')) {
      // Konversi ke JPG
      try {
        $img = new Imagick($file['tmp_name']);
        $img->setImageFormat('jpeg');
        $img->setImageCompression(Imagick::COMPRESSION_JPEG);
        $img->setImageCompressionQuality(82);
        $ext = '.jpg';
        $safeName = $filenameBase . $ext;
        $path = rtrim($destDir, '/').'/'.$safeName;
        if ($img->writeImage($path)) {
          return $safeName;
        } else {
          error_log('upload: imagick write failed');
          return null;
        }
      } catch (Throwable $e) {
        error_log('upload: imagick error '.$e->getMessage());
        return null;
      }
    } else {
      // Imagick tidak ada → tolak (atau arahkan user ubah setting kamera ke JPG)
      error_log('upload: heic not supported (imagick missing)');
      return null;
    }
  } else {
    // Coba fallback via exif_imagetype untuk beberapa browser yang kasih MIME aneh
    $t = @exif_imagetype($file['tmp_name']);
    if ($t === IMAGETYPE_JPEG) $ext = '.jpg';
    elseif ($t === IMAGETYPE_PNG) $ext = '.png';
  }

  if ($ext === '') { error_log('upload: mime not supported '.$mime); return null; }

  // Pindah file
  $safeName = $filenameBase . $ext;
  $path = rtrim($destDir, '/').'/'.$safeName;

  if (!move_uploaded_file($file['tmp_name'], $path)) {
    error_log('upload: move_uploaded_file failed to '.$path);
    return null;
  }

  // (opsional) kompres ringan JPG via GD supaya konsisten ukuran
  if ($ext === '.jpg') {
    try {
      $im = @imagecreatefromjpeg($path);
      if ($im) {
        @imagejpeg($im, $path, 82);
        @imagedestroy($im);
      }
    } catch (Throwable $e) {}
  }

  return $safeName;
}


/* ---------- UTILS ---------- */
function user_role(): string { return $_SESSION['user']['role_name'] ?? ''; }
function user_id(): ?int { return $_SESSION['user']['id'] ?? null; }
