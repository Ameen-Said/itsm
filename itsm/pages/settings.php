<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requirePermission('settings','view');
$pageTitle  = __('set_title');
$breadcrumb = [['label'=>__('set_title'),'active'=>true]];

$s = [
  'company_name'        => getSetting('company_name','My Company'),
  'company_logo'        => getSetting('company_logo',''),
  'date_format'         => getSetting('date_format','d M Y'),
  'currency'            => getSetting('currency','USD'),
  'timezone'            => getSetting('timezone','UTC'),
  'session_timeout'     => getSetting('session_timeout','60'),
  'warranty_alert_days' => getSetting('warranty_alert_days','30'),
  'license_alert_days'  => getSetting('license_alert_days','30'),
  'pagination_limit'    => getSetting('pagination_limit','25'),
  'maintenance_mode'    => getSetting('maintenance_mode','0'),
  'default_language'    => getSetting('default_language','en'),
];

$dbTables=['assets','users','licenses','documents','vendors','audit_logs','vault_entries','notifications'];
$dbStats=[];
foreach($dbTables as $t){try{$dbStats[$t]=$db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();}catch(Throwable $e){$dbStats[$t]='N/A';}}

include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title"><div class="title-icon"><i class="bi bi-gear"></i></div> <?= h(__('set_title')) ?></h1>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="card mb-4">
      <div class="card-header"><i class="bi bi-sliders text-primary"></i> <?= h(__('set_general')) ?></div>
      <div class="card-body">
        <!-- NOTE: This form uses standard POST redirect (not AJAX) to avoid empty page bug -->
        <form action="<?= APP_URL ?>/actions/settings_save.php" method="post" enctype="multipart/form-data" onsubmit="showSavingState(this)">
          <?= $auth->csrfField() ?>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label"><?= h(__('set_company')) ?></label>
              <input type="text" name="company_name" class="form-control" value="<?= h($s['company_name']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= h(__('set_logo')) ?></label>
              <div class="d-flex align-items-center gap-2">
                <?php if ($s['company_logo'] && file_exists(UPLOAD_DIR.'logos/'.$s['company_logo'])): ?>
                <img src="<?= APP_URL ?>/uploads/logos/<?= h($s['company_logo']) ?>" style="height:34px;border-radius:6px;border:1px solid var(--border);">
                <?php endif; ?>
                <input type="file" name="company_logo" class="form-control" accept="image/*">
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= h(__('set_date_format')) ?></label>
              <select name="date_format" class="form-select">
                <?php foreach(['d M Y'=>'01 Jan 2024','Y-m-d'=>'2024-01-01','d/m/Y'=>'01/01/2024','m/d/Y'=>'01/01/2024'] as $f=>$ex): ?>
                <option value="<?= h($f) ?>" <?= $s['date_format']===$f?'selected':'' ?>><?= h($ex) ?> (<?= h($f) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= h(__('set_currency')) ?></label>
              <select name="currency" class="form-select">
                <?php foreach(['USD'=>'USD ($)','EUR'=>'EUR (€)','GBP'=>'GBP (£)','AED'=>'AED (د.إ)','SAR'=>'SAR (ر.س)','EGP'=>'EGP (ج.م)','KWD'=>'KWD (د.ك)'] as $k=>$v): ?>
                <option value="<?= h($k) ?>" <?= $s['currency']===$k?'selected':'' ?>><?= h($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= h(__('set_timezone')) ?></label>
              <select name="timezone" class="form-select">
                <?php foreach(['UTC','Asia/Dubai','Asia/Riyadh','Asia/Kuwait','Asia/Qatar','Africa/Cairo','Europe/London','America/New_York'] as $tz): ?>
                <option value="<?= h($tz) ?>" <?= $s['timezone']===$tz?'selected':'' ?>><?= h($tz) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= h(__('set_session')) ?></label>
              <div class="input-group"><input type="number" name="session_timeout" class="form-control" value="<?= h($s['session_timeout']) ?>" min="5" max="1440"><span class="input-group-text">min</span></div>
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= h(__('set_per_page')) ?></label>
              <input type="number" name="pagination_limit" class="form-control" value="<?= h($s['pagination_limit']) ?>" min="5" max="200">
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= h(__('field_language')) ?></label>
              <select name="default_language" class="form-select">
                <option value="en" <?= $s['default_language']==='en'?'selected':'' ?>><?= h(__('lang_en')) ?></option>
                <option value="ar" <?= $s['default_language']==='ar'?'selected':'' ?>><?= h(__('lang_ar')) ?></option>
              </select>
            </div>
          </div>
          <hr class="my-4">
          <h6 class="fw-bold mb-3"><i class="bi bi-bell text-warning me-2"></i><?= h(__('set_alerts')) ?></h6>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label"><?= h(__('set_warranty_days')) ?></label>
              <div class="input-group"><input type="number" name="warranty_alert_days" class="form-control" value="<?= h($s['warranty_alert_days']) ?>" min="1" max="365"><span class="input-group-text"><?= CURRENT_LANG==='ar'?'يوم':'days' ?></span></div>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= h(__('set_license_days')) ?></label>
              <div class="input-group"><input type="number" name="license_alert_days" class="form-control" value="<?= h($s['license_alert_days']) ?>" min="1" max="365"><span class="input-group-text"><?= CURRENT_LANG==='ar'?'يوم':'days' ?></span></div>
            </div>
          </div>
          <hr class="my-4">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="maintenance_mode" id="mainMode" value="1" <?= $s['maintenance_mode']==='1'?'checked':'' ?>>
            <label class="form-check-label" for="mainMode">
              <strong><?= h(__('set_maintenance')) ?></strong>
              <div class="text-muted" style="font-size:12px;"><?= h(__('set_maintenance_hint')) ?></div>
            </label>
          </div>
          <?php if ($auth->can('settings','edit')): ?>
          <div class="mt-4">
            <button type="submit" class="btn btn-primary" id="saveSettingsBtn"><i class="bi bi-save me-1"></i><?= h(__('btn_save')) ?></button>
          </div>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- Backup -->
    <div class="card">
      <div class="card-header"><i class="bi bi-database text-success"></i> <?= h(__('set_backup')) ?></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="p-3 rounded border" style="background:var(--surface-2);">
              <h6 class="fw-bold mb-2"><i class="bi bi-download text-success me-1"></i><?= h(__('set_create_backup')) ?></h6>
              <p class="text-muted small mb-3">Create a full SQL export of the database.</p>
              <a href="<?= APP_URL ?>/actions/backup_create.php?csrf_token=<?= h($auth->generateCsrfToken()) ?>" class="btn btn-sm btn-success"><i class="bi bi-download me-1"></i>Download Backup</a>
            </div>
          </div>
          <div class="col-md-6">
            <div class="p-3 rounded border" style="background:var(--surface-2);">
              <h6 class="fw-bold mb-2"><i class="bi bi-upload text-warning me-1"></i><?= h(__('set_restore')) ?></h6>
              <form action="<?= APP_URL ?>/actions/backup_restore.php" method="post" enctype="multipart/form-data">
                <?= $auth->csrfField() ?>
                <div class="input-group input-group-sm">
                  <input type="file" name="sql_file" class="form-control" accept=".sql">
                  <button type="submit" class="btn btn-warning" onclick="return confirm('WARNING: This will overwrite all data!')"><i class="bi bi-upload"></i></button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Right sidebar -->
  <div class="col-lg-4">
    <div class="card mb-4">
      <div class="card-header"><i class="bi bi-database text-primary"></i> <?= h(__('set_db_stats')) ?></div>
      <div class="card-body p-0">
        <?php foreach($dbStats as $t=>$c): ?>
        <div class="d-flex justify-content-between px-3 py-2 border-bottom">
          <span class="text-capitalize" style="font-size:13px;"><?= h(str_replace('_',' ',$t)) ?></span>
          <span class="badge bg-primary-subtle text-primary mono"><?= number_format((int)$c) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card mb-4">
      <div class="card-header"><i class="bi bi-lightning text-warning"></i> <?= h(__('set_quick_actions')) ?></div>
      <div class="card-body d-flex flex-column gap-2">
        <a href="<?= APP_URL ?>/actions/clear_audit.php?csrf_token=<?= h($auth->generateCsrfToken()) ?>" class="btn btn-outline-secondary btn-sm" onclick="return confirm('Clear audit logs older than 90 days?')"><i class="bi bi-trash me-1"></i>Clear Old Audit Logs</a>
        <a href="<?= APP_URL ?>/actions/clear_notifications.php?csrf_token=<?= h($auth->generateCsrfToken()) ?>" class="btn btn-outline-secondary btn-sm" onclick="return confirm('Clear all read notifications?')"><i class="bi bi-bell-slash me-1"></i>Clear Read Notifications</a>
        <a href="<?= APP_URL ?>/actions/send_expiry_alerts.php?csrf_token=<?= h($auth->generateCsrfToken()) ?>" class="btn btn-outline-warning btn-sm"><i class="bi bi-bell me-1"></i>Send Expiry Alerts</a>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><i class="bi bi-info-circle text-info"></i> System Info</div>
      <div class="card-body p-0">
        <?php $info=['PHP Version'=>phpversion(),'App Version'=>APP_VERSION,'MySQL'=>$db->query("SELECT VERSION()")->fetchColumn(),'Upload Max'=>ini_get('upload_max_filesize'),'Memory Limit'=>ini_get('memory_limit')];
        foreach($info as $k=>$v): ?>
        <div class="d-flex justify-content-between px-3 py-2 border-bottom">
          <span class="text-muted" style="font-size:12px;"><?= h($k) ?></span>
          <span class="mono fw-semibold" style="font-size:12px;"><?= h($v) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script>
function showSavingState(form) {
  const btn = document.getElementById('saveSettingsBtn');
  if (btn) { btn.disabled=true; btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span><?= CURRENT_LANG==="ar"?"جاري الحفظ...":"Saving..." ?>'; }
}
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
