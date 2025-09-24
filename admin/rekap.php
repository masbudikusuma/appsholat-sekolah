<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';
require_role(['admin','guru']);

header('X-Content-Type-Options: nosniff');

function json_ok($data){ header('Content-Type: application/json'); echo json_encode(['ok'=>true]+$data); exit; }
function json_err($msg,$code=400){ http_response_code($code); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>$msg]); exit; }

$tz = new DateTimeZone('Asia/Jakarta');

/* =============== API (AJAX) =============== */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['api']??'')==='1') {
  if (!csrf_check($_POST['csrf'] ?? '')) json_err('CSRF invalid');
  $action = $_POST['action'] ?? '';

  // helper periode
  $period = $_POST['period'] ?? 'week'; // week|month|custom
  $prayer = $_POST['prayer'] ?? 'all'; // all|dzuhur|ashar
  $start  = $_POST['start']  ?? null;  // YYYY-MM-DD
  $end    = $_POST['end']    ?? null;

  $today = new DateTime('today', $tz);
  if ($period==='week') {
    $w = (int)$today->format('N'); // 1..7
    $start_dt = (clone $today)->modify('-'.($w-1).' days'); // Monday
    $end_dt   = (clone $start_dt)->modify('+6 days');
  } elseif ($period==='month') {
    $start_dt = new DateTime($today->format('Y-m-01'), $tz);
    $end_dt   = (clone $start_dt)->modify('last day of this month');
  } else {
    $start_dt = DateTime::createFromFormat('Y-m-d', $start ?? '', $tz) ?: new DateTime('today', $tz);
    $end_dt   = DateTime::createFromFormat('Y-m-d', $end   ?? '', $tz) ?: new DateTime('today', $tz);
  }
  $date_from = $start_dt->format('Y-m-d');
  $date_to   = $end_dt->format('Y-m-d');

  // Build date list (Mon-Fri only; sekolah)
  $dates = [];
  $cursor = clone $start_dt;
  while ($cursor <= $end_dt) {
    $dow = (int)$cursor->format('N'); // 1..7
    if ($dow>=1 && $dow<=5) $dates[] = $cursor->format('Y-m-d');
    $cursor->modify('+1 day');
  }
  $num_days = count($dates);
  $prayers_arr = $prayer==='all' ? ['dzuhur','ashar'] : [$prayer];

  // helper expected count per student
  $expected_per_student = $num_days * count($prayers_arr);

  if ($action==='overview') {
    // KPI + per-class summary + trend
    $classes = $mysqli->query("SELECT id,nama FROM kelas WHERE aktif=1 ORDER BY nama")->fetch_all(MYSQLI_ASSOC);
    // all active students mapped to class
    $stu_q = $mysqli->query("SELECT id,class_id,gender FROM users WHERE is_active=1")->fetch_all(MYSQLI_ASSOC);
    $students = [];
    foreach ($stu_q as $s) $students[(int)$s['id']] = ['class_id'=>(int)$s['class_id'],'gender'=>$s['gender']];

    // attendance within range
    $cond_prayer = $prayer==='all' ? "sholat IN ('dzuhur','ashar')" : "sholat=?";
    $sql = "
      SELECT user_id, tanggal, sholat, status_verifikasi
      FROM kehadiran
      WHERE tanggal BETWEEN ? AND ? AND $cond_prayer
    ";
    $stmt = $mysqli->prepare($sql);
    if ($prayer==='all') $stmt->bind_param('ss', $date_from,$date_to);
    else $stmt->bind_param('sss', $date_from,$date_to,$prayer);
    $stmt->execute();
    $att = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // aggregate
    $present_by_user = []; // user_id => approved_count
    $trend = []; // date => ['dzuhur'=>approved,'ashar'=>approved]
    foreach ($dates as $d) $trend[$d] = ['dzuhur'=>0,'ashar'=>0];

    foreach ($att as $a) {
      if ($a['status_verifikasi']!=='approved') continue;
      $uid = (int)$a['user_id'];
      if (!isset($students[$uid])) continue; // skip inactive
      $present_by_user[$uid] = ($present_by_user[$uid] ?? 0) + 1;
      $trend[$a['tanggal']][$a['sholat']]++;
    }

    // per class
    $per_class = []; // class_id => stats
    foreach ($classes as $c) $per_class[(int)$c['id']] = [
      'class_id'=>(int)$c['id'],'class_name'=>$c['nama'],
      'students'=>0,'present'=>0,'expected'=>0,'rate'=>0
    ];
    foreach ($students as $uid=>$st) {
      $cid = (int)($st['class_id'] ?? 0);
      if (!$cid || !isset($per_class[$cid])) continue;
      $per_class[$cid]['students']++;
      $per_class[$cid]['present'] += ($present_by_user[$uid] ?? 0);
      $per_class[$cid]['expected'] += $expected_per_student;
    }
    foreach ($per_class as &$pc) {
      $pc['absent'] = max(0, $pc['expected'] - $pc['present']);
      $pc['rate']   = $pc['expected']>0 ? round(($pc['present']/$pc['expected'])*100,1) : 0.0;
    }

    // KPI global
    $total_students = 0; $total_expected=0; $total_present=0;
    foreach ($per_class as $pc) {
      $total_students += $pc['students'];
      $total_expected += $pc['expected'];
      $total_present  += $pc['present'];
    }
    $kpi = [
      'total_students'=>$total_students,
      'total_present'=>$total_present,
      'total_absent'=>max(0,$total_expected-$total_present),
      'rate'=>$total_expected>0 ? round(($total_present/$total_expected)*100,1) : 0.0
    ];

    // respond
    json_ok([
      'kpi'=>$kpi,
      'per_class'=>array_values($per_class),
      'trend'=>['labels'=>$dates, 'dzuhur'=>array_map(fn($d)=>$trend[$d]['dzuhur'],$dates), 'ashar'=>array_map(fn($d)=>$trend[$d]['ashar'],$dates)],
      'meta'=>['period'=>$period,'from'=>$date_from,'to'=>$date_to,'prayer'=>$prayer]
    ]);
  }

  if ($action==='class_detail') {
    $class_id = (int)($_POST['class_id'] ?? 0);
    if (!$class_id) json_err('class_id required');

    // fetch class students (active)
    $stmt = $mysqli->prepare("SELECT id,nis,name,gender,IFNULL(catatan,'') catatan FROM users WHERE is_active=1 AND class_id=?");
    $stmt->bind_param('i', $class_id);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $student_ids = array_map(fn($x)=>(int)$x['id'], $students);

    if (empty($student_ids)) {
      json_ok([
        'summary'=>['class_id'=>$class_id,'students'=>0,'present'=>0,'expected'=>0,'rate'=>0],
        'daily'=>['labels'=>$dates,'present'=>array_fill(0,count($dates),0)],
        'table'=>[],
        'meta'=>['from'=>$date_from,'to'=>$date_to,'prayer'=>$prayer]
      ]);
    }

    // attendance in range for class
    $in = implode(',', array_fill(0,count($student_ids),'?'));
    $types = str_repeat('i', count($student_ids));
    $cond_prayer = $prayer==='all' ? "sholat IN ('dzuhur','ashar')" : "sholat=?";
    $sql = "
      SELECT user_id, tanggal, sholat, status_verifikasi
      FROM kehadiran
      WHERE tanggal BETWEEN ? AND ? AND $cond_prayer AND user_id IN ($in)
    ";
    $stmt = $mysqli->prepare($sql);
    if ($prayer==='all') $stmt->bind_param('ss'.$types, $date_from,$date_to, ...$student_ids);
    else $stmt->bind_param('sss'.$types, $date_from,$date_to,$prayer, ...$student_ids);
    $stmt->execute();
    $att = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $present_by_user = array_fill_keys($student_ids, 0);
    $present_by_date = array_fill_keys($dates, 0);
    foreach ($att as $a) {
      if ($a['status_verifikasi']!=='approved') continue;
      $uid = (int)$a['user_id'];
      if (!isset($present_by_user[$uid])) continue;
      $present_by_user[$uid]++;
      if (isset($present_by_date[$a['tanggal']])) $present_by_date[$a['tanggal']]++;
    }

    // table rows per student
    $rows = [];
    $present_total = 0;
    foreach ($students as $s) {
      $uid = (int)$s['id'];
      $hadir = $present_by_user[$uid] ?? 0;
      $present_total += $hadir;
      $row = [
        'nis'=>$s['nis'],
        'name'=>$s['name'],
        'gender'=>$s['gender'],
        'catatan'=>$s['catatan'],
        'hadir'=>$hadir,
        'bolos'=>max(0, $expected_per_student - $hadir),
        'rate'=>$expected_per_student>0 ? round(($hadir/$expected_per_student)*100,1) : 0.0
      ];
      $rows[] = $row;
    }
    // sort by rate asc (yang sering bolong di atas)
    usort($rows, fn($a,$b)=> $a['rate']<=>$b['rate']);

    json_ok([
      'summary'=>[
        'class_id'=>$class_id,
        'students'=>count($students),
        'present'=>$present_total,
        'expected'=>$expected_per_student * count($students),
        'rate'=> ($expected_per_student*count($students))>0 ? round(($present_total/($expected_per_student*count($students)))*100,1) : 0.0
      ],
      'daily'=>[
        'labels'=>$dates,
        'present'=>array_map(fn($d)=>$present_by_date[$d], $dates)
      ],
      'table'=>$rows,
      'meta'=>['from'=>$date_from,'to'=>$date_to,'prayer'=>$prayer]
    ]);
  }

  if ($action==='frequent_absentees') {
    $threshold = max(0.0, min(1.0, (float)($_POST['threshold'] ?? 0.75))); // 0..1
    $exclude_female = (int)($_POST['exclude_female'] ?? 0)===1;

    // all active students with gender & class
    $stu = $mysqli->query("SELECT u.id,u.nis,u.name,u.gender,IFNULL(u.catatan,'') catatan, k.nama AS kelas
                           FROM users u LEFT JOIN kelas k ON k.id=u.class_id
                           WHERE u.is_active=1")->fetch_all(MYSQLI_ASSOC);
    $ids = array_map(fn($x)=>(int)$x['id'], $stu);
    if (empty($ids)) json_ok(['list'=>[]]);

    $in = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $cond_prayer = $prayer==='all' ? "sholat IN ('dzuhur','ashar')" : "sholat=?";
    $sql = "SELECT user_id, status_verifikasi FROM kehadiran
            WHERE tanggal BETWEEN ? AND ? AND $cond_prayer AND user_id IN ($in)";
    $stmt = $mysqli->prepare($sql);
    if ($prayer==='all') $stmt->bind_param('ss'.$types, $date_from,$date_to, ...$ids);
    else $stmt->bind_param('sss'.$types, $date_from,$date_to,$prayer, ...$ids);
    $stmt->execute();
    $att = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $present_by_user = array_fill_keys($ids, 0);
    foreach ($att as $a) if ($a['status_verifikasi']==='approved') $present_by_user[(int)$a['user_id']]++;

    $list = [];
    foreach ($stu as $s) {
      if ($exclude_female && ($s['gender'] ?? null)==='P') continue;
      $uid = (int)$s['id'];
      $hadir = $present_by_user[$uid] ?? 0;
      $rate  = $expected_per_student>0 ? ($hadir/$expected_per_student) : 1.0;
      if ($rate < $threshold) {
        $list[] = [
          'nis'=>$s['nis'],
          'name'=>$s['name'],
          'kelas'=>$s['kelas'],
          'gender'=>$s['gender'],
          'catatan'=>$s['catatan'],
          'hadir'=>$hadir,
          'bolos'=>max(0, $expected_per_student - $hadir),
          'rate'=> round($rate*100,1)
        ];
      }
    }
    // paling bolong di atas
    usort($list, fn($a,$b)=> $a['rate']<=>$b['rate']);
    json_ok(['list'=>$list, 'meta'=>['from'=>$date_from,'to'=>$date_to,'prayer'=>$prayer,'threshold'=>$threshold]]);
  }

  json_err('unknown action');
}

/* =============== PAGE (HTML) =============== */

include __DIR__.'/../layout/header.php';
?>
<meta name="csrf-token" content="<?= csrf_token() ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css">
<style>
  .card-soft{border:1px solid #eef1f4;border-radius:1rem;box-shadow:0 8px 24px rgba(0,0,0,.05)}
  .btn-pill{border-radius:999px}
  .kpi{display:flex;flex-direction:column;gap:4px;padding:14px;border:1px solid #eef1f4;border-radius:1rem}
  .kpi .v{font-size:1.6rem;font-weight:700}
  .table-sticky th{position:sticky;top:0;background:#fff;z-index:1}
  .chart-wrap{min-height:280px}
</style>

<div class="row g-3">
  <div class="col-12">
    <div class="card card-soft">
      <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-md-between gap-2">
          <div>
            <h1 class="h4 mb-1">🧾 Rekapitulasi Absensi</h1>
            <div class="text-muted small">Ringkasan mingguan/bulanan semua kelas, dengan drill-down kelas & laporan sering bolong.</div>
          </div>
          <!-- Filter -->
          <form class="row g-2 align-items-end" id="formFilter">
            <div class="col-auto">
              <label class="form-label small">Periode</label>
              <select class="form-select form-select-sm" name="period" id="period">
                <option value="week">Minggu ini</option>
                <option value="month" selected>Bulan ini</option>
                <option value="custom">Custom</option>
              </select>
            </div>
            <div class="col-auto">
              <label class="form-label small">Mulai</label>
              <input type="date" class="form-control form-control-sm" name="start" id="start">
            </div>
            <div class="col-auto">
              <label class="form-label small">Selesai</label>
              <input type="date" class="form-control form-control-sm" name="end" id="end">
            </div>
            <div class="col-auto">
              <label class="form-label small">Sholat</label>
              <select class="form-select form-select-sm" name="prayer" id="prayer">
                <option value="all" selected>Semua</option>
                <option value="dzuhur">Dzuhur</option>
                <option value="ashar">Ashar</option>
              </select>
            </div>
            <div class="col-auto">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <button class="btn btn-primary btn-sm btn-pill" id="btnApply" type="submit">Terapkan</button>
            </div>
          </form>
        </div>

        <hr>

        <!-- KPI -->
        <div class="row g-3" id="kpiRow">
          <div class="col-6 col-md-3"><div class="kpi"><div class="t small text-muted">Siswa Aktif</div><div class="v" id="kpi_students">-</div></div></div>
          <div class="col-6 col-md-3"><div class="kpi"><div class="t small text-muted">Total Hadir</div><div class="v" id="kpi_present">-</div></div></div>
          <div class="col-6 col-md-3"><div class="kpi"><div class="t small text-muted">Total Tidak Hadir</div><div class="v" id="kpi_absent">-</div></div></div>
          <div class="col-6 col-md-3"><div class="kpi"><div class="t small text-muted">% Kehadiran</div><div class="v" id="kpi_rate">-</div></div></div>
        </div>

        <!-- Chart Tren -->
          <div class="card card-soft mt-3">
            <div class="card card-soft h-100">
              <div class="card-body">
                <h2 class="h6 mb-2">Tren Kehadiran (Approved) :: per Hari</h2>
                <div class="chart-wrap">
                  <canvas id="chartTrend" height="120"></canvas>
                </div>
                <div class="small text-muted mt-2" id="lblPeriod">Periode: -</div>
              </div>
            </div>
          </div>
          <div class="card card-soft mt-3">
            <div class="card card-soft h-100">
              <div class="card-body">
                <h2 class="h6 mb-2">Siswa Sering Bolong</h2>
                <form class="row g-2 align-items-center" id="formBolos">
                  <div class="col-6">
                    <label class="form-label small">% ambang</label>
                    <input type="number" min="0" max="100" step="1" value="75" class="form-control form-control-sm" id="threshold">
                  </div>
                  <div class="col-6 form-check mt-4">
                    <input class="form-check-input" type="checkbox" id="exclude_female">
                    <label class="form-check-label small" for="exclude_female">Abaikan siswa perempuan</label>
                  </div>
                </form>
                <div class="table-responsive mt-2">
                  <table class="table table-sm table-hover" id="tblBolos">
                    <thead><tr><th>Kelas</th><th>NIS</th><th>Nama</th><th>Gender</th><th>Hadir</th><th>Bolos</th><th>%</th><th>Catatan</th></tr></thead>
                    <tbody></tbody>
                  </table>
                </div>
                <div class="text-end"><button class="btn btn-outline-secondary btn-sm btn-pill" id="btnRefreshBolos">Refresh</button></div>
              </div>
            </div>
          </div>
        

        <!-- Rekap per Kelas -->
        <div class="card card-soft mt-3">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h2 class="h6 mb-0">Rekap Semua Kelas</h2>
              <div class="small text-muted">Klik kelas untuk lihat detail</div>
            </div>
            <div class="table-responsive">
              <table class="table table-hover align-middle" id="tblKelasRekap">
                <thead>
                  <tr>
                    <th>Kelas</th><th>Siswa</th><th>Hadir</th><th>Tidak Hadir</th><th>%</th><th>Detail</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Detail Kelas -->
        <div class="card card-soft mt-3 d-none" id="panelKelas">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
              <h2 class="h6 mb-0">Detail Kelas: <span id="lblKelas">-</span></h2>
              <button class="btn btn-sm btn-outline-secondary btn-pill" id="btnCloseDetail">Kembali</button>
            </div>
            <div class="row g-3 mt-1">
              <div class="col-12 col-lg-6">
                <div class="card card-soft h-100"><div class="card-body">
                  <h3 class="h6 mb-2">Tren Kehadiran Kelas (Approved)</h3>
                  <div class="chart-wrap"><canvas id="chartClass" height="110"></canvas></div>
                </div></div>
              </div>
              <div class="col-12 col-lg-6">
                <div class="kpi h-100">
                  <div class="t small text-muted">Siswa</div><div class="v" id="c_students">-</div>
                  <div class="t small text-muted">Total Hadir</div><div class="v" id="c_present">-</div>
                  <div class="t small text-muted">Total Tidak Hadir</div><div class="v" id="c_absent">-</div>
                  <div class="t small text-muted">% Kehadiran</div><div class="v" id="c_rate">-</div>
                </div>
              </div>
              <div class="col-12">
                <div class="table-responsive">
                  <table class="table table-striped" id="tblKelasDetail">
                    <thead><tr><th>NIS</th><th>Nama</th><th>Gndr</th><th>Hadir</th><th>Bolos</th><th>%</th><th>Catatan</th></tr></thead>
                    <tbody></tbody>
                  </table>
                </div>
              </div>
            </div>

          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js" defer></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js" defer></script>
<script src="https://appsholat.ayosinau.id/admin/assets/js/rekap.js?v=<?= time() ?>" defer></script>


<?php include __DIR__ . '/../layout/footer.php'; ?>
