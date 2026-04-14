<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requirePermission('licenses','view');
$pageTitle  = __('lic_title');
$breadcrumb = [['label'=>__('lic_title'),'active'=>true]];

$search = trim($_GET['q']??'');
$type   = $_GET['type']??'';
$status = $_GET['status']??'';
$page   = max(1,(int)($_GET['page']??1));

$where=[]; $params=[];
if ($search){ $where[]="l.software_name LIKE ?"; $params[]="%$search%"; }
if ($type)  { $where[]="l.type=?"; $params[]=$type; }
if ($status){ $where[]="l.status=?"; $params[]=$status; }
$w=$where?'WHERE '.implode(' AND ',$where):'';

$sql="SELECT l.*, v.name as vendor_name,
      (SELECT COUNT(*) FROM license_assignments la WHERE la.license_id=l.id) as assigned_count
      FROM licenses l LEFT JOIN vendors v ON l.vendor_id=v.id $w ORDER BY l.software_name";
$pag = paginate($db,$sql,$params,$page);
$filter='?'.http_build_query(array_filter(['q'=>$search,'type'=>$type,'status'=>$status]));

// Stats
$stats = [
    'total'   => $db->query("SELECT COUNT(*) FROM licenses")->fetchColumn(),
    'active'  => $db->query("SELECT COUNT(*) FROM licenses WHERE status='active'")->fetchColumn(),
    'expiring'=> $db->query("SELECT COUNT(*) FROM licenses WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) AND status='active'")->fetchColumn(),
    'expired' => $db->query("SELECT COUNT(*) FROM licenses WHERE status='expired' OR expiry_date < CURDATE()")->fetchColumn(),
];

$users  = $db->query("SELECT id,full_name FROM users WHERE status='active' ORDER BY full_name")->fetchAll();
$assets = $db->query("SELECT id,asset_code,name FROM assets WHERE status='available' OR status='assigned' ORDER BY name")->fetchAll();
$vendors= $db->query("SELECT id,name FROM vendors ORDER BY name")->fetchAll();
$types  = ['per_user'=>__('lic_type_per_user'),'per_device'=>__('lic_type_per_device'),'enterprise'=>__('lic_type_enterprise'),'subscription'=>__('lic_type_sub'),'open_source'=>__('lic_type_oss')];

include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title"><div class="title-icon"><i class="bi bi-tags"></i></div> <?= h(__('lic_title')) ?></h1>
  <div class="page-actions">
    <?php if($auth->can('licenses','export')): ?>
    <button class="btn btn-sm btn-outline-secondary" onclick="exportTable('licTable','licenses')"><i class="bi bi-download me-1"></i><?= h(__('btn_export')) ?></button>
    <?php endif; ?>
    <?php if($auth->can('licenses','add')): ?>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalLic" onclick="resetLicModal()">
      <i class="bi bi-plus-lg me-1"></i><?= h(__('lic_add')) ?>
    </button>
    <?php endif; ?>
  </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="stat-card blue"><div class="stat-icon-box blue"><i class="bi bi-tags"></i></div><div class="stat-num"><?= $stats['total'] ?></div><div class="stat-label"><?= h(__('lic_total')) ?></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card green"><div class="stat-icon-box green"><i class="bi bi-check-circle"></i></div><div class="stat-num"><?= $stats['active'] ?></div><div class="stat-label"><?= h(__('lic_active')) ?></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card amber"><div class="stat-icon-box amber"><i class="bi bi-clock-history"></i></div><div class="stat-num"><?= $stats['expiring'] ?></div><div class="stat-label"><?= h(__('lic_expiring_30')) ?></div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card red"><div class="stat-icon-box red"><i class="bi bi-x-circle"></i></div><div class="stat-num"><?= $stats['expired'] ?></div><div class="stat-label"><?= h(__('lic_expired')) ?></div></div></div>
</div>

