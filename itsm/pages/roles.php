<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requirePermission('roles','view');
$pageTitle  = __('role_title');
$breadcrumb = [['label'=>__('role_title'),'active'=>true]];

$roles = $db->query("SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id=r.id) as user_count FROM roles r ORDER BY r.id")->fetchAll();
$perms = $db->query("SELECT * FROM permissions ORDER BY module, action")->fetchAll();
$byMod = [];
foreach ($perms as $p) $byMod[$p['module']][$p['action']] = $p;
$rolePerms = [];
foreach ($db->query("SELECT role_id, permission_id FROM role_permissions")->fetchAll() as $rp) {
    $rolePerms[(int)$rp['role_id']][(int)$rp['permission_id']] = true;
}

include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title"><div class="title-icon"><i class="bi bi-shield-lock"></i></div> <?= h(__('role_title')) ?></h1>
  <?php if ($auth->can('roles','add')): ?>
  <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalRole" onclick="openAddRole()">
    <i class="bi bi-plus-lg me-1"></i><?= h(__('role_add')) ?>
  </button>
  <?php endif; ?>
</div>

<div class="row g-4">
  <!-- Role list -->
  <div class="col-md-3">
    <div class="d-flex flex-column gap-2" id="roleListEl">
      <?php foreach($roles as $r): ?>
      <div class="card cursor-pointer hover-lift role-card-item" data-role-id="<?= (int)$r['id'] ?>"
           onclick="selectRole(<?= (int)$r['id'] ?>, this)">
        <div class="card-body py-3">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <div class="fw-semibold" style="font-size:13.5px;"><?= h($r['name']) ?></div>
              <div class="text-muted" style="font-size:12px;"><?= (int)$r['user_count'] ?> <?= h(__('role_users')) ?></div>
            </div>
            <div class="d-flex gap-1 align-items-center">
              <?php if ($r['is_system']): ?><span class="badge bg-danger-subtle text-danger" style="font-size:10px;"><?= h(__('role_system_label')) ?></span><?php endif; ?>
              <i class="bi bi-chevron-<?= IS_RTL?'left':'right' ?> text-muted" style="font-size:11px;"></i>
            </div>
          </div>
          <?php if ($r['description']): ?><div class="text-muted mt-1" style="font-size:11px;"><?= h($r['description']) ?></div><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Permissions matrix -->
  <div class="col-md-9">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span id="permTitle"><i class="bi bi-grid-3x3-gap text-primary me-2"></i><?= h(__('role_perm_matrix')) ?></span>
        <div id="permActions" class="d-none d-flex gap-2">
          <?php if ($auth->can('roles','edit')): ?>
          <button class="btn btn-xs btn-outline-secondary" onclick="toggleAll(true)"><?= h(__('role_check_all')) ?></button>
          <button class="btn btn-xs btn-outline-secondary" onclick="toggleAll(false)"><?= h(__('role_uncheck_all')) ?></button>
          <button class="btn btn-sm btn-success" onclick="savePermissions()"><i class="bi bi-save me-1"></i><?= h(__('role_save_perms')) ?></button>
          <button class="btn btn-sm btn-outline-primary" onclick="openEditRole()"><i class="bi bi-pencil me-1"></i><?= h(__('btn_edit')) ?></button>
          <?php endif; ?>
          <?php if ($auth->can('roles','delete')): ?>
          <button class="btn btn-sm btn-outline-danger" id="deleteRoleBtn" onclick="doDeleteRole()" style="display:none"><i class="bi bi-trash me-1"></i><?= h(__('btn_delete')) ?></button>
          <?php endif; ?>
        </div>
      </div>
      <div id="permMatrix">
        <div class="text-center py-5 text-muted">
          <i class="bi bi-arrow-left-circle fs-2 d-block mb-2"></i>
          <?= h(__('role_select_hint')) ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Role Modal -->
