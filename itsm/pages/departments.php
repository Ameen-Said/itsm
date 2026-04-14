<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requirePermission('departments','view');
$pageTitle  = __('dept_title');
$breadcrumb = [['label'=>__('dept_title'),'active'=>true]];

$depts = $db->query(
    "SELECT d.*, u.full_name as manager_name,
     (SELECT COUNT(*) FROM users e WHERE e.department_id=d.id AND e.status='active') as emp_count,
     (SELECT COUNT(*) FROM assets a WHERE a.department_id=d.id) as asset_count,
     (SELECT COALESCE(SUM(a.price),0) FROM assets a WHERE a.department_id=d.id) as asset_value
     FROM departments d LEFT JOIN users u ON d.manager_id=u.id ORDER BY d.name"
)->fetchAll();

$managers = $db->query("SELECT id,full_name FROM users WHERE status='active' ORDER BY full_name")->fetchAll();
include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-diagram-3-fill"></i> <?= h(__('dept_title')) ?></h1>
  <?php if ($auth->can('departments','add')): ?>
  <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalDept" onclick="resetDeptModal()">
    <i class="bi bi-plus-lg me-1"></i><?= h(__('dept_add')) ?>
  </button>
  <?php endif; ?>
</div>

<div class="row g-3">
  <?php if (empty($depts)): ?>
  <div class="col-12"><div class="text-center py-5 text-muted"><i class="bi bi-building fs-1 d-block mb-2"></i><?= h(__('msg_no_data')) ?></div></div>
  <?php endif; ?>
  <?php foreach ($depts as $d):
    $pct = $d['budget']>0 ? min(100,round($d['asset_value']/$d['budget']*100)) : 0;
  ?>
  <div class="col-md-6 col-lg-4">
    <div class="card hover-lift h-100">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between mb-3">
          <div class="d-flex align-items-center gap-3">
            <div class="stat-icon primary" style="width:42px;height:42px;font-size:18px;flex-shrink:0;"><i class="bi bi-building"></i></div>
            <div>
              <h6 class="mb-0 fw-bold"><?= h($d['name']) ?></h6>
              <?php if ($d['manager_name']): ?>
              <div class="text-muted" style="font-size:12px;"><i class="bi bi-person me-1"></i><?= h($d['manager_name']) ?></div>
              <?php endif; ?>
            </div>
          </div>
          <div class="d-flex gap-1">
            <?php if ($auth->can('departments','edit')): ?>
            <button class="btn btn-icon btn-sm btn-outline-secondary" onclick="editDept(<?= $d['id'] ?>)"><i class="bi bi-pencil"></i></button>
            <?php endif; ?>
            <?php if ($auth->can('departments','delete') && $d['emp_count']==0): ?>
            <button class="btn btn-icon btn-sm btn-outline-danger" onclick="confirmDelete('<?= APP_URL ?>/actions/dept_delete.php?id=<?= $d['id'] ?>&csrf_token=<?= h($auth->generateCsrfToken()) ?>')"><i class="bi bi-trash"></i></button>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($d['description']): ?><p class="text-muted mb-3" style="font-size:13px;"><?= h($d['description']) ?></p><?php endif; ?>
        <div class="row g-2 mb-3">
          <div class="col-4 text-center p-2 rounded" style="background:var(--surface-2);">
            <div class="fw-bold text-primary" style="font-size:18px;"><?= $d['emp_count'] ?></div>
            <div class="text-muted" style="font-size:11px;"><?= h(__('dept_employees')) ?></div>
          </div>
          <div class="col-4 text-center p-2 rounded" style="background:var(--surface-2);">
            <div class="fw-bold text-info" style="font-size:18px;"><?= $d['asset_count'] ?></div>
            <div class="text-muted" style="font-size:11px;"><?= h(__('nav_assets')) ?></div>
          </div>
          <div class="col-4 text-center p-2 rounded" style="background:var(--surface-2);">
            <div class="fw-bold text-success" style="font-size:12px;"><?= formatMoney($d['asset_value']) ?></div>
            <div class="text-muted" style="font-size:11px;">Value</div>
          </div>
        </div>
        <?php if ($d['budget']>0): ?>
        <div>
          <div class="d-flex justify-content-between mb-1" style="font-size:12px;">
            <span class="text-muted"><?= h(__('dept_budget_used')) ?></span>
            <span><?= $pct ?>% of <?= formatMoney($d['budget']) ?></span>
          </div>
          <div class="progress" style="height:5px;">
            <div class="progress-bar <?= $pct>=90?'bg-danger':($pct>=70?'bg-warning':'bg-success') ?>" style="width:<?= $pct ?>%"></div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Modal -->
<?php if ($auth->can('departments','add')): ?>
<div class="modal fade" id="modalDept" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deptModalTitle"><i class="bi bi-building text-primary me-2"></i><?= h(__('dept_add')) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form data-ajax action="<?= APP_URL ?>/actions/dept_save.php" method="post">
        <?= $auth->csrfField() ?>
        <input type="hidden" name="id" id="deptId" value="">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12"><label class="form-label"><?= h(__('field_name')) ?> <span class="text-danger">*</span></label><input type="text" name="name" id="deptName" class="form-control" required></div>
            <div class="col-12"><label class="form-label"><?= h(__('field_description')) ?></label><textarea name="description" id="deptDesc" class="form-control" rows="2"></textarea></div>
            <div class="col-md-6"><label class="form-label"><?= h(__('field_manager')) ?></label>
              <select name="manager_id" id="deptMgr" class="form-select"><option value="">—</option>
                <?php foreach($managers as $m): ?><option value="<?=$m['id']?>"><?=h($m['full_name'])?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6"><label class="form-label"><?= h(__('field_budget')) ?></label>
              <div class="input-group"><span class="input-group-text">$</span><input type="number" name="budget" id="deptBudget" class="form-control" step="0.01" value="0" min="0"></div>
            </div>
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
function resetDeptModal() {
  ['deptId','deptName','deptDesc','deptBudget'].forEach(id => { const el=document.getElementById(id); if(el) el.value=id==='deptBudget'?'0':''; });
  const mgr=document.getElementById('deptMgr'); if(mgr) mgr.value='';
  document.getElementById('deptModalTitle').innerHTML='<i class="bi bi-building text-primary me-2"></i><?= h(__("dept_add")) ?>';
}
async function editDept(id) {
  const res = await api('/api/dept_get.php',{id});
  if (!res.success) return Toast.show('Error','danger');
  const d = res.dept;
  document.getElementById('deptId').value     = d.id;
  document.getElementById('deptName').value   = d.name||'';
  document.getElementById('deptDesc').value   = d.description||'';
  document.getElementById('deptMgr').value    = d.manager_id||'';
  document.getElementById('deptBudget').value = d.budget||0;
  document.getElementById('deptModalTitle').innerHTML='<i class="bi bi-pencil text-primary me-2"></i><?= h(__("dept_edit")) ?>';
  new bootstrap.Modal(document.getElementById('modalDept')).show();
}
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
