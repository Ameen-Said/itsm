<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requirePermission('vault','view');
$pageTitle  = __('vault_title');
$breadcrumb = [['label'=>__('vault_title'),'active'=>true]];

$uid     = $auth->getUserId();
$user    = $auth->getUser();
$search  = trim($_GET['q']??'');
$catF    = $_GET['cat']??'';
$where   = ['ve.user_id = ?']; $params = [$uid];
if ($search) { $where[] = "(ve.system_name LIKE ? OR ve.username LIKE ? OR ve.url LIKE ?)"; $s="%$search%"; $params=array_merge($params,[$s,$s,$s]); }
if ($catF)   { $where[] = "ve.category = ?"; $params[]=$catF; }
$stmt = $db->prepare("SELECT id,system_name,url,username,category,is_favourite,updated_at FROM vault_entries ve WHERE ".implode(' AND ',$where)." ORDER BY is_favourite DESC, system_name ASC");
$stmt->execute($params); $entries = $stmt->fetchAll();
$cats = $db->prepare("SELECT DISTINCT category FROM vault_entries WHERE user_id=? ORDER BY category"); $cats->execute([$uid]); $cats=array_column($cats->fetchAll(),'category');
$hasKey = !empty($user['vault_salt']);
$catOpts=['general'=>__('vault_cat_general'),'server'=>__('vault_cat_server'),'database'=>__('vault_cat_database'),'email'=>__('vault_cat_email'),'social'=>__('vault_cat_social'),'banking'=>__('vault_cat_banking'),'vpn'=>__('vault_cat_vpn'),'other'=>__('vault_cat_other')];

include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title"><div class="title-icon"><i class="bi bi-key"></i></div> <?= h(__('vault_title')) ?></h1>
  <div class="page-actions">
    <?php if (!$hasKey): ?><button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalVaultKey"><i class="bi bi-shield-lock me-1"></i><?= h(__('vault_set_key')) ?></button><?php endif; ?>
    <?php if ($auth->can('vault','add')): ?><button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalVault" onclick="resetVaultModal()"><i class="bi bi-plus-lg me-1"></i><?= h(__('vault_add')) ?></button><?php endif; ?>
  </div>
</div>

<?php if (!$hasKey): ?>
<div class="alert alert-warning d-flex gap-2 align-items-start mb-4">
  <i class="bi bi-shield-exclamation fs-5 flex-shrink-0 mt-1"></i>
  <div><strong><?= h(__('vault_not_secured')) ?></strong> <?= h(__('vault_set_key_hint')) ?></div>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="d-flex gap-2 mb-4 flex-wrap align-items-center">
  <div class="input-group" style="max-width:280px;">
    <span class="input-group-text"><i class="bi bi-search"></i></span>
    <input type="text" class="form-control" placeholder="<?= h(__('vault_search_ph')) ?>" value="<?= h($search) ?>"
           onkeyup="filterVault(this.value)">
  </div>
  <select class="form-select" style="max-width:160px;" onchange="filterVaultCat(this.value)">
    <option value=""><?= h(__('vault_all_cats')) ?></option>
    <?php foreach($catOpts as $k=>$v): ?><option value="<?=h($k)?>" <?=$catF===$k?'selected':''?>><?=h($v)?></option><?php endforeach;?>
  </select>
  <div class="ms-auto text-muted small d-flex align-items-center gap-1">
    <i class="bi bi-shield-lock-fill text-success"></i> <?= h(__('vault_encrypted')) ?>
  </div>
</div>

