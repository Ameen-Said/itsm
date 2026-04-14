<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$pageTitle  = __('profile_title');
$breadcrumb = [['label' => __('profile_title'), 'active' => true]];

$uid  = $auth->getUserId();
$stmt = $db->prepare("SELECT u.*, r.name as role_name, d.name as dept_name FROM users u LEFT JOIN roles r ON u.role_id=r.id LEFT JOIN departments d ON u.department_id=d.id WHERE u.id=?");
$stmt->execute([$uid]);
$user = $stmt->fetch();

// My assets
$myAssets = $db->prepare("SELECT a.*, ac.name as cat_name FROM assets a LEFT JOIN asset_categories ac ON a.category_id=ac.id WHERE a.assigned_to=? ORDER BY a.name");
$myAssets->execute([$uid]);
$myAssets = $myAssets->fetchAll();

// Vault count
$vCount = $db->prepare("SELECT COUNT(*) FROM vault_entries WHERE user_id=?"); $vCount->execute([$uid]); $vCount=(int)$vCount->fetchColumn();

// Recent activity
$activity = $db->prepare("SELECT * FROM audit_logs WHERE user_id=? ORDER BY created_at DESC LIMIT 12");
$activity->execute([$uid]); $activity=$activity->fetchAll();

// Roles and departments for dropdowns
$roles = $db->query("SELECT id,name FROM roles ORDER BY name")->fetchAll();
$depts = $db->query("SELECT id,name FROM departments ORDER BY name")->fetchAll();

include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-person-circle"></i> <?= h(__('profile_title')) ?></h1>
</div>