<!-- Filters -->
<div class="card mb-3"><div class="card-body py-2">
  <form method="get" class="row g-2 align-items-end">
    <div class="col-md-4"><input type="text" name="q" class="form-control form-control-sm" placeholder="<?= h(__('field_name')).'...' ?>" value="<?= h($search) ?>"></div>
    <div class="col-md-2"><select name="type" class="form-select form-select-sm"><option value=""><?= h(__('lic_all_types')) ?></option><?php foreach($types as $k=>$v): ?><option value="<?=h($k)?>" <?=$type===$k?'selected':''?>><?=h($v)?></option><?php endforeach;?></select></div>
    <div class="col-md-2"><select name="status" class="form-select form-select-sm"><option value=""><?= h(__('field_status')).' — '.h(__('emp_all_status')) ?></option><option value="active" <?=$status==='active'?'selected':''?>><?= h(__('status_active')) ?></option><option value="expired" <?=$status==='expired'?'selected':''?>><?= h(__('status_expired')) ?></option><option value="cancelled" <?=$status==='cancelled'?'selected':''?>><?= h(__('status_cancelled')) ?></option></select></div>
    <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i><?= h(__('btn_filter')) ?></button> <a href="<?= APP_URL ?>/pages/licenses.php" class="btn btn-sm btn-outline-secondary"><?= h(__('btn_reset')) ?></a></div>
  </form>
</div></div>

<div class="table-card">
  <div class="table-toolbar"><span class="text-muted small"><?= number_format($pag['total']) ?> <?= h(__('lic_title')) ?></span></div>
  <div class="table-responsive">
    <table class="table" id="licTable">
      <thead><tr>
        <th><?= h(__('field_name')) ?></th><th><?= h(__('field_type')) ?></th>
        <th><?= h(__('lic_seats_info')) ?></th><th><?= h(__('field_vendor')) ?></th>
        <th><?= h(__('field_expiry')) ?></th><th><?= h(__('field_price')) ?></th>
        <th><?= h(__('field_status')) ?></th><th class="text-end"><?= h(__('field_actions')) ?></th>
      </tr></thead>
      <tbody>
        <?php if(empty($pag['records'])): ?><tr><td colspan="8" class="text-center py-4 text-muted"><?= h(__('msg_no_records')) ?></td></tr><?php endif; ?>
        <?php foreach($pag['records'] as $l):
          $used = (int)$l['assigned_count'];
          $total= (int)$l['seats'];
          $avail= max(0,$total-$used);
          $pct  = $total>0?min(100,round($used/$total*100)):0;
          $days = daysUntil($l['expiry_date']);
          $rowCls= $days!==null?($days<0?'row-critical':($days<=30?'row-warn':'')):'';
        ?>
        <tr class="<?=$rowCls?>">
          <td>
            <div class="fw-semibold" style="font-size:13px;"><?= h($l['software_name']) ?></div>
            <?php if($l['license_key']): ?><div class="mono text-muted" style="font-size:11px;"><?= h(mb_substr($l['license_key'],0,30)).'…' ?></div><?php endif;?>
          </td>
          <td><span class="badge bg-info-subtle text-info"><?= h($types[$l['type']]??$l['type']) ?></span></td>
          <td style="min-width:160px;">
            <div class="d-flex justify-content-between mb-1" style="font-size:12px;">
              <span><?=$used?>/<?=$total?> <?= h(__('field_seats_used')) ?></span>
              <span class="text-<?=$avail>0?'success':'danger?>'?>"><?=$avail?> <?= h(__('field_seats_avail')) ?></span>
            </div>
            <div class="progress" style="height:4px;"><div class="progress-bar bg-<?=$pct>=90?'danger':($pct>=70?'warning':'success')?>" style="width:<?=$pct?>%"></div></div>
          </td>
          <td style="font-size:13px;"><?= h($l['vendor_name']??'—') ?></td>
          <td class="<?= expiryClass($l['expiry_date']) ?>" style="font-size:12px;"><?= formatDate($l['expiry_date']) ?><?php if($days!==null): ?><br><small>(<?= abs($days) ?>d <?=$days<0?'ago':'left'?>)</small><?php endif;?></td>
          <td class="mono" style="font-size:12px;"><?= formatMoney($l['price']) ?></td>
          <td><?= licenseStatusBadge($l['status']) ?></td>
          <td class="text-end">
            <?php if($auth->can('licenses','edit')&&$avail>0): ?>
            <button class="btn btn-icon btn-sm btn-outline-success" onclick="openAssignLic(<?=$l['id']?>,<?=$avail?>)" title="<?= h(__('lic_assign')) ?>"><i class="bi bi-person-plus"></i></button>
            <?php endif; ?>
            <?php if($auth->can('licenses','edit')): ?>
            <button class="btn btn-icon btn-sm btn-outline-secondary" onclick="editLic(<?=$l['id']?>)" title="<?= h(__('btn_edit')) ?>"><i class="bi bi-pencil"></i></button>
            <?php endif; ?>
            <?php if($auth->can('licenses','delete')): ?>
            <button class="btn btn-icon btn-sm btn-outline-danger" onclick="confirmDelete('<?= APP_URL ?>/actions/license_delete.php?id=<?=$l['id']?>&csrf_token=<?=h($auth->generateCsrfToken())?>')" title="<?= h(__('btn_delete')) ?>"><i class="bi bi-trash"></i></button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="table-footer"><span><?= $pag['total'] ?> <?= h(__('lic_title')) ?></span><?= renderPagination($pag, APP_URL.'/pages/licenses.php'.$filter) ?></div>
