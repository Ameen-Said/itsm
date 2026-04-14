<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requirePermission('documents','view');
$pageTitle  = __('doc_title');
$breadcrumb = [['label'=>__('doc_title'),'active'=>true]];

$folderId = !empty($_GET['folder']) ? (int)$_GET['folder'] : 0;
$search   = trim($_GET['q']??'');
$catF     = $_GET['cat']??'';
$page     = max(1,(int)($_GET['page']??1));

// Get folders
$folders = $db->query("SELECT f.*,(SELECT COUNT(*) FROM documents d WHERE d.folder_id=f.id) as doc_count FROM document_folders f ORDER BY f.name")->fetchAll();

// Documents query
$where=[]; $params=[];
if ($folderId > 0) { $where[]="d.folder_id=?"; $params[]=$folderId; }
if ($search) { $where[]="(d.title LIKE ? OR d.description LIKE ?)"; $s="%$search%"; $params[]=$s; $params[]=$s; }
if ($catF)   { $where[]="d.category=?"; $params[]=$catF; }
$w=$where?'WHERE '.implode(' AND ',$where):'';

$sql="SELECT d.*, u.full_name as uploader, f.name as folder_name
      FROM documents d
      LEFT JOIN users u ON d.uploaded_by=u.id
      LEFT JOIN document_folders f ON d.folder_id=f.id
      $w ORDER BY d.created_at DESC";
$pag = paginate($db,$sql,$params,$page);
$filter='?'.http_build_query(array_filter(['folder'=>$folderId,'q'=>$search,'cat'=>$catF]));

$totalSize = (int)$db->query("SELECT COALESCE(SUM(file_size),0) FROM documents")->fetchColumn();
$cats = ['contract'=>__('doc_category_contract'),'manual'=>__('doc_category_manual'),'invoice'=>__('doc_category_invoice'),'policy'=>__('doc_category_policy'),'other'=>__('doc_category_other')];
$users   = $db->query("SELECT id,full_name FROM users WHERE status='active' ORDER BY full_name")->fetchAll();
$assets  = $db->query("SELECT id,asset_code,name FROM assets ORDER BY name LIMIT 200")->fetchAll();
$vendors = $db->query("SELECT id,name FROM vendors ORDER BY name")->fetchAll();

include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title"><div class="title-icon"><i class="bi bi-folder2"></i></div> <?= h(__('doc_title')) ?></h1>
  <div class="page-actions">
    <?php if($auth->can('documents','add')): ?>
    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalFolder"><i class="bi bi-folder-plus me-1"></i><?= h(__('doc_new_folder')) ?></button>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalUpload"><i class="bi bi-upload me-1"></i><?= h(__('doc_upload')) ?></button>
    <?php endif; ?>
  </div>
</div>

<!-- Stats row -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="stat-card blue"><div class="stat-icon-box blue"><i class="bi bi-file-earmark"></i></div><div class="stat-num"><?= $pag['total'] ?></div><div class="stat-label"><?= h(__('doc_title')) ?></div></div>
  </div>
  <div class="col-md-3">
    <div class="stat-card violet"><div class="stat-icon-box violet"><i class="bi bi-folder"></i></div><div class="stat-num"><?= count($folders) ?></div><div class="stat-label"><?= h(__('field_folder')) ?>s</div></div>
  </div>
  <div class="col-md-3">
    <div class="stat-card green"><div class="stat-icon-box green"><i class="bi bi-hdd"></i></div><div class="stat-num" style="font-size:18px;"><?= formatBytes($totalSize) ?></div><div class="stat-label"><?= h(__('doc_storage')) ?></div></div>
  </div>
</div>

