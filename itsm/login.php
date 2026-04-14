<?php
require_once __DIR__ . '/includes/bootstrap.php';

if ($auth->isLoggedIn()) redirect(APP_URL . '/pages/dashboard.php');

// Language for login page (from cookie/query)
$loginLang = $_GET['lang'] ?? ($_COOKIE['lang'] ?? 'en');
$loginLang = in_array($loginLang, ['en','ar']) ? $loginLang : 'en';
$loginRTL  = $loginLang === 'ar';
loadLang($loginLang);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = __('val_csrf');
    } else {
        $result = $auth->login(
            trim($_POST['username'] ?? ''),
            $_POST['password'] ?? ''
        );
        if ($result['success']) {
            redirect(APP_URL . '/pages/dashboard.php');
        } else {
            $error = $result['message'];
        }
    }
}

$companyName = getSetting('company_name', APP_NAME);
$companyLogo = getSetting('company_logo', '');
?>
<!DOCTYPE html>
<html lang="<?= h($loginLang) ?>" dir="<?= $loginRTL?'rtl':'ltr' ?>" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h(__('auth_login_title')) ?> — <?= h($companyName) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <?php if ($loginRTL): ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
  <?php endif; ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <?php if ($loginRTL): ?>
  <style>body{font-family:'Cairo',sans-serif!important}</style>
  <?php endif; ?>
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <!-- Logo -->
    <div class="login-logo">
      <?php if ($companyLogo && file_exists(UPLOAD_DIR.'logos/'.$companyLogo)): ?>
        <img src="<?= APP_URL ?>/uploads/logos/<?= h($companyLogo) ?>" alt="Logo">
      <?php else: ?>
        <i class="bi bi-cpu-fill"></i>
      <?php endif; ?>
    </div>
    <h1 class="login-title"><?= h($companyName) ?></h1>
    <p class="login-subtitle"><?= h(__('auth_subtitle')) ?></p>

    <?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-3" style="font-size:13px;background:rgba(220,38,38,.15);border-color:rgba(220,38,38,.3);color:#fca5a5;">
      <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
      <?= h($error) ?>
    </div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= h($auth->generateCsrfToken()) ?>">
      <div class="mb-3">
        <label class="form-label"><?= h(__('auth_username')) ?></label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-person"></i></span>
          <input type="text" name="username" class="form-control"
                 value="<?= h($_POST['username'] ?? '') ?>"
                 placeholder="<?= h(__('auth_username')) ?>"
                 required autofocus autocomplete="username">
        </div>
      </div>
      <div class="mb-4">
        <label class="form-label"><?= h(__('auth_password')) ?></label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input type="password" name="password" id="loginPass" class="form-control"
                 placeholder="<?= h(__('auth_password')) ?>"
                 required autocomplete="current-password">
          <button type="button" class="input-group-text" data-toggle-password="#loginPass" style="cursor:pointer;">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
        <i class="bi bi-box-arrow-<?= $loginRTL?'left':'right' ?> me-2"></i><?= h(__('auth_signin')) ?>
      </button>
    </form>

    <!-- Language switcher -->
    <div class="text-center mt-4 d-flex justify-content-center gap-2">
      <a href="?lang=en" class="lang-btn <?= $loginLang==='en'?'active':'' ?>" style="text-decoration:none;">EN</a>
      <a href="?lang=ar" class="lang-btn <?= $loginLang==='ar'?'active':'' ?>" style="text-decoration:none;">ع</a>
    </div>

    <div class="text-center mt-3" style="font-size:11.5px;color:rgba(255,255,255,.25);">
      <?= h(__('auth_default_hint')) ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('click', e => {
  const btn = e.target.closest('[data-toggle-password]');
  if (!btn) return;
  const t = document.querySelector(btn.dataset.togglePassword);
  if (!t) return;
  const show = t.type === 'password';
  t.type = show ? 'text' : 'password';
  const i = btn.querySelector('i');
  if (i) i.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
});
</script>
</body>
</html>
