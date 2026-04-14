<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requirePermission('users','view');
$pageTitle  = __('emp_title');
$breadcrumb = [['label'=>__('emp_title'),'active'=>true]];

$search  = trim($_GET['q']     ?? '');
$roleF   = (int)($_GET['role'] ?? 0);
$deptF   = (int)($_GET['dept'] ?? 0);
$statusF = $_GET['status'] ?? '';
$page    = max(1,(int)($_GET['page']??1));

$where = []; $params = [];
if ($search) { $where[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.username LIKE ? OR u.employee_id LIKE ?)"; $s="%$search%"; $params=array_merge($params,[$s,$s,$s,$s]); }
if ($roleF)  { $where[] = "u.role_id=?"; $params[]=$roleF; }
if ($deptF)  { $where[] = "u.department_id=?"; $params[]=$deptF; }
if ($statusF){ $where[] = "u.status=?"; $params[]=$statusF; }
$w = $where ? 'WHERE '.implode(' AND ',$where) : '';

$sql = "SELECT u.*, r.name as role_name, d.name as dept_name
        FROM users u
        LEFT JOIN roles r ON u.role_id=r.id
        LEFT JOIN departments d ON u.department_id=d.id
        $w ORDER BY u.full_name";
$pag = paginate($db,$sql,$params,$page);
$filter = '?'.http_build_query(array_filter(['q'=>$search,'role'=>$roleF,'dept'=>$deptF,'status'=>$statusF]));

$roles = $db->query("SELECT id,name FROM roles ORDER BY name")->fetchAll();
$depts = $db->query("SELECT id,name FROM departments ORDER BY name")->fetchAll();

include APP_ROOT . '/includes/header.php';
?>
<div class="page-header">
  <h1 class="page-title"><div class="title-icon"><i class="bi bi-people"></i></div> <?= h(__('emp_title')) ?></h1>
  <div class="page-actions">
    <?php if ($auth->can('users','export')): ?>
    <button class="btn btn-sm btn-outline-secondary" onclick="exportTable('usersTable','employees')"><i class="bi bi-download me-1"></i><?= h(__('btn_export')) ?></button>
    <?php endif; ?>
    <?php if ($auth->can('users','add')): ?>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalUser" onclick="resetUserModal()">
      <i class="bi bi-plus-lg me-1"></i><?= h(__('emp_add')) ?>
    </button>
    <?php endif; ?>
  </div>
</div>

<!-- Filters -->
<div class="card mb-3"><div class="card-body py-2">
  <form method="get" class="row g-2 align-items-end">
    <div class="col-md-3"><input type="text" name="q" class="form-control form-control-sm" placeholder="<?= h(__('emp_search_ph')) ?>" value="<?= h($search) ?>"></div>
    <div class="col-md-2">
      <select name="role" class="form-select form-select-sm">
        <option value=""><?= h(__('emp_all_roles')) ?></option>
        <?php foreach($roles as $r): ?><option value="<?=$r['id']?>" <?=$roleF==$r['id']?'selected':''?>><?=h($r['name'])?></option><?php endforeach;?>
      </select>
    </div>
    <div class="col-md-2">
      <select name="dept" class="form-select form-select-sm">
        <option value=""><?= h(__('emp_all_depts')) ?></option>
        <?php foreach($depts as $d): ?><option value="<?=$d['id']?>" <?=$deptF==$d['id']?'selected':''?>><?=h($d['name'])?></option><?php endforeach;?>
      </select>
    </div>
    <div class="col-md-2">
      <select name="status" class="form-select form-select-sm">
        <option value=""><?= h(__('emp_all_status')) ?></option>
        <option value="active" <?=$statusF==='active'?'selected':''?>><?= h(__('status_active')) ?></option>
        <option value="inactive" <?=$statusF==='inactive'?'selected':''?>><?= h(__('status_inactive')) ?></option>
        <option value="suspended" <?=$statusF==='suspended'?'selected':''?>><?= h(__('status_suspended')) ?></option>
      </select>
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i><?= h(__('btn_filter')) ?></button>
      <a href="<?= APP_URL ?>/pages/users.php" class="btn btn-sm btn-outline-secondary ms-1"><?= h(__('btn_reset')) ?></a>
    </div>
  </form>
</div></div>

<!-- Bulk bar -->
<div class="bulk-bar" id="bulkBar">
  <span><?= CURRENT_LANG==='ar'?'تم اختيار':'Selected' ?> <strong id="bulkCount">0</strong></span>
  <button class="btn btn-xs btn-success ms-2" onclick="bulkAction('activate_users')"><?= h(__('status_active')) ?></button>
  <button class="btn btn-xs btn-warning" onclick="bulkAction('deactivate_users')"><?= h(__('status_inactive')) ?></button>
  <?php if ($auth->can('users','delete')): ?>
  <button class="btn btn-xs btn-danger" onclick="if(confirm('Delete selected?'))bulkAction('delete_users')"><?= h(__('btn_delete')) ?></button>
  <?php endif; ?>
</div>

<div class="table-card">
  <div class="table-toolbar">
    <div class="table-toolbar-left"><span class="text-muted small"><?= number_format($pag['total']) ?> <?= h(__('emp_title')) ?></span></div>
  </div>
  <div class="table-responsive">
    <table class="table" id="usersTable">
      <thead><tr>
        <th style="width:36px;"><input type="checkbox" class="form-check-input" id="selectAll"></th>
        <th><?= h(__('field_name')) ?></th>
        <th><?= h(__('field_email')) ?></th>
        <th><?= h(__('field_role')) ?></th>
        <th><?= h(__('field_department')) ?></th>
        <th><?= h(__('field_status')) ?></th>
        <th><?= h(__('field_last_login')) ?></th>
        <th class="text-end"><?= h(__('field_actions')) ?></th>
      </tr></thead>
      <tbody>
        <?php if(empty($pag['records'])): ?>
        <tr><td colspan="8" class="text-center py-4 text-muted"><?= h(__('msg_no_records')) ?></td></tr>
        <?php endif; ?>
        <?php foreach($pag['records'] as $u): ?>
        <tr>
          <td><input type="checkbox" class="form-check-input row-cb" value="<?= $u['id'] ?>"></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--purple));display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:700;overflow:hidden;flex-shrink:0;">
                <?php if($u['avatar']&&file_exists(UPLOAD_DIR.'avatars/'.$u['avatar'])): ?><img src="<?=APP_URL?>/uploads/avatars/<?=h($u['avatar'])?>" style="width:100%;height:100%;object-fit:cover;"><?php else: ?><?=h(mb_strtoupper(mb_substr($u['full_name'],0,2)))?><?php endif;?>
              </div>
              <div>
                <div class="fw-semibold" style="font-size:13px;"><?= h($u['full_name']) ?></div>
                <?php if($u['employee_id']): ?><div class="mono text-muted" style="font-size:11px;"><?= h($u['employee_id']) ?></div><?php endif; ?>
              </div>
            </div>
          </td>
          <td style="font-size:13px;"><?= h($u['email']) ?></td>
          <td><span class="badge bg-primary-subtle text-primary"><?= h($u['role_name']) ?></span></td>
          <td style="font-size:13px;"><?= h($u['dept_name'] ?? '—') ?></td>
          <td><?= userStatusBadge($u['status']) ?></td>
          <td style="font-size:12px;"><?= $u['last_login'] ? formatDate($u['last_login']) : '<span class="text-muted">'.h(__('emp_never')).'</span>' ?></td>
          <td class="text-end">
            <?php if($auth->can('users','edit')): ?>
            <button class="btn btn-icon btn-sm btn-outline-secondary" onclick="editUser(<?= $u['id'] ?>)" title="<?= h(__('btn_edit')) ?>"><i class="bi bi-pencil"></i></button>
            <?php endif; ?>
            <?php if($auth->can('users','delete') && $u['id'] !== $auth->getUserId()): ?>
            <button class="btn btn-icon btn-sm btn-outline-danger ms-1" onclick="confirmDelete('<?= APP_URL ?>/actions/user_delete.php?id=<?=$u['id']?>&csrf_token=<?=h($auth->generateCsrfToken())?>')" title="<?= h(__('btn_delete')) ?>"><i class="bi bi-trash"></i></button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="table-footer">
    <span><?= $pag['total'] ?> <?= h(__('emp_title')) ?></span>
    <?= renderPagination($pag, APP_URL.'/pages/users.php'.$filter) ?>
  </div>
