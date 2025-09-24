<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';
require_role(['admin']);

// ===== Helpers response JSON =====
function json_ok($msg,$extra=[]) { header('Content-Type: application/json'); echo json_encode(['ok'=>true,'msg'=>$msg]+$extra); exit; }
function json_err($msg,$code=400) { http_response_code($code); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>$msg]); exit; }

// ===== ACTION HANDLER (AJAX) =====
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) json_err('CSRF tidak valid');
  $act = $_POST['action'] ?? '';

  /* ---------- USERS ---------- */
  if ($act==='create_user' || $act==='update_user') {
    $id=(int)($_POST['id']??0);
    $nis=trim($_POST['nis']??'');
    $name=trim($_POST['name']??'');
    $class_id=$_POST['class_id']!==''?(int)$_POST['class_id']:null;
    $email=trim($_POST['email']??'');
    $phone=trim($_POST['phone']??'');
    $role_id=(int)($_POST['role_id']??0);
    $active=(int)($_POST['is_active']??0);
    if($name===''||!$role_id) json_err('Nama & role wajib');
    if($act==='create_user'){
      $pass_hash=password_hash('123456',PASSWORD_BCRYPT);
      $stmt=$mysqli->prepare("INSERT INTO users(nis,name,class_id,email,phone,pass_hash,role_id,is_active)VALUES(?,?,?,?,?,?,?,?)");
      $stmt->bind_param('ssisssii',$nis,$name,$class_id,$email,$phone,$pass_hash,$role_id,$active);
      $stmt->execute()||json_err($mysqli->error);
      json_ok('User ditambahkan');
    }else{
      $stmt=$mysqli->prepare("UPDATE users SET nis=?,name=?,class_id=?,email=?,phone=?,role_id=?,is_active=? WHERE id=?");
      $stmt->bind_param('ssissiii',$nis,$name,$class_id,$email,$phone,$role_id,$active,$id);
      $stmt->execute()||json_err($mysqli->error);
      json_ok('User diperbarui');
    }
  }
  if($act==='delete_user'){
    $id=(int)($_POST['id']??0); if(!$id) json_err('ID invalid');
    if($id===user_id()) json_err('Tidak bisa hapus diri sendiri');
    $mysqli->query("DELETE FROM users WHERE id=$id")||json_err($mysqli->error);
    json_ok('User dihapus');
  }
  if($act==='reset_password'){
    $id=(int)($_POST['id']??0); $new=trim($_POST['new_password']??'');
    if(strlen($new)<6) json_err('Min 6 karakter');
    $hash=password_hash($new,PASSWORD_BCRYPT);
    $stmt=$mysqli->prepare("UPDATE users SET pass_hash=? WHERE id=?");
    $stmt->bind_param('si',$hash,$id); $stmt->execute()||json_err($mysqli->error);
    json_ok('Password diperbarui');
  }
  if($act==='toggle_user'){
    $id=(int)$_POST['id']; $val=(int)$_POST['val'];
    $stmt=$mysqli->prepare("UPDATE users SET is_active=? WHERE id=?");
    $stmt->bind_param('ii',$val,$id); $stmt->execute()||json_err($mysqli->error);
    json_ok('Status user diperbarui');
  }

  /* ---------- KELAS ---------- */
  if($act==='create_class'||$act==='update_class'){
    $id=(int)($_POST['id']??0);
    $nama=trim($_POST['nama']??'');
    $tingkat=trim($_POST['tingkat']??'');
    $aktif=(int)($_POST['aktif']??1);
    if($nama==='') json_err('Nama kelas wajib');
    if($act==='create_class'){
      $stmt=$mysqli->prepare("INSERT INTO kelas(nama,tingkat,aktif)VALUES(?,?,?)");
      $stmt->bind_param('ssi',$nama,$tingkat,$aktif);
      $stmt->execute()||json_err($mysqli->error);
      json_ok('Kelas ditambahkan');
    }else{
      $stmt=$mysqli->prepare("UPDATE kelas SET nama=?,tingkat=?,aktif=? WHERE id=?");
      $stmt->bind_param('ssii',$nama,$tingkat,$aktif,$id);
      $stmt->execute()||json_err($mysqli->error);
      json_ok('Kelas diperbarui');
    }
  }
  if($act==='delete_class'){
    $id=(int)$_POST['id']; if(!$id) json_err('ID invalid');
    $mysqli->query("DELETE FROM kelas WHERE id=$id")||json_err($mysqli->error);
    json_ok('Kelas dihapus');
  }
  if($act==='toggle_class'){
    $id=(int)$_POST['id']; $val=(int)$_POST['val'];
    $stmt=$mysqli->prepare("UPDATE kelas SET aktif=? WHERE id=?");
    $stmt->bind_param('ii',$val,$id); $stmt->execute()||json_err($mysqli->error);
    json_ok('Status kelas diperbarui');
  }

  json_err('Aksi tidak dikenali');
}

