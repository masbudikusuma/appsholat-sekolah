<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) { http_response_code(400); exit('CSRF invalid'); }

  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password'] ?? '';

  $stmt = $mysqli->prepare("SELECT u.id,u.name,u.pass_hash,r.name AS role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE (u.email=? OR u.phone=?) AND u.is_active=1 LIMIT 1");
  $stmt->bind_param('ss', $email, $email);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) {
    if (password_verify($pass, $row['pass_hash'])) {
      $_SESSION['user'] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'role_name' => $row['role_name'],
      ];
      $next = $_GET['next'] ?? '/';
      header("Location: $next");
      exit;
    }
  }
  $err = "Email/HP atau kata sandi salah.";
}

include __DIR__ . '/layout/header.php';
?>
<div class="row justify-content-center">
  <div class="col-12 col-sm-10 col-md-6">
    <div class="card card-soft">
      <div class="card-body p-4">
        <h1 class="h4 mb-3">Masuk</h1>
        <?php if (!empty($err)): ?>
          <div class="alert alert-danger py-2"><?= sanitize($err) ?></div>
        <?php endif; ?>
        <form method="post" novalidate>
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <div class="mb-3">
            <label class="form-label">Email / No. HP</label>
            <input type="text" class="form-control" name="email" required placeholder="contoh: siswa@sekolah.sch.id">
          </div>
          <div class="mb-3">
            <label class="form-label">Kata Sandi</label>
            <input type="password" class="form-control" name="password" required>
          </div>
          <button class="btn btn-primary w-100 btn-pill">Masuk</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/layout/footer.php'; ?>
