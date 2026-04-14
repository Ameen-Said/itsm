<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requirePermission('email', 'view');

$pageTitle  = 'Email Management';
$breadcrumb = [['label' => 'Email', 'active' => true]];

$tab  = $_GET['tab'] ?? 'logs';
$page = max(1, (int)($_GET['page'] ?? 1));

// Email logs
$logQuery = "SELECT * FROM email_logs ORDER BY sent_at DESC";
$pag = paginate($db, $logQuery, [], $page, 50);

// Email configs
$configs = $db->query("SELECT * FROM email_configs ORDER BY is_default DESC, name ASC")->fetchAll();

// Stats
$stats = [
    'total' => $db->query("SELECT COUNT(*) FROM email_logs")->fetchColumn(),
    'sent'  => $db->query("SELECT COUNT(*) FROM email_logs WHERE status='sent'")->fetchColumn(),
    'failed'=> $db->query("SELECT COUNT(*) FROM email_logs WHERE status='failed'")->fetchColumn(),
];

include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-envelope-fill"></i> Email Management</h1>
  <?php if ($auth->can('email','add')): ?>
  <div class="d-flex gap-2">
    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalSendTest"><i class="bi bi-send me-1"></i>Send Test Email</button>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddConfig"><i class="bi bi-plus-lg me-1"></i>Add SMTP Config</button>
  </div>
  <?php endif; ?>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-4"><div class="stat-card primary"><div class="stat-icon primary"><i class="bi bi-envelope"></i></div><div class="stat-number"><?= number_format($stats['total']) ?></div><div class="stat-label">Total Emails Logged</div></div></div>
  <div class="col-6 col-md-4"><div class="stat-card success"><div class="stat-icon success"><i class="bi bi-envelope-check"></i></div><div class="stat-number"><?= number_format($stats['sent']) ?></div><div class="stat-label">Successfully Sent</div></div></div>
  <div class="col-6 col-md-4"><div class="stat-card danger"><div class="stat-icon danger"><i class="bi bi-envelope-x"></i></div><div class="stat-number"><?= number_format($stats['failed']) ?></div><div class="stat-label">Failed</div></div></div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4">
  <li class="nav-item"><a class="nav-link <?= $tab==='logs'?'active':'' ?>" href="?tab=logs"><i class="bi bi-journal-text me-1"></i>Send Log</a></li>
  <li class="nav-item"><a class="nav-link <?= $tab==='config'?'active':'' ?>" href="?tab=config"><i class="bi bi-gear me-1"></i>SMTP Configurations</a></li>
  <li class="nav-item"><a class="nav-link <?= $tab==='alerts'?'active':'' ?>" href="?tab=alerts"><i class="bi bi-bell me-1"></i>Alert Rules</a></li>
</ul>

<?php if ($tab === 'logs'): ?>
<div class="table-card">
  <div class="table-toolbar"><span class="text-muted small"><strong><?= $pag['total'] ?></strong> email log entries</span>
    <?php if ($auth->can('reports','export')): ?>
    <button class="btn btn-sm btn-outline-secondary" onclick="exportTable('emailTable','email_log')"><i class="bi bi-download me-1"></i>Export</button>
    <?php endif; ?>
  </div>
  <div class="table-responsive">
    <table class="table table-hover table-sm" id="emailTable">
      <thead><tr><th>To</th><th>Subject</th><th>Status</th><th>Date</th><th>Details</th></tr></thead>
      <tbody>
        <?php if (empty($pag['records'])): ?>
        <tr><td colspan="5" class="text-center py-4 text-muted"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No emails logged yet</td></tr>
        <?php endif; ?>
        <?php foreach ($pag['records'] as $log): ?>
        <tr>
          <td style="font-size:13px;"><?= h($log['to_email']) ?></td>
          <td class="fw-semibold" style="font-size:13px;"><?= h($log['subject']) ?></td>
          <td>
            <span class="badge bg-<?= $log['status']==='sent'?'success':($log['status']==='failed'?'danger':'warning') ?>">
              <?= ucfirst($log['status']) ?>
            </span>
          </td>
          <td class="mono" style="font-size:11px;"><?= formatDate($log['sent_at'],'d M Y H:i') ?></td>
          <td>
            <?php if ($log['error_msg']): ?>
            <button class="btn btn-xs btn-outline-danger" onclick="alert(<?= j($log['error_msg']) ?>)" style="font-size:11px;padding:1px 6px;"><i class="bi bi-exclamation-triangle me-1"></i>Error</button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="table-footer">
    <span><?= $pag['total'] ?> entries</span>
    <?= renderPagination($pag, APP_URL.'/pages/email.php?tab=logs') ?>
  </div>
