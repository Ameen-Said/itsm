<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requirePermission('audit','view');
$pageTitle  = __('audit_title');
$breadcrumb = [['label'=>__('audit_title'),'active'=>true]];

$module   = $_GET['module'] ??'';
$action   = $_GET['action'] ??'';
$uid      = (int)($_GET['user']??0);
$dateFrom = $_GET['from'] ?? date('Y-m-d',strtotime('-30 days'));
$dateTo   = $_GET['to']   ?? date('Y-m-d');
$page     = max(1,(int)($_GET['page']??1));

$where=[]; $params=[];
if ($module){ $where[]="al.module=?"; $params[]=$module; }
if ($action){ $where[]="al.action=?"; $params[]=$action; }
if ($uid)   { $where[]="al.user_id=?"; $params[]=$uid; }
if ($dateFrom){ $where[]="DATE(al.created_at)>=?"; $params[]=$dateFrom; }
if ($dateTo)  { $where[]="DATE(al.created_at)<=?"; $params[]=$dateTo; }
$w=$where?'WHERE '.implode(' AND ',$where):'';

$baseQ="SELECT al.*, u.full_name, u.username FROM audit_logs al LEFT JOIN users u ON al.user_id=u.id $w ORDER BY al.created_at DESC";
$pag = paginate($db,$baseQ,$params,$page,50);

$modules = $db->query("SELECT DISTINCT module FROM audit_logs ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);
$actions = $db->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
$users   = $db->query("SELECT id,full_name FROM users ORDER BY full_name")->fetchAll();

$actionColors=['login'=>'success','login_failed'=>'danger','logout'=>'secondary','create'=>'primary','edit'=>'info','delete'=>'danger','reveal_password'=>'warning','assign'=>'info','upload'=>'success','bulk_delete'=>'danger'];

include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-journal-text"></i> <?= h(__('audit_title')) ?></h1>
  <button class="btn btn-sm btn-outline-secondary" onclick="exportTable('auditTbl','audit_log')">
    <i class="bi bi-download me-1"></i><?= h(__('btn_export')) ?>
  </button>
</div>

<div class="card mb-3"><div class="card-body py-2">
  <form method="get" class="row g-2 align-items-end">
    <div class="col-md-2"><label class="form-label" style="font-size:11px;"><?= h(__('field_module')) ?></label>
      <select name="module" class="form-select form-select-sm"><option value=""><?= h(__('audit_all_modules')) ?></option>
        <?php foreach($modules as $m): ?><option value="<?=h($m)?>" <?=$module===$m?'selected':''?>><?=h(ucfirst($m))?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2"><label class="form-label" style="font-size:11px;"><?= h(__('field_action')) ?></label>
      <select name="action" class="form-select form-select-sm"><option value=""><?= h(__('audit_all_actions')) ?></option>
        <?php foreach($actions as $a): ?><option value="<?=h($a)?>" <?=$action===$a?'selected':''?>><?=h(str_replace('_',' ',ucfirst($a)))?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2"><label class="form-label" style="font-size:11px;"><?= h(__('field_name')) ?></label>
      <select name="user" class="form-select form-select-sm"><option value=""><?= h(__('audit_all_users')) ?></option>
        <?php foreach($users as $u): ?><option value="<?=$u['id']?>" <?=$uid==$u['id']?'selected':''?>><?=h($u['full_name'])?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2"><label class="form-label" style="font-size:11px;"><?= h(__('audit_from')) ?></label><input type="date" name="from" class="form-control form-control-sm" value="<?=h($dateFrom)?>"></div>
    <div class="col-md-2"><label class="form-label" style="font-size:11px;"><?= h(__('audit_to')) ?></label><input type="date" name="to" class="form-control form-control-sm" value="<?=h($dateTo)?>"></div>
    <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i><?= h(__('btn_filter')) ?></button><a href="<?=APP_URL?>/pages/audit.php" class="btn btn-sm btn-outline-secondary ms-1"><?= h(__('btn_reset')) ?></a></div>
  </form>
</div></div>

<div class="table-card">
  <div class="table-toolbar"><span class="text-muted small">Showing <strong><?=count($pag['records'])?></strong> of <strong><?=number_format($pag['total'])?></strong> entries</span></div>
  <div class="table-responsive">
    <table class="table table-hover table-sm" id="auditTbl">
      <thead><tr>
        <th><?= h(__('field_timestamp')) ?></th>
        <th><?= h(__('field_name')) ?></th>
        <th><?= h(__('field_action')) ?></th>
        <th><?= h(__('field_module')) ?></th>
        <th><?= h(__('field_ip')) ?></th>
        <th><?= h(__('field_details')) ?></th>
      </tr></thead>
      <tbody>
        <?php if(empty($pag['records'])): ?><tr><td colspan="6" class="text-center py-4 text-muted"><?=h(__('msg_no_records'))?></td></tr><?php endif; ?>
        <?php foreach($pag['records'] as $log):
          $cls=$actionColors[$log['action']]??'secondary';
        ?>
        <tr>
          <td class="mono" style="font-size:11.5px;white-space:nowrap;"><?=formatDate($log['created_at'],'d M Y H:i:s')?></td>
          <td><div class="fw-semibold" style="font-size:13px;"><?=h($log['full_name']??'System')?></div><div class="text-muted" style="font-size:11px;"><?=h($log['username']??'')?></div></td>
          <td><span class="badge bg-<?=$cls?>-subtle text-<?=$cls?>"><?=h(str_replace('_',' ',ucfirst($log['action'])))?></span></td>
          <td><span class="badge bg-secondary-subtle text-secondary"><?=h($log['module'])?></span></td>
          <td class="mono" style="font-size:11.5px;"><?=h($log['ip_address']??'—')?></td>
          <td><?php if($log['new_values']): ?><button class="btn btn-xs btn-outline-secondary" onclick='showDetails(<?=h($log["new_values"])?>)' style="font-size:11px;padding:1px 6px;"><i class="bi bi-eye me-1"></i>View</button><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="table-footer">
    <span><?=$pag['total']?> entries</span>
    <?=renderPagination($pag,APP_URL.'/pages/audit.php?'.http_build_query(array_filter(['module'=>$module,'action'=>$action,'user'=>$uid,'from'=>$dateFrom,'to'=>$dateTo])))?>
  </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-info-circle text-primary me-2"></i><?= h(__('field_details')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><pre id="detailContent" class="mono" style="font-size:12px;background:var(--surface-2);padding:12px;border-radius:6px;max-height:400px;overflow-y:auto;"></pre></div>
  </div></div>
</div>

<script>
function showDetails(data) {
  document.getElementById('detailContent').textContent = JSON.stringify(data, null, 2);
  new bootstrap.Modal(document.getElementById('detailModal')).show();
}
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