<div class="row g-4">
  <!-- Left: Profile Card -->
  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-body text-center py-4">
        <!-- Avatar with upload -->
        <div class="position-relative d-inline-block mb-3">
          <?php if ($user['avatar'] && file_exists(UPLOAD_DIR.'avatars/'.$user['avatar'])): ?>
          <img src="<?= APP_URL ?>/uploads/avatars/<?= h($user['avatar']) ?>" alt="Avatar"
               class="rounded-circle" style="width:88px;height:88px;object-fit:cover;border:3px solid var(--border);">
          <?php else: ?>
          <div style="width:88px;height:88px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--purple));display:flex;align-items:center;justify-content:center;color:#fff;font-size:32px;font-weight:700;margin:0 auto;">
            <?= h(strtoupper(mb_substr($user['full_name'],0,2))) ?>
          </div>
          <?php endif; ?>
          <label for="avatarInput" class="position-absolute" style="bottom:0;right:0;width:28px;height:28px;background:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid var(--surface);" title="<?= h(__('profile_avatar')) ?>">
            <i class="bi bi-camera-fill text-white" style="font-size:11px;"></i>
            <input type="file" id="avatarInput" accept="image/*" class="d-none" onchange="uploadAvatar(this)">
          </label>
        </div>
        <h5 class="mb-1 fw-bold"><?= h($user['full_name']) ?></h5>
        <div class="text-muted mb-2"><?= h($user['job_title'] ?? '') ?></div>
        <div class="d-flex gap-2 justify-content-center flex-wrap">
          <span class="badge bg-primary"><?= h($user['role_name']) ?></span>
          <?php
          $statusMap=['active'=>'success','inactive'=>'warning','suspended'=>'danger'];
          $sCls=$statusMap[$user['status']]??'secondary';
          ?>
          <span class="badge bg-<?= $sCls ?>"><?= h(ucfirst($user['status'])) ?></span>
        </div>
      </div>
    </div>

    <!-- Stats Card -->
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-bar-chart text-primary"></i> <?= h(__('profile_my_stats')) ?></div>
      <div class="card-body p-0">
        <?php $statRows = [
          ['bi-laptop','primary',__('emp_assets'), count($myAssets)],
          ['bi-key','warning',__('nav_vault'), $vCount],
          ['bi-calendar','secondary',__('emp_joined'), formatDate($user['created_at'])],
          ['bi-clock','info',__('field_last_login'), $user['last_login']?formatDate($user['last_login'],'d M Y H:i'):__('emp_never')],
        ]; foreach ($statRows as [$icon,$c,$label,$val]): ?>
        <div class="d-flex align-items-center gap-3 p-3 border-bottom">
          <div class="stat-icon <?= $c ?>" style="width:34px;height:34px;font-size:15px;flex-shrink:0;"><i class="bi <?= $icon ?>"></i></div>
          <div><div class="text-muted" style="font-size:11px;"><?= h($label) ?></div><div class="fw-semibold" style="font-size:13px;"><?= h($val) ?></div></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Right: Forms -->
  <div class="col-md-8">
    <!-- Edit Profile -->
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-pencil-square text-primary"></i> <?= h(__('profile_edit')) ?></div>
      <div class="card-body">
        <form data-ajax action="<?= APP_URL ?>/actions/profile_save.php" method="post">
          <?= $auth->csrfField() ?>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label"><?= h(__('field_name')) ?> <span class="text-danger">*</span></label>
              <input type="text" name="full_name" class="form-control" value="<?= h($user['full_name']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= h(__('field_email')) ?> <span class="text-danger">*</span></label>
              <input type="email" name="email" class="form-control" value="<?= h($user['email']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= h(__('field_phone')) ?></label>
              <input type="text" name="phone" class="form-control" value="<?= h($user['phone']??'') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= h(__('field_job_title')) ?></label>
              <input type="text" name="job_title" class="form-control" value="<?= h($user['job_title']??'') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= h(__('field_theme')) ?></label>
              <select name="theme" class="form-select">
                <option value="light" <?= $user['theme']==='light'?'selected':'' ?>>☀️ <?= h(__('theme_light')) ?></option>
                <option value="dark"  <?= $user['theme']==='dark'?'selected':'' ?>>🌙 <?= h(__('theme_dark')) ?></option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= h(__('field_language')) ?></label>
              <select name="language" class="form-select">
                <option value="en" <?= ($user['language']??'en')==='en'?'selected':'' ?>>🇺🇸 <?= h(__('lang_en')) ?></option>
                <option value="ar" <?= ($user['language']??'en')==='ar'?'selected':'' ?>>🇸🇦 <?= h(__('lang_ar')) ?></option>
              </select>
            </div>
          </div>
          <div class="mt-3">
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i><?= h(__('btn_save')) ?></button>
          </div>
        </form>
      </div>
    </div>

    <!-- Change Password -->
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-lock text-warning"></i> <?= h(__('profile_change_pass')) ?></div>
      <div class="card-body">
        <form data-ajax action="<?= APP_URL ?>/actions/profile_password.php" method="post">
          <?= $auth->csrfField() ?>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label"><?= h(__('profile_current_pass')) ?></label>
              <div class="input-group">
                <input type="password" name="current_password" id="curPwd" class="form-control" required>
                <button type="button" class="btn btn-outline-secondary" data-toggle-password="#curPwd"><i class="bi bi-eye"></i></button>
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= h(__('profile_new_pass')) ?></label>
              <div class="input-group">
                <input type="password" name="new_password" id="newPwd" class="form-control" required minlength="8" oninput="checkPasswordStrength(this.value,'pwdStrength')">
                <button type="button" class="btn btn-outline-secondary" data-toggle-password="#newPwd"><i class="bi bi-eye"></i></button>
              </div>
              <div id="pwdStrength" class="mt-1"></div>
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= h(__('profile_confirm_pass')) ?></label>
              <input type="password" name="confirm_password" class="form-control" required minlength="8">
            </div>
          </div>
          <div class="mt-3">
            <button type="submit" class="btn btn-warning"><i class="bi bi-shield-lock me-1"></i><?= h(__('profile_change_pass')) ?></button>
          </div>
        </form>
      </div>
    </div>

    <!-- My Assets -->
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-laptop text-primary"></i> <?= h(__('profile_my_assets')) ?> <span class="badge bg-primary-subtle text-primary ms-1"><?= count($myAssets) ?></span></div>
      <div class="card-body p-0">
        <?php if (empty($myAssets)): ?>
        <p class="text-muted text-center py-3 mb-0"><?= h(__('msg_no_data')) ?></p>
        <?php else: ?>
        <table class="table table-sm mb-0">
          <thead><tr><th><?= h(__('nav_assets')) ?></th><th><?= h(__('field_category')) ?></th><th><?= h(__('field_status')) ?></th><th><?= h(__('field_warranty')) ?></th></tr></thead>
          <tbody>
            <?php foreach ($myAssets as $a): ?>
            <tr>
              <td><a href="<?= APP_URL ?>/pages/assets.php?id=<?= $a['id'] ?>" class="fw-semibold text-decoration-none"><?= h($a['name']) ?></a><div class="mono text-muted" style="font-size:11px;"><?= h($a['asset_code']) ?></div></td>
              <td><?= h($a['cat_name']??'—') ?></td>
              <td><?= assetStatusBadge($a['status']) ?></td>
              <td class="<?= expiryClass($a['warranty_expiry']) ?>" style="font-size:12px;"><?= formatDate($a['warranty_expiry']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>

    <!-- Activity -->
    <div class="card">
      <div class="card-header"><i class="bi bi-activity text-primary"></i> <?= h(__('profile_my_activity')) ?></div>
      <div class="card-body" style="max-height:280px;overflow-y:auto;">
        <div class="timeline">
          <?php if (empty($activity)): ?><p class="text-muted text-center">—</p>
          <?php else: foreach ($activity as $log): ?>
          <div class="timeline-item">
            <div class="timeline-dot"></div>
            <div class="timeline-body">
              <div class="d-flex justify-content-between">
                <span><?= h(str_replace('_',' ',ucfirst($log['action']))) ?> <span class="badge bg-secondary-subtle text-secondary"><?= h($log['module']) ?></span></span>
                <span class="timeline-time"><?= formatDate($log['created_at'],'d M H:i') ?></span>
              </div>
            </div>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
async function uploadAvatar(input) {
  if (!input.files[0]) return;
  const fd = new FormData();
  fd.append('avatar', input.files[0]);
  fd.append('csrf_token', getCsrf());
  const res = await api('/actions/profile_avatar.php', fd);
  if (res.success) { Toast.show('<?= h(__("msg_saved")) ?>','success'); setTimeout(()=>location.reload(),900); }
  else Toast.show(res.message||'Upload failed','danger');
}
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