</div>

<?php elseif ($tab === 'config'): ?>
<div class="row g-3">
  <?php if (empty($configs)): ?>
  <div class="col-12"><div class="text-center py-5 text-muted"><i class="bi bi-envelope-slash fs-1 d-block mb-2"></i>No SMTP configurations. Add one to enable email.</div></div>
  <?php endif; ?>
  <?php foreach ($configs as $cfg): ?>
  <div class="col-md-6 col-lg-4">
    <div class="card h-100 <?= $cfg['is_default']?'border-primary':'' ?>">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <h6 class="fw-bold mb-0"><?= h($cfg['name']) ?></h6>
          <?php if ($cfg['is_default']): ?><span class="badge bg-primary">Default</span><?php endif; ?>
        </div>
        <div class="mb-2" style="font-size:13px;">
          <div class="text-muted mb-1" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">SMTP Server</div>
          <div class="mono"><?= h($cfg['smtp_host']) ?>:<?= h($cfg['smtp_port']) ?></div>
        </div>
        <div class="mb-2" style="font-size:13px;">
          <div class="text-muted mb-1" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Username</div>
          <div><?= h($cfg['smtp_user']) ?></div>
        </div>
        <div style="font-size:13px;">
          <div class="text-muted mb-1" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">From</div>
          <div><?= h($cfg['from_name']) ?> &lt;<?= h($cfg['from_email']) ?>&gt;</div>
        </div>
        <div class="d-flex gap-2 mt-3">
          <button class="btn btn-sm btn-outline-secondary" onclick="editEmailConfig(<?= $cfg['id'] ?>)"><i class="bi bi-pencil me-1"></i>Edit</button>
          <?php if (!$cfg['is_default']): ?>
          <button class="btn btn-sm btn-outline-primary" onclick="setDefaultConfig(<?= $cfg['id'] ?>)"><i class="bi bi-star me-1"></i>Set Default</button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php elseif ($tab === 'alerts'): ?>
<div class="card">
  <div class="card-header"><i class="bi bi-bell-fill text-warning"></i> Automated Alert Rules</div>
  <div class="card-body">
    <div class="alert alert-info d-flex gap-2 align-items-start">
      <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
      <div>Alerts are sent automatically when assets or licenses are within the configured threshold. Configure email first, then enable alerts below.</div>
    </div>
    <form data-ajax action="<?= APP_URL ?>/actions/email_alerts_save.php" method="post">
      <?= $auth->csrfField() ?>
      <div class="row g-3">
        <div class="col-md-6">
          <div class="card border-warning">
            <div class="card-body">
              <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="warranty_alerts" id="warrantyAlerts" checked>
                <label class="form-check-label fw-semibold" for="warrantyAlerts"><i class="bi bi-shield-exclamation text-warning me-1"></i>Warranty Expiry Alerts</label>
              </div>
              <label class="form-label">Alert X days before expiry</label>
              <div class="input-group input-group-sm"><input type="number" name="warranty_days" class="form-control" value="30" min="1" max="365"><span class="input-group-text">days</span></div>
              <label class="form-label mt-2">Notify Email(s)</label>
              <input type="text" name="warranty_email" class="form-control form-control-sm" placeholder="admin@company.com, it@company.com">
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card border-danger">
            <div class="card-body">
              <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="license_alerts" id="licenseAlerts" checked>
                <label class="form-check-label fw-semibold" for="licenseAlerts"><i class="bi bi-tags text-danger me-1"></i>License Expiry Alerts</label>
              </div>
              <label class="form-label">Alert X days before expiry</label>
              <div class="input-group input-group-sm"><input type="number" name="license_days" class="form-control" value="30" min="1" max="365"><span class="input-group-text">days</span></div>
              <label class="form-label mt-2">Notify Email(s)</label>
              <input type="text" name="license_email" class="form-control form-control-sm" placeholder="admin@company.com">
            </div>
          </div>
        </div>
      </div>
      <div class="mt-3">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Alert Rules</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Add SMTP Config Modal -->
