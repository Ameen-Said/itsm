<?php
// header.php — Complete v3 with full RTL, i18n, dark mode persistence
$currentUser = $auth->getUser();
$theme       = $auth->getTheme();
$lang        = $auth->getLang();
$isRTL       = ($lang === 'ar');
$dir         = $isRTL ? 'rtl' : 'ltr';
$companyName = getSetting('company_name', APP_NAME);
$companyLogo = getSetting('company_logo', '');

// Notifications
$notifs = [];
if ($currentUser) {
    $ns = $db->prepare("SELECT * FROM notifications WHERE (user_id=? OR user_id IS NULL) AND is_read=0 ORDER BY created_at DESC LIMIT 10");
    $ns->execute([$currentUser['id']]);
    $notifs = $ns->fetchAll();
}
$unread = count($notifs);

// Current page for active nav detection
$curPage = basename(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
function isNav(string $page, string $curPage): string {
    return str_contains($curPage, str_replace('.php','',$page)) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>" dir="<?= $dir ?>" data-bs-theme="<?= h($theme) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= h($auth->generateCsrfToken()) ?>">
<meta name="app-url" content="<?= h(APP_URL) ?>">
<meta name="lang" content="<?= h($lang) ?>">
<title><?= h($pageTitle ?? 'Dashboard') ?> — <?= h($companyName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<?php if ($isRTL): ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
<?php endif; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Cairo:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body>

<aside class="sidebar" id="appSidebar">
  <!-- Brand -->
  <div class="sidebar-brand">
    <?php if ($companyLogo && file_exists(UPLOAD_DIR.'logos/'.$companyLogo)): ?>
      <img src="<?= APP_URL ?>/uploads/logos/<?= h($companyLogo) ?>" class="brand-logo-img" alt="Logo">
    <?php else: ?>
      <div class="brand-logo-box"><i class="bi bi-cpu-fill"></i></div>
    <?php endif; ?>
    <div>
      <span class="brand-name"><?= h($companyName) ?></span>
      <span class="brand-ver">IT MANAGER PRO</span>
    </div>
  </div>

  <!-- Search -->
  <div class="sidebar-search">
    <div class="sb-search-wrap">
      <i class="bi bi-search sb-search-icon"></i>
      <input type="text" id="globalSearch" class="sb-search-input"
             placeholder="<?= h(__('search_placeholder')) ?>" autocomplete="off">
    </div>
    <div id="searchDropdown" class="search-dropdown"></div>
  </div>

  <!-- Nav -->
  <nav class="sidebar-nav">
    <?php
    $groups = [
      __('nav_assets_inv') => [
        ['bi-speedometer2', __('nav_dashboard'),  '/pages/dashboard.php',   'dashboard','view'],
        ['bi-laptop',       __('nav_assets'),      '/pages/assets.php',       'assets',   'view'],
        ['bi-tags',         __('nav_licenses'),    '/pages/licenses.php',     'licenses', 'view'],
      ],
      __('nav_people') => [
        ['bi-people',       __('nav_employees'),   '/pages/users.php',        'users',    'view'],
        ['bi-diagram-3',    __('nav_departments'), '/pages/departments.php',  'departments','view'],
      ],
      __('nav_operations') => [
        ['bi-shop',         __('nav_vendors'),     '/pages/vendors.php',      'vendors',  'view'],
        ['bi-cart3',        __('nav_procurement'), '/pages/procurement.php',  'procurement','view'],
        ['bi-folder2',      __('nav_documents'),   '/pages/documents.php',    'documents','view'],
      ],
      __('nav_tools') => [
        ['bi-key',          __('nav_vault'),       '/pages/vault.php',        'vault',    'view'],
        ['bi-envelope',     __('nav_email'),       '/pages/email.php',        'email',    'view'],
        ['bi-bar-chart',    __('nav_reports'),     '/pages/reports.php',      'reports',  'view'],
        ['bi-file-earmark-arrow-up', __('nav_import'), '/pages/import.php',   'assets',  'add'],
      ],
      __('nav_admin') => [
        ['bi-shield-lock',  __('nav_roles'),       '/pages/roles.php',        'roles',    'view'],
        ['bi-journal-text', __('nav_audit'),       '/pages/audit.php',        'audit',    'view'],
        ['bi-gear',         __('nav_settings'),    '/pages/settings.php',     'settings', 'view'],
      ],
    ];
    foreach ($groups as $label => $items):
        $visible = array_filter($items, fn($i) => $auth->can($i[3], $i[4]));
        if (!$visible) continue;
    ?>
    <div class="nav-section">
      <div class="nav-section-title"><?= h($label) ?></div>
      <?php foreach ($visible as [$icon, $name, $href, , ]):
        $active = isNav(basename($href), $curPage); ?>
      <a href="<?= APP_URL . h($href) ?>" class="nav-link-item <?= $active ?>">
        <i class="bi <?= h($icon) ?>"></i><span><?= h($name) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </nav>

  <!-- Footer -->
  <div class="sidebar-footer">
    <a href="<?= APP_URL ?>/pages/profile.php" class="sb-user-link">
      <div class="sb-avatar">
        <?php if (!empty($currentUser['avatar']) && file_exists(UPLOAD_DIR.'avatars/'.$currentUser['avatar'])): ?>
          <img src="<?= APP_URL ?>/uploads/avatars/<?= h($currentUser['avatar']) ?>" alt="">
        <?php else: ?>
          <?= h(mb_strtoupper(mb_substr($currentUser['full_name'] ?? 'U', 0, 2))) ?>
        <?php endif; ?>
      </div>
      <div>
        <span class="sb-user-name"><?= h($currentUser['full_name']) ?></span>
        <span class="sb-user-role"><?= h($currentUser['role_name']) ?></span>
      </div>
    </a>
    <a href="<?= APP_URL ?>/actions/logout.php" class="sb-logout-btn" title="<?= h(__('nav_logout')) ?>">
      <i class="bi bi-box-arrow-<?= $isRTL ? 'left' : 'right' ?>"></i>
    </a>
  </div>
</aside>

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="main-wrapper" id="mainWrapper">
  <!-- Topbar -->
  <header class="topbar">
    <div class="topbar-left">
      <button class="topbar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
      <nav class="topbar-breadcrumb d-none d-md-flex">
        <a href="<?= APP_URL ?>/pages/dashboard.php"><i class="bi bi-house"></i></a>
        <?php if (isset($breadcrumb)): foreach ($breadcrumb as $bc): ?>
          <span class="sep">›</span>
          <?php if (isset($bc['url'])): ?>
            <a href="<?= h($bc['url']) ?>"><?= h($bc['label']) ?></a>
          <?php else: ?>
            <span class="current"><?= h($bc['label']) ?></span>
          <?php endif; ?>
        <?php endforeach; endif; ?>
      </nav>
    </div>
    <div class="topbar-right">
      <!-- Lang -->
      <div class="lang-switcher">
        <button class="lang-pill <?= $lang==='en'?'active':'' ?>" onclick="switchLang('en')">EN</button>
        <button class="lang-pill <?= $lang==='ar'?'active':'' ?>" onclick="switchLang('ar')">ع</button>
      </div>
      <!-- Theme -->
      <button class="tb-btn" id="themeToggle" title="Toggle theme">
        <i class="bi bi-<?= $theme==='dark'?'sun-fill':'moon-fill' ?>"></i>
      </button>
      <!-- Notifications -->
      <div class="dropdown">
        <button class="tb-btn position-relative" data-bs-toggle="dropdown">
          <i class="bi bi-bell-fill"></i>
          <?php if ($unread > 0): ?><span class="tb-badge"><?= min($unread,99) ?></span><?php endif; ?>
        </button>
        <div class="dropdown-menu dropdown-menu-<?= $isRTL?'start':'end' ?> p-0 notif-panel shadow">
          <div class="notif-head">
            <span><?= h(__('notif_title')) ?><?php if($unread>0): ?> <span class="badge bg-danger ms-1"><?= $unread ?></span><?php endif;?></span>
            <button class="notif-mark-all"><?= h(__('notif_mark_read')) ?></button>
          </div>
          <div class="notif-list">
            <?php if (empty($notifs)): ?>
              <div class="notif-empty"><i class="bi bi-bell-slash d-block fs-2 mb-2"></i><?= h(__('notif_empty')) ?></div>
            <?php else: foreach ($notifs as $n): ?>
              <a class="notif-row unread" href="<?= h($n['link']??'#') ?>" data-id="<?= (int)$n['id'] ?>">
                <div class="notif-ico <?= h($n['type']) ?>">
                  <i class="bi bi-<?= $n['type']==='warning'?'exclamation-triangle':($n['type']==='danger'?'x-circle':($n['type']==='success'?'check-circle':'info-circle')) ?>-fill"></i>
                </div>
                <div>
                  <div class="notif-title"><?= h(mb_strimwidth($n['title'],0,55,'…')) ?></div>
                  <div class="notif-time"><?= formatDate($n['created_at'],'d M H:i') ?></div>
                </div>
              </a>
            <?php endforeach; endif; ?>
          </div>
          <div class="notif-footer"><a href="<?= APP_URL ?>/pages/audit.php"><?= h(__('notif_view_all')) ?> →</a></div>
        </div>
      </div>
      <!-- User menu -->
      <div class="dropdown">
        <button class="tb-user-btn" data-bs-toggle="dropdown">
          <div class="tb-avatar">
            <?php if (!empty($currentUser['avatar']) && file_exists(UPLOAD_DIR.'avatars/'.$currentUser['avatar'])): ?>
              <img src="<?= APP_URL ?>/uploads/avatars/<?= h($currentUser['avatar']) ?>" alt="">
            <?php else: ?>
              <?= h(mb_strtoupper(mb_substr($currentUser['full_name']??'U',0,2))) ?>
            <?php endif; ?>
          </div>
          <span class="d-none d-md-inline"><?= h(explode(' ',$currentUser['full_name'])[0]) ?></span>
          <i class="bi bi-chevron-down" style="font-size:10px;color:var(--text-3)"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-<?= $isRTL?'start':'end' ?> shadow" style="min-width:200px;">
          <li class="px-3 py-2 border-bottom mb-1">
            <div class="fw-semibold" style="font-size:13px;"><?= h($currentUser['full_name']) ?></div>
            <div class="text-muted" style="font-size:11.5px;"><?= h($currentUser['email']) ?></div>
          </li>
          <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/profile.php"><i class="bi bi-person me-2 text-muted"></i><?= h(__('nav_profile')) ?></a></li>
          <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/settings.php"><i class="bi bi-gear me-2 text-muted"></i><?= h(__('nav_settings')) ?></a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/actions/logout.php"><i class="bi bi-box-arrow-<?= $isRTL?'left':'right' ?> me-2"></i><?= h(__('nav_logout')) ?></a></li>
        </ul>
      </div>
    </div>
  </header>

  <!-- Page body -->
  <main class="page-body">
    <?php foreach (getFlash() as $flash): ?>
      <div class="alert alert-<?= h($flash['type']) ?> alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-<?= $flash['type']==='success'?'check-circle':'exclamation-triangle' ?>-fill me-2"></i>
        <?= h($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endforeach; ?>