</div>

<!-- Add/Edit License Modal -->
<?php if ($auth->can('licenses','add')): ?>
<div class="modal fade" id="modalLic" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="licModalTitle"><i class="bi bi-tags text-primary me-2"></i><?= h(__('lic_add')) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form data-ajax action="<?= APP_URL ?>/actions/license_save.php" method="post">
        <?= $auth->csrfField() ?>
        <input type="hidden" name="id" id="licId" value="">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12"><label class="form-label"><?= h(__('field_name')) ?> <span class="text-danger">*</span></label><input type="text" name="software_name" id="lName" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label"><?= h(__('field_license_key')) ?></label><input type="text" name="license_key" id="lKey" class="form-control mono"></div>
            <div class="col-md-3"><label class="form-label"><?= h(__('field_type')) ?></label><select name="type" id="lType" class="form-select"><?php foreach($types as $k=>$v): ?><option value="<?=h($k)?>"><?=h($v)?></option><?php endforeach;?></select></div>
            <div class="col-md-3"><label class="form-label"><?= h(__('field_seats')) ?></label><input type="number" name="seats" id="lSeats" class="form-control" value="1" min="1"></div>
            <div class="col-md-6"><label class="form-label"><?= h(__('field_vendor')) ?></label><select name="vendor_id" id="lVendor" class="form-select"><option value="">—</option><?php foreach($vendors as $v): ?><option value="<?=$v['id']?>"><?=h($v['name'])?></option><?php endforeach;?></select></div>
            <div class="col-md-6"><label class="form-label"><?= h(__('field_status')) ?></label><select name="status" id="lStatus" class="form-select"><option value="active"><?= h(__('status_active')) ?></option><option value="expired"><?= h(__('status_expired')) ?></option><option value="cancelled"><?= h(__('status_cancelled')) ?></option></select></div>
            <div class="col-md-4"><label class="form-label"><?= h(__('field_purchase')) ?></label><input type="date" name="purchase_date" id="lPurch" class="form-control"></div>
            <div class="col-md-4"><label class="form-label"><?= h(__('field_expiry')) ?></label><input type="date" name="expiry_date" id="lExpiry" class="form-control"></div>
            <div class="col-md-4"><label class="form-label"><?= h(__('field_price')) ?></label><div class="input-group"><span class="input-group-text"><?= getSetting('currency','USD') ?></span><input type="number" name="price" id="lPrice" class="form-control" step="0.01" value="0" min="0"></div></div>
            <div class="col-12"><label class="form-label"><?= h(__('field_notes')) ?></label><textarea name="notes" id="lNotes" class="form-control" rows="2"></textarea></div>
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

