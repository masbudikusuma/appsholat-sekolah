<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';
require_role(['admin','guru']);

$from   = $_GET['from']   ?? today_id();
$to     = $_GET['to']     ?? today_id();
$prayer = $_GET['prayer'] ?? 'all';   // dzuhur|ashar|all
$status = $_GET['status'] ?? 'all';   // pending|approved|rejected|all
$class  = $_GET['class']  ?? 'all';   // class_id|all

$conds = ["k.tanggal BETWEEN ? AND ?"];
$params = [$from, $to]; $types = 'ss';

if (in_array($prayer, ['dzuhur','ashar'], true)) { $conds[]="k.sholat=?"; $params[]=$prayer; $types.='s'; }
if (in_array($status, ['pending','approved','rejected'], true)) { $conds[]="k.status_verifikasi=?"; $params[]=$status; $types.='s'; }
if ($class !== 'all' && ctype_digit($class)) { $conds[]="u.class_id=?"; $params[]=(int)$class; $types.='i'; }

$sql = "
SELECT k.id, k.tanggal, k.sholat, k.waktu_scan, k.status_verifikasi,
       u.id AS user_id, u.name AS nama, u.nis, u.class_id,
       k.path_foto, k.id_perangkat, k.ip, k.lat, k.lng, k.akurasi_lokasi
FROM kehadiran k
JOIN users u ON u.id = k.user_id
WHERE ".implode(' AND ', $conds)."
ORDER BY k.tanggal, k.sholat, k.waktu_scan
";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=rekap_' . $from . '_' . $to . '.csv');

$out = fopen('php://output', 'w');
fputcsv($out, ['id','tanggal','sholat','waktu_scan','status','user_id','nama','nis','class_id','foto','device_id','ip','lat','lng','akurasi']);
while ($r = $res->fetch_assoc()) {
  fputcsv($out, [
    $r['id'],$r['tanggal'],$r['sholat'],$r['waktu_scan'],$r['status_verifikasi'],
    $r['user_id'],$r['nama'],$r['nis'],$r['class_id'],
    $r['path_foto'],$r['id_perangkat'],$r['ip'],$r['lat'],$r['lng'],$r['akurasi_lokasi']
  ]);
}
fclose($out);