// ===== DATA UTAMA UNTUK TABEL =====
$roles=$mysqli->query("SELECT id,name FROM roles ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$kelas=$mysqli->query("SELECT * FROM kelas ORDER BY nama")->fetch_all(MYSQLI_ASSOC);
$users=$mysqli->query("SELECT u.*,r.name role_name,k.nama kelas_nama FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN kelas k ON k.id=u.class_id where u.role_id=3 ORDER BY u.id DESC")->fetch_all(MYSQLI_ASSOC);

include __DIR__.'/../layout/header.php';
?>
<meta name="csrf-token" content="<?= csrf_token() ?>">
<link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js" defer></script>
<!-- <script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js" defer></script> -->

<script src="https://appsholat.ayosinau.id/admin/assets/js/admin_users.js" defer></script>

<div class="row g-3">
  <div class="col-12">
    <div class="card card-soft">
      <div class="card-body p-3 p-md-4">
        <ul class="nav nav-tabs" id="userTab" role="tablist">
          <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabUsers" type="button">👥 Pengguna</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabKelas" type="button">🏫 Kelas</button></li>
        </ul>
        <div class="tab-content pt-3">
          <!-- USERS -->
          <div class="tab-pane fade show active" id="tabUsers">
            <div class="d-flex justify-content-between mb-2">
              <h5>Daftar Pengguna</h5>
              <button class="btn btn-primary btn-sm" id="btnAddUser">➕ Tambah</button>
            </div>
            <div class="table-responsive">
              <table class="table" id="tblUsers">
                <thead><tr><th>ID</th><th>NIS</th><th>Nama</th><th>Kelas</th><th>Email</th><th>HP</th><th>Role</th><th>Aktif</th><th>Aksi</th></tr></thead>
                <tbody>
                  <?php foreach($users as $u): ?>
                  <tr data-user='<?= json_encode($u,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>'>
                    <td><?= $u['id'] ?></td>
                    <td><?= sanitize($u['nis']) ?></td>
                    <td><?= sanitize($u['name']) ?></td>
                    <td><?= sanitize($u['kelas_nama']??'-') ?></td>
                    <td><?= sanitize($u['email']) ?></td>
                    <td><?= sanitize($u['phone']) ?></td>
                    <td><?= sanitize($u['role_name']) ?></td>
                    <td><?= $u['is_active']?'✅':'❌' ?></td>
                    <td><div class="btn-group btn-group-sm">
                      <button class="btn btn-outline-primary btn-edit">Edit</button>
                      <button class="btn btn-outline-warning btn-reset">Password</button>
                      <button class="btn btn-outline-secondary btn-toggle"><?= $u['is_active']?'Nonaktif':'Aktifkan' ?></button>
                      <button class="btn btn-outline-danger btn-delete">Hapus</button>
                    </div></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- KELAS -->
          <div class="tab-pane fade" id="tabKelas">
            <div class="d-flex justify-content-between mb-2">
              <h5>Daftar Kelas</h5>
              <button class="btn btn-primary btn-sm" id="btnAddClass">➕ Tambah</button>
            </div>
            <div class="table-responsive">
              <table class="table" id="tblKelas">
                <thead><tr><th>ID</th><th>Nama</th><th>Tingkat</th><th>Aktif</th><th>Aksi</th></tr></thead>
                <tbody>
                  <?php foreach($kelas as $k): ?>
                  <tr data-kelas='<?= json_encode($k,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>'>
                    <td><?= $k['id'] ?></td>
                    <td><?= sanitize($k['nama']) ?></td>
                    <td><?= sanitize($k['tingkat']) ?></td>
                    <td><?= $k['aktif']?'✅':'❌' ?></td>
                    <td><div class="btn-group btn-group-sm">
                      <button class="btn btn-outline-primary btn-edit-class">Edit</button>
                      <button class="btn btn-outline-secondary btn-toggle-class"><?= $k['aktif']?'Nonaktif':'Aktifkan' ?></button>
                      <button class="btn btn-outline-danger btn-delete-class">Hapus</button>
                    </div></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__.'/../layout/footer.php'; ?>
