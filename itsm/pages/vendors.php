<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requirePermission('vendors','view');
$pageTitle  = __('vendor_title');
$breadcrumb = [['label'=>__('vendor_title'),'active'=>true]];

$search = trim($_GET['q']??'');
$status = $_GET['status']??'';
$page   = max(1,(int)($_GET['page']??1));

$where=[]; $params=[];
if ($search){ $where[]="(v.name LIKE ? OR v.contact_name LIKE ? OR v.email LIKE ?)"; $s="%$search%"; $params=array_merge($params,[$s,$s,$s]); }
if ($status){ $where[]="v.status=?"; $params[]=$status; }
$w=$where?'WHERE '.implode(' AND ',$where):'';

$baseQ="SELECT v.*,(SELECT COUNT(*) FROM assets a WHERE a.vendor_id=v.id) as asset_count,(SELECT COUNT(*) FROM licenses l WHERE l.vendor_id=v.id) as lic_count,(SELECT COALESCE(SUM(price),0) FROM assets a WHERE a.vendor_id=v.id) as total_value FROM vendors v $w ORDER BY v.name";
$pag = paginate($db,$baseQ,$params,$page);

$filterBase='?'.http_build_query(array_filter(['q'=>$search,'status'=>$status]));
include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-shop"></i> <?= h(__('vendor_title')) ?></h1>
  <div class="d-flex gap-2">
    <?php if ($auth->can('vendors','export')): ?>
    <button class="btn btn-sm btn-outline-secondary" onclick="exportTable('vendorTable','vendors')"><i class="bi bi-download me-1"></i><?= h(__('btn_export')) ?></button>
    <?php endif; ?>
    <?php if ($auth->can('vendors','add')): ?>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalVendor" onclick="resetVendorModal()">
      <i class="bi bi-plus-lg me-1"></i><?= h(__('vendor_add')) ?>
    </button>
    <?php endif; ?>
  </div>
</div>

<!-- Filters -->
<div class="card mb-3"><div class="card-body py-2">
  <form method="get" class="row g-2 align-items-end">
    <div class="col-md-5"><input type="text" name="q" class="form-control form-control-sm" placeholder="<?= h(__('field_name')).'...' ?>" value="<?= h($search) ?>"></div>
    <div class="col-md-2"><select name="status" class="form-select form-select-sm"><option value="">All Status</option><option value="active" <?=$status==='active'?'selected':''?>>Active</option><option value="inactive" <?=$status==='inactive'?'selected':''?>>Inactive</option></select></div>
    <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i><?= h(__('btn_filter')) ?></button><a href="<?=APP_URL?>/pages/vendors.php" class="btn btn-sm btn-outline-secondary ms-1"><?= h(__('btn_reset')) ?></a></div>
  </form>
</div></div>

<div class="row g-3">
  <?php foreach ($pag['records'] as $v): ?>
  <div class="col-md-6 col-lg-4">
    <div class="card hover-lift h-100">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between mb-3">
          <div class="d-flex align-items-center gap-3">
            <div class="stat-icon info flex-shrink-0" style="width:40px;height:40px;font-size:18px;"><i class="bi bi-building"></i></div>
            <div>
              <div class="fw-bold"><?= h($v['name']) ?></div>
              <div class="text-muted" style="font-size:12px;"><?= h($v['contact_name']??'') ?></div>
            </div>
          </div>
          <span class="badge bg-<?= $v['status']==='active'?'success':'secondary' ?>"><?= h(ucfirst($v['status'])) ?></span>
        </div>
        <div class="row g-2 mb-3 text-center">
          <div class="col-4"><div class="fw-bold text-primary"><?=$v['asset_count']?></div><div class="text-muted" style="font-size:11px;"><?= h(__('vendor_assets')) ?></div></div>
          <div class="col-4"><div class="fw-bold text-info"><?=$v['lic_count']?></div><div class="text-muted" style="font-size:11px;"><?= h(__('nav_licenses')) ?></div></div>
          <div class="col-4"><div class="fw-bold text-success" style="font-size:12px;"><?=formatMoney($v['total_value'])?></div><div class="text-muted" style="font-size:11px;">Value</div></div>
        </div>
        <?php if ($v['email']||$v['phone']||$v['website']): ?>
        <div style="font-size:12px;" class="mb-3">
          <?php if($v['email']): ?><div><i class="bi bi-envelope me-1 text-muted"></i><a href="mailto:<?=h($v['email'])?>" class="text-decoration-none"><?=h($v['email'])?></a></div><?php endif; ?>
          <?php if($v['phone']): ?><div><i class="bi bi-telephone me-1 text-muted"></i><?=h($v['phone'])?></div><?php endif; ?>
          <?php if($v['website']): ?><div><i class="bi bi-globe me-1 text-muted"></i><a href="<?=h($v['website'])?>" target="_blank" class="text-decoration-none"><?=h($v['website'])?></a></div><?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="d-flex gap-2 justify-content-end">
          <?php if($auth->can('vendors','edit')): ?><button class="btn btn-sm btn-outline-secondary" onclick="editVendor(<?=$v['id']?>)"><i class="bi bi-pencil me-1"></i><?=h(__('btn_edit'))?></button><?php endif; ?>
          <?php if($auth->can('vendors','delete')&&$v['asset_count']==0&&$v['lic_count']==0): ?><button class="btn btn-sm btn-outline-danger" onclick="confirmDelete('<?=APP_URL?>/actions/vendor_delete.php?id=<?=$v['id']?>&csrf_token=<?=h($auth->generateCsrfToken())?>','<?= h(__("msg_confirm_delete")) ?>')"><i class="bi bi-trash"></i></button><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if(empty($pag['records'])): ?><div class="col-12"><div class="text-center py-5 text-muted"><i class="bi bi-shop fs-1 d-block mb-2"></i><?=h(__('msg_no_records'))?></div></div><?php endif; ?>
