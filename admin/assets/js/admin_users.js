// /assets/js/admin_users.js
(function () {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  document.addEventListener('DOMContentLoaded', function () {
    // === Init DataTables (vanilla v2) ===
    const tblUsers = document.getElementById('tblUsers');
    if (tblUsers) {
      new DataTable(tblUsers, {
        responsive: true,
        pageLength: 25,
        order: [[0, 'desc']],
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/id.json' }
      });
    }

    const tblKelas = document.getElementById('tblKelas');
    if (tblKelas) {
      new DataTable(tblKelas, {
        responsive: true,
        pageLength: 25,
        order: [[0, 'asc']],
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/id.json' }
      });
    }

    // === USERS ===
    const btnAddUser = document.getElementById('btnAddUser');
    btnAddUser?.addEventListener('click', () => openUserModal());

    tblUsers?.addEventListener('click', function (e) {
      const btn = e.target.closest('button');
      if (!btn) return;
      const tr = btn.closest('tr');
      const u = tr?.dataset.user ? JSON.parse(tr.dataset.user) : null;

      if (btn.classList.contains('btn-edit')) openUserModal(u);
      else if (btn.classList.contains('btn-reset')) openResetModal(u);
      else if (btn.classList.contains('btn-toggle')) toggleUser(u);
      else if (btn.classList.contains('btn-delete')) deleteUser(u);
    });

    // === KELAS ===
    const btnAddClass = document.getElementById('btnAddClass');
    btnAddClass?.addEventListener('click', () => openClassModal());

    tblKelas?.addEventListener('click', function (e) {
      const btn = e.target.closest('button');
      if (!btn) return;
      const tr = btn.closest('tr');
      const k = tr?.dataset.kelas ? JSON.parse(tr.dataset.kelas) : null;

      if (btn.classList.contains('btn-edit-class')) openClassModal(k);
      else if (btn.classList.contains('btn-toggle-class')) toggleClass(k);
      else if (btn.classList.contains('btn-delete-class')) deleteClass(k);
    });
  });

  // ==== USERS ====
  function openUserModal(u) {
    const modal = buildUserModal();
    modal.querySelector('#user_action').value = u ? 'update_user' : 'create_user';
    modal.querySelector('#user_id').value = u?.id || '';
    modal.querySelector('#nis').value = u?.nis || '';
    modal.querySelector('#name').value = u?.name || '';
    modal.querySelector('#email').value = u?.email || '';
    modal.querySelector('#phone').value = u?.phone || '';
    modal.querySelector('#class_id').value = u?.class_id || '';
    modal.querySelector('#role_id').value = u?.role_id || '';
    modal.querySelector('#is_active').checked = (u?.is_active == 1);
    modal.querySelector('#infoPassDefault').style.display = u ? 'none' : '';
    new bootstrap.Modal(modal).show();

    modal.querySelector('form').onsubmit = async (ev) => {
      ev.preventDefault();
      const fd = new FormData(ev.target);
      fd.append('csrf', csrf);
      fd.set('is_active', modal.querySelector('#is_active').checked ? '1' : '0');
      const res = await fetch('', { method: 'POST', body: fd });
      const j = await res.json();
      alert(j.msg);
      if (j.ok) location.reload();
    };
  }

  function openResetModal(u) {
    const modal = buildResetModal();
    modal.querySelector('#reset_id').value = u.id;
    modal.querySelector('#reset_name').value = u.name;
    modal.querySelector('#new_password').value = '';
    new bootstrap.Modal(modal).show();

    modal.querySelector('form').onsubmit = async (ev) => {
      ev.preventDefault();
      const fd = new FormData(ev.target);
      fd.append('csrf', csrf);
      fd.append('action', 'reset_password');
      const res = await fetch('', { method: 'POST', body: fd });
      const j = await res.json();
      alert(j.msg);
      if (j.ok) bootstrap.Modal.getInstance(modal).hide();
    };
  }

  async function toggleUser(u) {
    if (!confirm(`Ubah status user ${u.name}?`)) return;
    const fd = new FormData();
    fd.append('csrf', csrf);
    fd.append('action', 'toggle_user');
    fd.append('id', u.id);
    fd.append('val', u.is_active ? '0' : '1');
    const res = await fetch('', { method: 'POST', body: fd });
    const j = await res.json();
    alert(j.msg);
    if (j.ok) location.reload();
  }

  async function deleteUser(u) {
    if (!confirm(`Hapus user ${u.name}?`)) return;
    const fd = new FormData();
    fd.append('csrf', csrf);
    fd.append('action', 'delete_user');
    fd.append('id', u.id);
    const res = await fetch('', { method: 'POST', body: fd });
    const j = await res.json();
    alert(j.msg);
    if (j.ok) location.reload();
  }

  // ==== KELAS ====
  function openClassModal(k) {
    const modal = buildClassModal();
    modal.querySelector('#class_action').value = k ? 'update_class' : 'create_class';
    modal.querySelector('#class_id').value = k?.id || '';
    modal.querySelector('#nama').value = k?.nama || '';
    modal.querySelector('#tingkat').value = k?.tingkat || '';
    modal.querySelector('#aktif').checked = (k?.aktif == 1);
    new bootstrap.Modal(modal).show();

    modal.querySelector('form').onsubmit = async (ev) => {
      ev.preventDefault();
      const fd = new FormData(ev.target);
      fd.append('csrf', csrf);
      fd.set('aktif', modal.querySelector('#aktif').checked ? '1' : '0');
      const res = await fetch('', { method: 'POST', body: fd });
      const j = await res.json();
      alert(j.msg);
      if (j.ok) location.reload();
    };
  }

  async function toggleClass(k) {
    if (!confirm(`Ubah status kelas ${k.nama}?`)) return;
    const fd = new FormData();
    fd.append('csrf', csrf);
    fd.append('action', 'toggle_class');
    fd.append('id', k.id);
    fd.append('val', k.aktif ? '0' : '1');
    const res = await fetch('', { method: 'POST', body: fd });
    const j = await res.json();
    alert(j.msg);
    if (j.ok) location.reload();
  }

  async function deleteClass(k) {
    if (!confirm(`Hapus kelas ${k.nama}?`)) return;
    const fd = new FormData();
    fd.append('csrf', csrf);
    fd.append('action', 'delete_class');
    fd.append('id', k.id);
    const res = await fetch('', { method: 'POST', body: fd });
    const j = await res.json();
    alert(j.msg);
    if (j.ok) location.reload();
  }

  // ==== Build Modal DOM (dinamis, agar tidak duplikat) ====
  function buildUserModal() {
    let m = document.getElementById('modalUser');
    if (m) return m;
    m = document.createElement('div');
    m.className = 'modal fade';
    m.id = 'modalUser';
    m.innerHTML = `
<div class="modal-dialog"><div class="modal-content">
  <form>
    <div class="modal-header"><h5 class="modal-title">User</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" name="action" id="user_action">
      <input type="hidden" name="id" id="user_id">
      <div class="mb-2"><label class="form-label">NIS</label><input type="text" class="form-control" name="nis" id="nis"></div>
      <div class="mb-2"><label class="form-label">Nama</label><input type="text" class="form-control" name="name" id="name" required></div>
      <div class="mb-2"><label class="form-label">Email</label><input type="email" class="form-control" name="email" id="email"></div>
      <div class="mb-2"><label class="form-label">HP</label><input type="text" class="form-control" name="phone" id="phone"></div>
      <div class="mb-2"><label class="form-label">Kelas</label><select class="form-select" name="class_id" id="class_id"><option value="">—</option>${renderOptions('tblKelas')}</select></div>
      <div class="mb-2"><label class="form-label">Role</label><select class="form-select" name="role_id" id="role_id"><option value="3">Siswa</option><option value="2">Guru</option><option value="1">Admin</option></select></div>
      <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"><label class="form-check-label" for="is_active">Aktif</label></div>
      <div class="small text-muted" id="infoPassDefault">Password default: <b>123456</b></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Simpan</button></div>
  </form>
</div></div>`;
    document.body.appendChild(m);
    return m;
  }

  function buildResetModal() {
    let m = document.getElementById('modalReset');
    if (m) return m;
    m = document.createElement('div');
    m.className = 'modal fade';
    m.id = 'modalReset';
    m.innerHTML = `
<div class="modal-dialog"><div class="modal-content">
  <form>
    <div class="modal-header"><h5 class="modal-title">Reset Password</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" name="id" id="reset_id">
      <div class="mb-2"><label class="form-label">Nama</label><input type="text" class="form-control" id="reset_name" disabled></div>
      <div class="mb-2"><label class="form-label">Password Baru</label><input type="password" class="form-control" name="new_password" id="new_password" required></div>
    </div>
    <div class="modal-footer"><button class="btn btn-warning">Ubah Password</button></div>
  </form>
</div></div>`;
    document.body.appendChild(m);
    return m;
  }

  function buildClassModal() {
    let m = document.getElementById('modalClass');
    if (m) return m;
    m = document.createElement('div');
    m.className = 'modal fade';
    m.id = 'modalClass';
    m.innerHTML = `
<div class="modal-dialog"><div class="modal-content">
  <form>
    <div class="modal-header"><h5 class="modal-title">Kelas</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" name="action" id="class_action">
      <input type="hidden" name="id" id="class_id">
      <div class="mb-2"><label class="form-label">Nama</label><input type="text" class="form-control" name="nama" id="nama" required></div>
      <div class="mb-2"><label class="form-label">Tingkat</label><input type="text" class="form-control" name="tingkat" id="tingkat"></div>
      <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="aktif" name="aktif" value="1"><label class="form-check-label" for="aktif">Aktif</label></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Simpan</button></div>
  </form>
</div></div>`;
    document.body.appendChild(m);
    return m;
  }

  // Utility: render options kelas dari tabel
  function renderOptions(tblId) {
    const tbl = document.getElementById(tblId);
    if (!tbl) return '';
    return Array.from(tbl.querySelectorAll('tbody tr')).map(tr => {
      const k = JSON.parse(tr.dataset.kelas || '{}');
      return `<option value="${k.id}">${k.nama}</option>`;
    }).join('');
  }
})();