<!-- Grid -->
<div class="row g-3" id="vaultGrid">
  <?php if(empty($entries)): ?>
  <div class="col-12"><div class="text-center py-5 text-muted"><i class="bi bi-key fs-1 d-block mb-3"></i><p><?= h(__('vault_empty')) ?></p></div></div>
  <?php endif; ?>
  <?php foreach($entries as $e): ?>
  <div class="col-md-6 col-lg-4 vault-item" data-name="<?= h(strtolower($e['system_name'])) ?>" data-cat="<?= h($e['category']) ?>">
    <div class="vault-card" data-vault-id="<?= (int)$e['id'] ?>">
      <div class="d-flex align-items-start gap-3 mb-3">
        <div class="vault-icon flex-shrink-0"><?= h(mb_strtoupper(mb_substr($e['system_name'],0,1))) ?></div>
        <div class="flex-grow-1" style="min-width:0;">
          <div class="fw-semibold" style="font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= h($e['system_name']) ?></div>
          <?php if($e['url']): ?><a href="<?= h($e['url']) ?>" target="_blank" rel="noopener" class="text-muted text-decoration-none" style="font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;"><?= h(parse_url($e['url'],PHP_URL_HOST)?:$e['url']) ?></a><?php endif;?>
        </div>
        <div class="d-flex gap-1 align-items-center flex-shrink-0">
          <?php if($e['is_favourite']): ?><i class="bi bi-star-fill text-warning" style="font-size:12px;"></i><?php endif;?>
          <span class="badge bg-secondary-subtle text-secondary" style="font-size:10px;"><?= h($catOpts[$e['category']]??$e['category']) ?></span>
        </div>
      </div>
      <!-- Username -->
      <div class="mb-2">
        <div class="text-muted mb-1" style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;"><?= h(__('field_username')) ?></div>
        <div class="d-flex align-items-center gap-2">
          <span class="mono flex-grow-1" style="font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= h($e['username']) ?></span>
          <button class="btn btn-icon-sm btn-outline-secondary" onclick="copyToClipboard(<?= json_encode($e['username']) ?>,this)" title="<?= h(__('btn_copy')) ?>"><i class="bi bi-copy"></i></button>
        </div>
      </div>
      <!-- Password -->
      <div class="mb-3">
        <div class="text-muted mb-1" style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;"><?= h(__('field_password')) ?></div>
        <div class="d-flex align-items-center gap-2">
          <span class="vault-pass-display mono flex-grow-1" data-revealed="0" style="letter-spacing:2px;font-size:13px;">••••••••••••</span>
          <button class="btn btn-icon-sm btn-outline-secondary" onclick="revealVaultPassword(<?= (int)$e['id'] ?>,this)" title="<?= h(__('btn_show')) ?>"><i class="bi bi-eye"></i></button>
          <button class="btn btn-icon-sm btn-outline-secondary" onclick="copyVaultPassword(<?= (int)$e['id'] ?>,this)" title="<?= h(__('btn_copy')) ?>"><i class="bi bi-copy"></i></button>
        </div>
      </div>
      <!-- Footer -->
      <div class="d-flex justify-content-between align-items-center">
        <span class="text-muted" style="font-size:11px;"><?= h(__('vault_updated')) ?> <?= formatDate($e['updated_at']) ?></span>
        <div class="d-flex gap-1">
          <?php if($auth->can('vault','edit')): ?><button class="btn btn-icon-sm btn-outline-secondary" onclick="editVaultEntry(<?=(int)$e['id']?>)" title="<?= h(__('btn_edit')) ?>"><i class="bi bi-pencil"></i></button><?php endif;?>
          <?php if($auth->can('vault','delete')): ?><button class="btn btn-icon-sm btn-outline-danger" onclick="confirmDelete('<?= APP_URL ?>/actions/vault_delete.php?id=<?=(int)$e['id']?>&csrf_token=<?=h($auth->generateCsrfToken())?>')" title="<?= h(__('btn_delete')) ?>"><i class="bi bi-trash"></i></button><?php endif;?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Add/Edit Modal -->