</div>

<!-- Add/Edit Modal -->
<?php if ($auth->can('users','add')): ?>
<div class="modal fade" id="modalUser" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="userModalTitle"><i class="bi bi-person-plus text-primary me-2"></i><?= h(__('emp_add')) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form data-ajax action="<?= APP_URL ?>/actions/user_save.php" method="post" enctype="multipart/form-data">
        <?= $auth->csrfField() ?>
        <input type="hidden" name="id" id="userId" value="">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label"><?= h(__('field_name')) ?> <span class="text-danger">*</span></label><input type="text" name="full_name" id="uName" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label"><?= h(__('field_email')) ?> <span class="text-danger">*</span></label><input type="email" name="email" id="uEmail" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label"><?= h(__('field_username')) ?> <span class="text-danger">*</span></label><input type="text" name="username" id="uUsername" class="form-control" required autocomplete="off"></div>
            <div class="col-md-6"><label class="form-label"><?= h(__('field_phone')) ?></label><input type="text" name="phone" id="uPhone" class="form-control"></div>
            <div class="col-md-6"><label class="form-label"><?= h(__('field_job_title')) ?></label><input type="text" name="job_title" id="uJobTitle" class="form-control"></div>
            <div class="col-md-6"><label class="form-label"><?= h(__('field_employee_id')) ?></label><input type="text" name="employee_id" id="uEmpId" class="form-control"></div>
            <div class="col-md-4">
              <label class="form-label"><?= h(__('field_role')) ?> <span class="text-danger">*</span></label>
              <select name="role_id" id="uRole" class="form-select" required>
                <option value="">—</option>
                <?php foreach($roles as $r): ?><option value="<?=$r['id']?>"><?=h($r['name'])?></option><?php endforeach;?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= h(__('field_department')) ?></label>
              <select name="department_id" id="uDept" class="form-select">
                <option value="">—</option>
                <?php foreach($depts as $d): ?><option value="<?=$d['id']?>"><?=h($d['name'])?></option><?php endforeach;?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= h(__('field_status')) ?></label>
              <select name="status" id="uStatus" class="form-select">
                <option value="active"><?= h(__('status_active')) ?></option>
                <option value="inactive"><?= h(__('status_inactive')) ?></option>
                <option value="suspended"><?= h(__('status_suspended')) ?></option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= h(__('field_password')) ?> <span class="text-danger new-req">*</span></label>
              <div class="input-group">
                <input type="password" name="password" id="uPassword" class="form-control" autocomplete="new-password" oninput="checkPasswordStrength(this.value,'uPwdStr')">
                <button type="button" class="btn btn-outline-secondary" data-toggle-pw="#uPassword"><i class="bi bi-eye"></i></button>
              </div>
              <div id="uPwdStr"></div>
              <div class="form-text edit-hint d-none"><?= h(__('emp_pass_hint')) ?></div>
            </div>
            <div class="col-md-6"><label class="form-label"><?= h(__('profile_avatar')) ?></label><input type="file" name="avatar" class="form-control" accept="image/*"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= h(__('btn_cancel')) ?></button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i><?= h(__('btn_save')) ?></button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