</div>
<?php if($pag['total_pages']>1): ?><div class="d-flex justify-content-center mt-4"><?=renderPagination($pag,APP_URL.'/pages/vendors.php'.$filterBase)?></div><?php endif; ?>

<!-- Modal -->
<?php if ($auth->can('vendors','add')): ?>
<div class="modal fade" id="modalVendor" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="vendorModalTitle"><i class="bi bi-shop text-primary me-2"></i><?=h(__('vendor_add'))?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form data-ajax action="<?=APP_URL?>/actions/vendor_save.php" method="post">
        <?= $auth->csrfField() ?>
        <input type="hidden" name="id" id="vendorId" value="">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-8"><label class="form-label"><?=h(__('field_name'))?> <span class="text-danger">*</span></label><input type="text" name="name" id="vName" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label"><?=h(__('field_status'))?></label><select name="status" id="vStatus" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
            <div class="col-md-6"><label class="form-label"><?=h(__('field_contact'))?></label><input type="text" name="contact_name" id="vContact" class="form-control"></div>
            <div class="col-md-6"><label class="form-label"><?=h(__('field_email'))?></label><input type="email" name="email" id="vEmail" class="form-control"></div>
            <div class="col-md-6"><label class="form-label"><?=h(__('field_phone'))?></label><input type="text" name="phone" id="vPhone" class="form-control"></div>
            <div class="col-md-6"><label class="form-label"><?=h(__('field_website'))?></label><input type="url" name="website" id="vWebsite" class="form-control" placeholder="https://..."></div>
            <div class="col-12"><label class="form-label"><?=h(__('field_address'))?></label><textarea name="address" id="vAddress" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><label class="form-label"><?=h(__('field_notes'))?></label><textarea name="notes" id="vNotes" class="form-control" rows="2"></textarea></div>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?=h(__('btn_cancel'))?></button><button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i><?=h(__('btn_save'))?></button></div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
function resetVendorModal() {
  ['vendorId','vName','vContact','vEmail','vPhone','vWebsite','vAddress','vNotes'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
  const s=document.getElementById('vStatus');if(s)s.value='active';
  document.getElementById('vendorModalTitle').innerHTML='<i class="bi bi-shop text-primary me-2"></i><?=h(__("vendor_add"))?>';
}
async function editVendor(id) {
  const res=await api('/api/vendor_get.php',{id});
  if(!res.success)return Toast.show('Error','danger');
  const v=res.vendor;
  const map={vendorId:'id',vName:'name',vContact:'contact_name',vEmail:'email',vPhone:'phone',vWebsite:'website',vAddress:'address',vNotes:'notes',vStatus:'status'};
  Object.entries(map).forEach(([elId,field])=>{const el=document.getElementById(elId);if(el)el.value=v[field]||'';});
  document.getElementById('vendorModalTitle').innerHTML='<i class="bi bi-pencil text-primary me-2"></i><?=h(__("vendor_edit"))?>';
  new bootstrap.Modal(document.getElementById('modalVendor')).show();
}
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