<?php if ($auth->can('vault','add')): ?>
<div class="modal fade" id="modalVault" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="vaultModalTitle"><i class="bi bi-key text-primary me-2"></i><?= h(__('vault_add')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form data-ajax action="<?= APP_URL ?>/actions/vault_save.php" method="post" id="vaultForm">
        <?= $auth->csrfField() ?>
        <input type="hidden" name="id" id="vId" value="">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12"><label class="form-label"><?= h(__('vault_system')) ?> <span class="text-danger">*</span></label><input type="text" name="system_name" id="vSystem" class="form-control" required></div>
            <div class="col-12"><label class="form-label"><?= h(__('field_url')) ?></label><input type="url" name="url" id="vUrl" class="form-control" placeholder="https://..."></div>
            <div class="col-12"><label class="form-label"><?= h(__('field_username')) ?> <span class="text-danger">*</span></label><input type="text" name="username" id="vUsername" class="form-control" required autocomplete="new-username"></div>
            <div class="col-12">
              <label class="form-label"><?= h(__('field_password')) ?> <span class="text-danger" id="vPassReq">*</span></label>
              <div class="input-group">
                <input type="password" name="password" id="vPass" class="form-control" autocomplete="new-password" oninput="checkPasswordStrength(this.value,'vStrength')">
                <button type="button" class="btn btn-outline-secondary" data-toggle-pw="#vPass"><i class="bi bi-eye"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="generatePassword('vPass')" title="Generate"><i class="bi bi-shuffle"></i></button>
              </div>
              <div id="vStrength"></div>
              <div class="form-text edit-hint d-none"><?= h(__('emp_pass_hint')) ?></div>
            </div>
            <div class="col-md-6"><label class="form-label"><?= h(__('field_category')) ?></label><select name="category" id="vCat" class="form-select"><?php foreach($catOpts as $k=>$v): ?><option value="<?=h($k)?>"><?=h($v)?></option><?php endforeach;?></select></div>
            <div class="col-md-6 d-flex align-items-end"><div class="form-check mb-2"><input type="checkbox" name="is_favourite" id="vFav" value="1" class="form-check-input"><label class="form-check-label" for="vFav"><i class="bi bi-star text-warning me-1"></i><?= h(__('vault_favourite')) ?></label></div></div>
            <div class="col-12"><label class="form-label"><?= h(__('field_notes')) ?></label><textarea name="notes" id="vNotes" class="form-control" rows="2"></textarea></div>
            <?php if ($hasKey): ?>
            <div class="col-12">
              <div class="p-3 rounded" style="background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.2);">
                <label class="form-label mb-1"><i class="bi bi-shield-lock text-warning me-1"></i><?= h(__('vault_master_req')) ?> <span class="text-danger">*</span></label>
                <input type="password" name="master_password" class="form-control" required>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= h(__('btn_cancel')) ?></button><button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i><?= h(__('btn_save')) ?></button></div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Master Key Modal -->
<div class="modal fade" id="modalVaultKey" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="bi bi-shield-lock text-warning me-2"></i><?= h(__('vault_set_key')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form data-ajax action="<?= APP_URL ?>/actions/vault_master_key.php" method="post">
        <?= $auth->csrfField() ?>
        <div class="modal-body">
          <div class="alert alert-warning small py-2"><?= h(__('vault_key_warning')) ?></div>
          <div class="mb-3"><label class="form-label">Master Password <span class="text-danger">*</span></label><input type="password" name="master_password" class="form-control" required minlength="8"></div>
          <div><label class="form-label">Confirm <span class="text-danger">*</span></label><input type="password" name="master_password_confirm" class="form-control" required></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= h(__('btn_cancel')) ?></button><button type="submit" class="btn btn-warning"><i class="bi bi-shield-check me-1"></i>Set Key</button></div>
      </form>
    </div>
  </div>
</div>

<script>
function filterVault(q) {
  q=q.toLowerCase();
  document.querySelectorAll('.vault-item').forEach(el=>{el.style.display=(!q||el.dataset.name.includes(q))?'':'none';});
}
function filterVaultCat(cat) {
  document.querySelectorAll('.vault-item').forEach(el=>{el.style.display=(!cat||el.dataset.cat===cat)?'':'none';});
}
function resetVaultModal() {
  ['vId','vSystem','vUrl','vUsername','vPass','vNotes'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
  document.getElementById('vCat').value='general';
  document.getElementById('vFav').checked=false;
  document.getElementById('vStrength').innerHTML='';
  document.getElementById('vaultModalTitle').innerHTML='<i class="bi bi-key text-primary me-2"></i><?= h(__("vault_add")) ?>';
  const pr=document.getElementById('vPassReq'); if(pr) pr.style.display='';
  document.querySelector('#vaultForm .edit-hint')?.classList.add('d-none');
  document.getElementById('vPass').required=true;
  const mp=document.querySelector('#vaultForm [name=master_password]'); if(mp) mp.required=true;
}
async function editVaultEntry(id) {
  const res=await api('/api/vault_get.php',{id});
  if(!res.success) return Toast.show('Error','danger');
  const e=res.entry;
  document.getElementById('vId').value     = e.id;
  document.getElementById('vSystem').value = e.system_name||'';
  document.getElementById('vUrl').value    = e.url||'';
  document.getElementById('vUsername').value=e.username||'';
  document.getElementById('vCat').value    = e.category||'general';
  document.getElementById('vFav').checked  = !!parseInt(e.is_favourite||0,10);
  document.getElementById('vPass').required=false;
  const pr=document.getElementById('vPassReq'); if(pr) pr.style.display='none';
  document.querySelector('#vaultForm .edit-hint')?.classList.remove('d-none');
  document.getElementById('vaultModalTitle').innerHTML='<i class="bi bi-pencil text-primary me-2"></i><?= h(__("vault_edit")) ?>';
  const mp=document.querySelector('#vaultForm [name=master_password]'); if(mp) mp.required=false;
  new bootstrap.Modal(document.getElementById('modalVault')).show();
}
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