function resetUserModal() {
  ['userId','uName','uEmail','uUsername','uPhone','uJobTitle','uEmpId','uPassword'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
  ['uRole','uDept'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
  document.getElementById('uStatus').value='active';
  document.getElementById('uPwdStr').innerHTML='';
  document.getElementById('userModalTitle').innerHTML='<i class="bi bi-person-plus text-primary me-2"></i><?= h(__("emp_add")) ?>';
  document.querySelector('.new-req').style.display='';
  document.querySelector('.edit-hint').classList.add('d-none');
  document.getElementById('uPassword').required=true;
}
async function editUser(id) {
  const res = await api('/api/user_get.php', {id});
  if (!res.success) return Toast.show('Error loading user','danger');
  const u = res.user;
  document.getElementById('userId').value    = u.id;
  document.getElementById('uName').value     = u.full_name||'';
  document.getElementById('uEmail').value    = u.email||'';
  document.getElementById('uUsername').value = u.username||'';
  document.getElementById('uPhone').value    = u.phone||'';
  document.getElementById('uJobTitle').value = u.job_title||'';
  document.getElementById('uEmpId').value    = u.employee_id||'';
  document.getElementById('uRole').value     = u.role_id||'';
  document.getElementById('uDept').value     = u.department_id||'';
  document.getElementById('uStatus').value   = u.status||'active';
  document.getElementById('uPassword').required = false;
  document.getElementById('userModalTitle').innerHTML='<i class="bi bi-pencil text-primary me-2"></i><?= h(__("emp_edit")) ?>';
  document.querySelector('.new-req').style.display='none';
  document.querySelector('.edit-hint').classList.remove('d-none');
  new bootstrap.Modal(document.getElementById('modalUser')).show();
}
</script>
<?php include APP_ROOT . '/includes/footer.php'; ?>