<div class="row g-4">
  <!-- Folders sidebar -->
  <div class="col-md-3">
    <div class="card">
      <div class="card-header"><i class="bi bi-folder text-warning"></i> <?= h(__('field_folder')) ?>s</div>
      <div class="list-group list-group-flush">
        <a href="<?= APP_URL ?>/pages/documents.php" class="list-group-item list-group-item-action d-flex justify-content-between <?= !$folderId?'active':'' ?>" style="font-size:13px;">
          <span><i class="bi bi-folder2-open me-2"></i>All Documents</span>
          <span class="badge bg-<?= !$folderId?'white text-primary':'secondary' ?>"><?= $pag['total'] ?></span>
        </a>
        <?php foreach($folders as $f): ?>
        <a href="<?= APP_URL ?>/pages/documents.php?folder=<?=$f['id']?>" class="list-group-item list-group-item-action d-flex justify-content-between <?= $folderId==$f['id']?'active':'' ?>" style="font-size:13px;">
          <span><i class="bi bi-folder me-2"></i><?= h($f['name']) ?></span>
          <span class="badge bg-<?= $folderId==$f['id']?'white text-primary':'secondary' ?>"><?= $f['doc_count'] ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Documents list -->
  <div class="col-md-9">
    <!-- Filters -->
    <div class="card mb-3"><div class="card-body py-2">
      <form method="get" class="row g-2 align-items-end">
        <?php if($folderId): ?><input type="hidden" name="folder" value="<?=$folderId?>"><?php endif;?>
        <div class="col-md-5"><input type="text" name="q" class="form-control form-control-sm" placeholder="<?= h(__('doc_search_ph')) ?>" value="<?= h($search) ?>"></div>
        <div class="col-md-3"><select name="cat" class="form-select form-select-sm"><option value=""><?= h(__('doc_all_cats')) ?></option><?php foreach($cats as $k=>$v): ?><option value="<?=h($k)?>" <?=$catF===$k?'selected':''?>><?=h($v)?></option><?php endforeach;?></select></div>
        <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i><?= h(__('btn_filter')) ?></button> <a href="<?= APP_URL ?>/pages/documents.php<?=$folderId?"?folder=$folderId":''?>" class="btn btn-sm btn-outline-secondary"><?= h(__('btn_reset')) ?></a></div>
      </form>
    </div></div>

    <div class="table-card">
      <div class="table-toolbar">
        <div class="table-toolbar-left">
          <span class="text-muted small"><?= number_format($pag['total']) ?> files</span>
          <?php if($auth->can('documents','delete')): ?>
          <button class="btn btn-xs btn-outline-danger ms-2" onclick="if(confirm('Delete selected?'))bulkAction('delete_documents')"><?= h(__('btn_delete')) ?> selected</button>
          <?php endif; ?>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table">
          <thead><tr>
            <th style="width:34px;"><input type="checkbox" class="form-check-input" id="selectAll"></th>
            <th><?= h(__('field_title')) ?></th>
            <th><?= h(__('field_category')) ?></th>
            <th><?= h(__('doc_folder')) ?></th>
            <th><?= h(__('field_size')) ?></th>
            <th><?= h(__('field_uploaded_by')) ?></th>
            <th><?= h(__('field_created_at')) ?></th>
            <th class="text-end"><?= h(__('field_actions')) ?></th>
          </tr></thead>
          <tbody>
            <?php if(empty($pag['records'])): ?><tr><td colspan="8" class="text-center py-4 text-muted"><?= h(__('msg_no_records')) ?></td></tr><?php endif;?>
            <?php foreach($pag['records'] as $doc):
              $ext = strtolower(pathinfo($doc['original_name'],PATHINFO_EXTENSION));
              $iconMap=['pdf'=>'bi-file-pdf text-danger','doc'=>'bi-file-word text-primary','docx'=>'bi-file-word text-primary','xls'=>'bi-file-excel text-success','xlsx'=>'bi-file-excel text-success','png'=>'bi-file-image text-info','jpg'=>'bi-file-image text-info','jpeg'=>'bi-file-image text-info'];
              $icon=$iconMap[$ext]??'bi-file-earmark text-secondary';
            ?>
            <tr>
              <td><input type="checkbox" class="form-check-input row-cb" value="<?=$doc['id']?>"></td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <i class="bi <?=$icon?> fs-5 flex-shrink-0"></i>
                  <div>
                    <div class="fw-semibold" style="font-size:13px;"><?= h($doc['title']) ?></div>
                    <div class="mono text-muted" style="font-size:11px;"><?= h($doc['original_name']) ?></div>
                  </div>
                </div>
              </td>
              <td><span class="badge bg-secondary-subtle text-secondary"><?= h($cats[$doc['category']]??$doc['category']) ?></span></td>
              <td style="font-size:13px;"><?= h($doc['folder_name']??'—') ?></td>
              <td style="font-size:12px;"><?= formatBytes((int)$doc['file_size']) ?></td>
              <td style="font-size:12px;"><?= h($doc['uploader']??'—') ?></td>
              <td style="font-size:12px;"><?= formatDate($doc['created_at']) ?></td>
              <td class="text-end">
                <a href="<?= APP_URL ?>/actions/download.php?id=<?=$doc['id']?>&preview=1" target="_blank" class="btn btn-icon btn-sm btn-outline-info" title="Preview"><i class="bi bi-eye"></i></a>
                <a href="<?= APP_URL ?>/actions/download.php?id=<?=$doc['id']?>" class="btn btn-icon btn-sm btn-outline-secondary ms-1" title="<?= h(__('btn_download')) ?>"><i class="bi bi-download"></i></a>
                <?php if($auth->can('documents','delete')): ?>
                <button class="btn btn-icon btn-sm btn-outline-danger ms-1" onclick="confirmDelete('<?= APP_URL ?>/actions/document_delete.php?id=<?=$doc['id']?>&csrf_token=<?=h($auth->generateCsrfToken())?>')" title="<?= h(__('btn_delete')) ?>"><i class="bi bi-trash"></i></button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="table-footer"><span><?= $pag['total'] ?> files</span><?= renderPagination($pag,APP_URL.'/pages/documents.php'.$filter)?></div>
    </div>
  </div>