<!-- Assign License Modal -->
<div class="modal fade" id="modalAssignLic" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="bi bi-person-plus text-success me-2"></i><?= h(__('lic_assign')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form data-ajax action="<?= APP_URL ?>/actions/license_assign.php" method="post">
        <?= $auth->csrfField() ?>
        <input type="hidden" name="license_id" id="assignLicId" value="">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label"><?= h(__('field_type')) ?></label>
            <select name="assign_type" id="assignType" class="form-select" onchange="toggleAssignType(this.value)">
              <option value="user"><?= h(__('lic_assign_user')) ?></option>
              <option value="asset"><?= h(__('lic_assign_asset')) ?></option>
            </select>
          </div>
          <div id="userSelect" class="mb-3">
            <label class="form-label"><?= h(__('nav_employees')) ?> <span class="text-danger">*</span></label>
            <select name="user_id" class="form-select"><option value="">—</option><?php foreach($users as $u): ?><option value="<?=$u['id']?>"><?=h($u['full_name'])?></option><?php endforeach;?></select>
          </div>
          <div id="assetSelect" class="mb-3 d-none">
            <label class="form-label"><?= h(__('nav_assets')) ?></label>
            <select name="asset_id" class="form-select"><option value="">—</option><?php foreach($assets as $a): ?><option value="<?=$a['id']?>"><?=h($a['name']).' ('.$a['asset_code'].')'?></option><?php endforeach;?></select>
          </div>
          <div class="mb-3"><label class="form-label"><?= h(__('field_notes')) ?></label><textarea name="notes" class="form-control" rows="2"></textarea></div>
          <div id="licAvailInfo" class="alert alert-info py-2" style="font-size:13px;"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= h(__('btn_cancel')) ?></button><button type="submit" class="btn btn-success"><i class="bi bi-check2 me-1"></i><?= h(__('btn_assign')) ?></button></div>
      </form>
    </div>
  </div>
</div>

<script>
function resetLicModal() {
  ['licId','lName','lKey','lNotes'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
  ['lType','lStatus'].forEach(id=>{const el=document.getElementById(id);if(el)el.selectedIndex=0;});
  ['lVendor'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
  document.getElementById('lSeats').value='1';
  document.getElementById('lPrice').value='0';
  ['lPurch','lExpiry'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
  document.getElementById('licModalTitle').innerHTML='<i class="bi bi-tags text-primary me-2"></i><?= h(__("lic_add")) ?>';
}
async function editLic(id) {
  const res = await api('/api/license_get.php',{id});
  if (!res.success) return Toast.show('Error','danger');
  const l = res.license;
  document.getElementById('licId').value    = l.id;
  document.getElementById('lName').value    = l.software_name||'';
  document.getElementById('lKey').value     = l.license_key||'';
  document.getElementById('lType').value    = l.type||'per_user';
  document.getElementById('lSeats').value   = l.seats||1;
  document.getElementById('lVendor').value  = l.vendor_id||'';
  document.getElementById('lStatus').value  = l.status||'active';
  document.getElementById('lPurch').value   = l.purchase_date||'';
  document.getElementById('lExpiry').value  = l.expiry_date||'';
  document.getElementById('lPrice').value   = l.price||0;
  document.getElementById('lNotes').value   = l.notes||'';
  document.getElementById('licModalTitle').innerHTML='<i class="bi bi-pencil text-primary me-2"></i><?= h(__("lic_edit")) ?>';
  new bootstrap.Modal(document.getElementById('modalLic')).show();
}
function openAssignLic(id, avail) {
  document.getElementById('assignLicId').value = id;
  document.getElementById('licAvailInfo').textContent = `${avail} <?= CURRENT_LANG==='ar'?'مقعد متاح':'seats available' ?>`;
  new bootstrap.Modal(document.getElementById('modalAssignLic')).show();
}
function toggleAssignType(val) {
  document.getElementById('userSelect').classList.toggle('d-none', val!=='user');
  document.getElementById('assetSelect').classList.toggle('d-none', val!=='asset');
}
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
