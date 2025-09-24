<?php
$mysqli = new mysqli("localhost", "appsholat", "appsholatappsholat", "appsholat");

// TZ (biar konsisten)
date_default_timezone_set('Asia/Jakarta');

// SECRET untuk HMAC token QR
// Ganti dengan string acak yang panjang (minimal 32–64 chars)
if (!defined('SECRET_KEY')) {
  define('SECRET_KEY', 'ganti_dengan_string_acak_panjang_yg_sulit_ditebak_!@#%_2025');
}

// (opsional) Lokasi direktori upload (boleh pakai konstanta agar seragam)
if (!defined('UPLOAD_DIR')) {
  define('UPLOAD_DIR', __DIR__ . '/uploads/attendance');
}