</div>

<!-- Upload Modal -->
<?php if ($auth->can('documents','add')): ?>
<div class="modal fade" id="modalUpload" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="bi bi-upload text-primary me-2"></i><?= h(__('doc_upload')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form action="<?= APP_URL ?>/actions/document_upload.php" method="post" enctype="multipart/form-data">
        <?= $auth->csrfField() ?>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12"><label class="form-label"><?= h(__('field_title')) ?> <span class="text-danger">*</span></label><input type="text" name="title" class="form-control" required></div>
            <div class="col-md-6">
              <label class="form-label"><?= h(__('doc_folder')) ?></label>
              <select name="folder_id" class="form-select">
                <option value=""><?= h(__('doc_all_cats')) ?></option>
                <?php foreach($folders as $f): ?><option value="<?=$f['id']?>" <?=$folderId==$f['id']?'selected':''?>><?=h($f['name'])?></option><?php endforeach;?>
              </select>
            </div>
            <div class="col-md-6"><label class="form-label"><?= h(__('field_category')) ?></label><select name="category" class="form-select"><?php foreach($cats as $k=>$v): ?><option value="<?=h($k)?>"><?=h($v)?></option><?php endforeach;?></select></div>
            <div class="col-12"><label class="form-label">File <span class="text-danger">*</span></label><input type="file" name="file" class="form-control" required></div>
            <div class="col-12"><label class="form-label"><?= h(__('field_description')) ?></label><textarea name="description" class="form-control" rows="2"></textarea></div>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= h(__('btn_cancel')) ?></button><button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i><?= h(__('btn_upload')) ?></button></div>
      </form>
    </div>
  </div>
</div>

<!-- New Folder Modal -->
<div class="modal fade" id="modalFolder" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="bi bi-folder-plus text-warning me-2"></i><?= h(__('doc_new_folder')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form data-ajax action="<?= APP_URL ?>/actions/folder_save.php" method="post">
        <?= $auth->csrfField() ?>
        <div class="modal-body">
          <label class="form-label"><?= h(__('field_name')) ?> <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" required>
          <div class="mt-3"><label class="form-label"><?= h(__('field_description')) ?></label><textarea name="description" class="form-control" rows="2"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= h(__('btn_cancel')) ?></button><button type="submit" class="btn btn-warning"><i class="bi bi-folder-plus me-1"></i>Create</button></div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include APP_ROOT . '/includes/footer.php'; ?>