<div class="modal fade" id="modalAddConfig" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="bi bi-server text-primary me-2"></i>SMTP Configuration</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form data-ajax action="<?= APP_URL ?>/actions/email_config_save.php" method="post">
        <?= $auth->csrfField() ?>
        <input type="hidden" name="id" id="cfgId" value="">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12"><label class="form-label">Config Name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required placeholder="e.g. Office 365 SMTP"></div>
            <div class="col-md-8"><label class="form-label">SMTP Host <span class="text-danger">*</span></label><input type="text" name="smtp_host" class="form-control mono" required placeholder="smtp.office365.com"></div>
            <div class="col-md-4"><label class="form-label">Port</label><input type="number" name="smtp_port" class="form-control" value="587"></div>
            <div class="col-12"><label class="form-label">Username</label><input type="text" name="smtp_user" class="form-control" autocomplete="off"></div>
            <div class="col-12">
              <label class="form-label">Password</label>
              <div class="input-group">
                <input type="password" name="smtp_pass" id="smtpPass" class="form-control" autocomplete="new-password">
                <button type="button" class="btn btn-outline-secondary" data-toggle-password="#smtpPass"><i class="bi bi-eye"></i></button>
              </div>
            </div>
            <div class="col-md-6"><label class="form-label">From Email</label><input type="email" name="from_email" class="form-control" placeholder="noreply@company.com"></div>
            <div class="col-md-6"><label class="form-label">From Name</label><input type="text" name="from_name" class="form-control" placeholder="IT Manager Pro"></div>
            <div class="col-12">
              <div class="form-check form-switch">
                <input type="checkbox" class="form-check-input" name="is_default" id="isDefault" value="1">
                <label class="form-check-label" for="isDefault">Set as default SMTP</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Config</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Send Test Email Modal -->
<div class="modal fade" id="modalSendTest" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="bi bi-send text-primary me-2"></i>Send Test Email</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form data-ajax action="<?= APP_URL ?>/actions/email_send_test.php" method="post">
        <?= $auth->csrfField() ?>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Send To <span class="text-danger">*</span></label><input type="email" name="to_email" class="form-control" required placeholder="test@example.com"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Send</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
async function editEmailConfig(id) {
  const res = await fetch('<?= APP_URL ?>/api/email_config_get.php?id=' + id);
  const data = await res.json();
  if (!data.success) return;
  const cfg = data.config;
  const form = document.querySelector('#modalAddConfig form');
  ['id','name','smtp_host','smtp_port','smtp_user','from_email','from_name'].forEach(f => {
    const el = form.querySelector(`[name=${f}]`)||document.getElementById('cfgId');
    if (el) el.value = cfg[f] || '';
  });
  document.getElementById('cfgId').value = cfg.id;
  form.querySelector('[name=is_default]').checked = cfg.is_default == 1;
  new bootstrap.Modal(document.getElementById('modalAddConfig')).show();
}

async function setDefaultConfig(id) {
  const res = await api('<?= APP_URL ?>/actions/email_config_default.php', { id });
  if (res.success) { Toast.show('Default config updated.','success'); setTimeout(()=>location.reload(),800); }
}
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
