<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

const RETENTION_DAYS = 14;
$cutoff = (new DateTime("-".RETENTION_DAYS." days", new DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s');

$dir = realpath(__DIR__ . '/../uploads/attendance');
if (!$dir) { fwrite(STDERR, "uploads dir not found\n"); exit(1); }

/**
 * Strategi:
 * - Hapus file fisik untuk kehadiran yang lebih tua dari batas, apapun statusnya.
 * - Pertahankan record DB (untuk rekap), hanya kosongkan path_foto.
 * - Tandai catatan otomatis.
 */

$stmt = $mysqli->prepare("
  SELECT id, path_foto FROM kehadiran
  WHERE waktu_scan < ? AND path_foto IS NOT NULL AND path_foto <> ''
  LIMIT 2000
");
$stmt->bind_param('s', $cutoff);
$stmt->execute();
$res = $stmt->get_result();

$count = 0;
while ($r = $res->fetch_assoc()) {
  $path = $dir . DIRECTORY_SEPARATOR . $r['path_foto'];
  if (is_file($path)) @unlink($path);
  $upd = $mysqli->prepare("UPDATE kehadiran SET path_foto=NULL, catatan=CONCAT(IFNULL(catatan,''),' [auto-clean ".date('Y-m-d')."]') WHERE id=?");
  $id = (int)$r['id'];
  $upd->bind_param('i', $id);
  $upd->execute();
  $count++;
}
echo "Cleaned $count photos older than ".RETENTION_DAYS." days\n";