<div class="modal fade" id="modalRole" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="roleModalTitle"><i class="bi bi-shield-plus text-primary me-2"></i><?= h(__('role_add')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form data-ajax action="<?= APP_URL ?>/actions/role_save.php" method="post">
        <?= $auth->csrfField() ?>
        <input type="hidden" name="id" id="roleId" value="">
        <div class="modal-body">
          <div class="mb-3"><label class="form-label"><?= h(__('field_name')) ?> <span class="text-danger">*</span></label><input type="text" name="name" id="roleName" class="form-control" required></div>
          <div><label class="form-label"><?= h(__('field_description')) ?></label><textarea name="description" id="roleDesc" class="form-control" rows="2"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= h(__('btn_cancel')) ?></button><button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i><?= h(__('btn_save')) ?></button></div>
      </form>
    </div>
  </div>
</div>

<script>
// All data from PHP
const allRoles    = <?= json_encode(array_column($roles, null, 'id'), JSON_UNESCAPED_UNICODE) ?>;
const allByMod    = <?= json_encode($byMod, JSON_UNESCAPED_UNICODE) ?>;
const initPerms   = <?= json_encode($rolePerms, JSON_UNESCAPED_UNICODE) ?>;
const csrfToken   = <?= json_encode($auth->generateCsrfToken()) ?>;
const APP_URL_JS  = <?= json_encode(APP_URL) ?>;

// Local mutable copy of permissions
let localPerms = JSON.parse(JSON.stringify(initPerms));
let selectedRoleId = null; // INTEGER — always set as int

function selectRole(roleId, el) {
  selectedRoleId = parseInt(roleId, 10); // ALWAYS int

  document.querySelectorAll('.role-card-item').forEach(c => c.classList.remove('border-primary'));
  if (el) el.classList.add('border-primary');

  const role = allRoles[selectedRoleId];
  const sys  = parseInt(role.is_system, 10) === 1;

  document.getElementById('permActions').classList.remove('d-none');
  document.getElementById('permActions').classList.add('d-flex');

  const delBtn = document.getElementById('deleteRoleBtn');
  if (delBtn) delBtn.style.display = (!sys && parseInt(role.user_count,10) === 0) ? '' : 'none';

  renderPerms(selectedRoleId, sys);
}

function renderPerms(roleId, isSystem) {
  const rp      = localPerms[roleId] || {};
  const actions = ['view','add','edit','delete','export'];
  const modules = Object.keys(allByMod);

  let html = '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th style="min-width:140px"><?= h(__("role_module")) ?></th>';
  actions.forEach(a => { html += `<th class="text-center" style="width:80px">${a.charAt(0).toUpperCase()+a.slice(1)}</th>`; });
  html += '</tr></thead><tbody>';

  modules.forEach(mod => {
    html += `<tr><td class="fw-semibold text-capitalize">${mod.replace(/_/g,' ')}</td>`;
    actions.forEach(act => {
      const p = allByMod[mod]?.[act];
      if (!p) { html += '<td></td>'; return; }
      const pid     = parseInt(p.id, 10);
      const checked = rp[pid] ? 'checked' : '';
      const dis     = isSystem ? 'disabled title="System role"' : '';
      html += `<td class="text-center"><input type="checkbox" class="form-check-input perm-cb" data-pid="${pid}" ${checked} ${dis}></td>`;
    });
    html += '</tr>';
  });

  html += '</tbody></table></div>';
  if (!isSystem) {
    html += `<div class="p-3 border-top d-flex align-items-center gap-2">
      <span class="text-muted" style="font-size:12px;" id="permCountLbl"></span>
    </div>`;
  }

  document.getElementById('permMatrix').innerHTML = html;
  updateCount();
  document.querySelectorAll('.perm-cb').forEach(cb => cb.addEventListener('change', updateCount));
}

function updateCount() {
  const tot = document.querySelectorAll('.perm-cb:not(:disabled)').length;
  const chk = document.querySelectorAll('.perm-cb:checked').length;
  const el  = document.getElementById('permCountLbl');
  if (el) el.textContent = `${chk} / ${tot} <?= h(__("field_actions")) ?>`;
}

function toggleAll(state) {
  document.querySelectorAll('.perm-cb:not(:disabled)').forEach(cb => cb.checked = state);
  updateCount();
}

async function savePermissions() {
  if (!selectedRoleId) { Toast.show('<?= h(__("role_select_hint")) ?>','warning'); return; }
  const ids = [...document.querySelectorAll('.perm-cb:checked')].map(cb => cb.dataset.pid);
  const res = await api(APP_URL_JS + '/actions/role_permissions_save.php', {
    role_id: String(selectedRoleId),
    permission_ids: ids,
    csrf_token: csrfToken
  });
  if (res.success) {
    Toast.show(res.message || '<?= h(__("msg_saved")) ?>','success');
    localPerms[selectedRoleId] = {};
    ids.forEach(id => { localPerms[selectedRoleId][parseInt(id,10)] = true; });
  } else {
    Toast.show(res.message || '<?= h(__("msg_error")) ?>','danger');
  }
}

async function doDeleteRole() {
  if (!selectedRoleId) return;
  if (!confirm('<?= h(__("msg_confirm_delete")) ?>')) return;
  const res = await api(APP_URL_JS + '/actions/role_delete.php', { id: String(selectedRoleId), csrf_token: csrfToken });
  if (res.success) { Toast.show('Deleted','success'); setTimeout(()=>location.reload(),700); }
  else Toast.show(res.message||'Error','danger');
}

function openAddRole() {
  document.getElementById('roleId').value = '';
  document.getElementById('roleName').value = '';
  document.getElementById('roleDesc').value = '';
  document.getElementById('roleModalTitle').innerHTML = '<i class="bi bi-shield-plus text-primary me-2"></i><?= h(__("role_add")) ?>';
}
function openEditRole() {
  if (!selectedRoleId) return;
  const r = allRoles[selectedRoleId];
  document.getElementById('roleId').value   = r.id;
  document.getElementById('roleName').value = r.name||'';
  document.getElementById('roleDesc').value = r.description||'';
  document.getElementById('roleModalTitle').innerHTML = '<i class="bi bi-pencil text-primary me-2"></i><?= h(__("role_edit")) ?>';
  new bootstrap.Modal(document.getElementById('modalRole')).show();
}

// Auto-select first role on load
document.addEventListener('DOMContentLoaded', ()=>{
  const first = document.querySelector('.role-card-item');
  if (first) first.click();
});
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
