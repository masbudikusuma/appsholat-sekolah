<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';
require_role(['admin','guru']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
if (!csrf_check($_POST['csrf'] ?? '')) { http_response_code(400); exit('CSRF invalid'); }

$ids = $_POST['ids'] ?? [];
$action = $_POST['action'] ?? '';
if (!in_array($action, ['approve','reject'], true) || empty($ids)) {
  header('Location: /admin/attendance.php'); exit;
}
$status = $action === 'approve' ? 'approved' : 'rejected';
$uid = user_id();
$now = date('Y-m-d H:i:s');

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));
$sql = "UPDATE kehadiran SET status_verifikasi=?, diverifikasi_oleh=?, waktu_verifikasi=? WHERE id IN ($placeholders)";

$stmt = $mysqli->prepare($sql);
$params = array_merge([$status, $uid, $now], array_map('intval', $ids));
$bindTypes = 'sis' . $types;
$stmt->bind_param($bindTypes, ...$params);
$stmt->execute();

$tanggal = $_POST['tanggal'] ?? today_id();
$sholat  = $_POST['sholat'] ?? 'dzuhur';
header('Location: /admin/attendance.php?tanggal=' . urlencode($tanggal) . '&sholat=' . urlencode($sholat));
