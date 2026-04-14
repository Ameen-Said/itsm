<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requirePermission('dashboard','view');
$pageTitle  = __('dash_title');
$breadcrumb = [['label'=>__('dash_title'),'active'=>true]];

// Stats
$stats = [
    'total_assets'     => $db->query("SELECT COUNT(*) FROM assets")->fetchColumn(),
    'assigned_assets'  => $db->query("SELECT COUNT(*) FROM assets WHERE status='assigned'")->fetchColumn(),
    'available_assets' => $db->query("SELECT COUNT(*) FROM assets WHERE status='available'")->fetchColumn(),
    'maintenance'      => $db->query("SELECT COUNT(*) FROM assets WHERE status='maintenance'")->fetchColumn(),
    'total_users'      => $db->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn(),
    'total_licenses'   => $db->query("SELECT COUNT(*) FROM licenses")->fetchColumn(),
    'expiring_lic'     => $db->query("SELECT COUNT(*) FROM licenses WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) AND status='active'")->fetchColumn(),
    'total_docs'       => $db->query("SELECT COUNT(*) FROM documents")->fetchColumn(),
    'total_vendors'    => $db->query("SELECT COUNT(*) FROM vendors WHERE status='active'")->fetchColumn(),
    'warranty_exp'     => $db->query("SELECT COUNT(*) FROM assets WHERE warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)")->fetchColumn(),
    'total_cost'       => $db->query("SELECT COALESCE(SUM(price),0) FROM assets")->fetchColumn(),
    'pending_pos'      => $db->query("SELECT COUNT(*) FROM purchase_orders WHERE status='draft'")->fetchColumn(),
];

// Chart data
$catRows  = $db->query("SELECT ac.name, COUNT(a.id) as cnt FROM asset_categories ac LEFT JOIN assets a ON a.category_id=ac.id GROUP BY ac.id ORDER BY cnt DESC LIMIT 8")->fetchAll();
$stRows   = $db->query("SELECT status, COUNT(*) as cnt FROM assets GROUP BY status")->fetchAll();
$stMap    = ['available'=>0,'assigned'=>0,'maintenance'=>0,'retired'=>0];
foreach ($stRows as $r) { if(isset($stMap[$r['status']])) $stMap[$r['status']]=(int)$r['cnt']; }
$mRows    = $db->query("SELECT DATE_FORMAT(created_at,'%b %Y') as m, COUNT(*) as cnt FROM assets WHERE created_at>=DATE_SUB(NOW(),INTERVAL 6 MONTH) GROUP BY YEAR(created_at),MONTH(created_at) ORDER BY created_at")->fetchAll();
$dRows    = $db->query("SELECT d.name, COUNT(a.id) as cnt, COALESCE(SUM(a.price),0) as v FROM departments d LEFT JOIN assets a ON a.department_id=d.id GROUP BY d.id ORDER BY cnt DESC LIMIT 6")->fetchAll();

// Expiry alerts
$expLic = $db->query("SELECT software_name,expiry_date,DATEDIFF(expiry_date,CURDATE()) as dl FROM licenses WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 60 DAY) AND status='active' ORDER BY expiry_date LIMIT 5")->fetchAll();
$expWar = $db->query("SELECT name,asset_code,warranty_expiry,DATEDIFF(warranty_expiry,CURDATE()) as dl FROM assets WHERE warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 60 DAY) ORDER BY warranty_expiry LIMIT 5")->fetchAll();
$activity = $db->query("SELECT al.*,u.full_name FROM audit_logs al LEFT JOIN users u ON al.user_id=u.id ORDER BY al.created_at DESC LIMIT 10")->fetchAll();

$jsCatL = json_encode(array_column($catRows,'name'));
$jsCatD = json_encode(array_map('intval',array_column($catRows,'cnt')));
$jsStD  = json_encode(array_values($stMap));
$jsML   = json_encode(array_column($mRows,'m'));
$jsMD   = json_encode(array_map('intval',array_column($mRows,'cnt')));

include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title"><div class="title-icon"><i class="bi bi-speedometer2"></i></div> <?= h(__('dash_title')) ?></h1>
  <div class="d-flex align-items-center gap-3">
    <span class="badge bg-success-subtle text-success px-3 py-2" style="font-size:12px;"><i class="bi bi-circle-fill me-1" style="font-size:7px;"></i><?= h(__('dash_system_online')) ?></span>
    <span class="text-muted" style="font-size:13px;"><?= date('D, d M Y H:i') ?></span>
  </div>
</div>

<!-- Row 1 -->
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="stat-card blue">
      <div class="stat-icon-box blue"><i class="bi bi-laptop"></i></div>
      <div class="stat-num"><?= number_format((int)$stats['total_assets']) ?></div>
      <div class="stat-label"><?= h(__('dash_total_assets')) ?></div>
      <div class="stat-sub"><?= (int)$stats['assigned_assets'] ?> <?= h(__('dash_assigned_count')) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card green">
      <div class="stat-icon-box green"><i class="bi bi-people-fill"></i></div>
      <div class="stat-num"><?= number_format((int)$stats['total_users']) ?></div>
      <div class="stat-label"><?= h(__('dash_active_employees')) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card amber">
      <div class="stat-icon-box amber"><i class="bi bi-tags-fill"></i></div>
      <div class="stat-num"><?= number_format((int)$stats['total_licenses']) ?></div>
      <div class="stat-label"><?= h(__('dash_licenses')) ?></div>
      <?php if ($stats['expiring_lic'] > 0): ?><div class="stat-sub text-warning"><i class="bi bi-exclamation-triangle"></i> <?= $stats['expiring_lic'] ?> <?= h(__('dash_expiring_soon')) ?></div><?php endif; ?>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card cyan">
      <div class="stat-icon-box cyan"><i class="bi bi-currency-dollar"></i></div>
      <div class="stat-num" style="font-size:18px;"><?= formatMoney((float)$stats['total_cost']) ?></div>
      <div class="stat-label"><?= h(__('dash_asset_value')) ?></div>
    </div>
  </div>
</div>

<!-- Row 2 -->
<div class="row g-3 mb-4">
  <div class="col-4 col-md-2"><div class="stat-card green"><div class="stat-icon-box green" style="width:34px;height:34px;font-size:15px;margin-bottom:8px;"><i class="bi bi-check-circle"></i></div><div class="stat-num" style="font-size:20px;"><?= (int)$stats['available_assets'] ?></div><div class="stat-label"><?= h(__('dash_available')) ?></div></div></div>
  <div class="col-4 col-md-2"><div class="stat-card blue"><div class="stat-icon-box blue" style="width:34px;height:34px;font-size:15px;margin-bottom:8px;"><i class="bi bi-person-check"></i></div><div class="stat-num" style="font-size:20px;"><?= (int)$stats['assigned_assets'] ?></div><div class="stat-label"><?= h(__('dash_assigned')) ?></div></div></div>
  <div class="col-4 col-md-2"><div class="stat-card amber"><div class="stat-icon-box amber" style="width:34px;height:34px;font-size:15px;margin-bottom:8px;"><i class="bi bi-tools"></i></div><div class="stat-num" style="font-size:20px;"><?= (int)$stats['maintenance'] ?></div><div class="stat-label"><?= h(__('dash_maintenance')) ?></div></div></div>
  <div class="col-4 col-md-2"><div class="stat-card red"><div class="stat-icon-box red" style="width:34px;height:34px;font-size:15px;margin-bottom:8px;"><i class="bi bi-shield-exclamation"></i></div><div class="stat-num" style="font-size:20px;"><?= (int)$stats['warranty_exp'] ?></div><div class="stat-label"><?= h(__('dash_warranty_expiring')) ?></div></div></div>
  <div class="col-4 col-md-2"><div class="stat-card violet"><div class="stat-icon-box violet" style="width:34px;height:34px;font-size:15px;margin-bottom:8px;"><i class="bi bi-folder-fill"></i></div><div class="stat-num" style="font-size:20px;"><?= (int)$stats['total_docs'] ?></div><div class="stat-label"><?= h(__('dash_documents')) ?></div></div></div>
  <div class="col-4 col-md-2"><div class="stat-card cyan"><div class="stat-icon-box cyan" style="width:34px;height:34px;font-size:15px;margin-bottom:8px;"><i class="bi bi-shop"></i></div><div class="stat-num" style="font-size:20px;"><?= (int)$stats['total_vendors'] ?></div><div class="stat-label"><?= h(__('dash_vendors')) ?></div></div></div>
</div>

<!-- Charts -->
<div class="row g-3 mb-4">
  <div class="col-md-8">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-bar-chart-fill text-primary"></i> <?= h(__('dash_asset_dist')) ?></div>
      <div class="chart-wrap"><canvas id="chartCat" height="220"></canvas></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-pie-chart-fill text-primary"></i> <?= h(__('dash_asset_status')) ?></div>
      <div class="chart-wrap d-flex align-items-center justify-content-center" style="height:260px;"><canvas id="chartSt"></canvas></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-7">
    <div class="card">
      <div class="card-header"><i class="bi bi-graph-up text-primary"></i> <?= h(__('dash_monthly_assets')) ?></div>
      <div class="chart-wrap"><canvas id="chartMonth" height="160"></canvas></div>
    </div>
  </div>
  <div class="col-md-5">
    <div class="card">
      <div class="card-header"><i class="bi bi-building text-primary"></i> <?= h(__('dash_dept_assets')) ?></div>
      <div class="card-body p-0">
        <?php foreach($dRows as $d): ?><div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom"><span style="font-size:13px;"><?=h($d['name'])?></span><div class="d-flex gap-3 text-end"><span class="badge bg-primary-subtle text-primary"><?=(int)$d['cnt']?></span><span class="mono text-muted" style="font-size:12px;"><?=formatMoney($d['v'])?></span></div></div><?php endforeach;?>
      </div>
    </div>
  </div>
</div>

<!-- Alerts + Activity -->
<div class="row g-3">
  <div class="col-md-5">
    <div class="card">
      <div class="card-header"><i class="bi bi-exclamation-triangle-fill text-warning"></i> <?= h(__('dash_expiry_alerts')) ?></div>
      <div class="card-body p-0">
        <?php if(empty($expLic)&&empty($expWar)): ?><div class="text-center py-4 text-muted"><i class="bi bi-check-circle fs-2 text-success d-block mb-2"></i><?=h(__('dash_no_expiry'))?></div><?php else:?>
          <?php foreach($expLic as $l): ?><div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom <?=(int)$l['dl']<=7?'row-critical':''?>"><i class="bi bi-tags-fill text-warning fs-5 flex-shrink-0"></i><div class="flex-grow-1"><div class="fw-semibold" style="font-size:13px;"><?=h($l['software_name'])?></div><div class="text-muted" style="font-size:12px;"><?=h(__('dash_expires'))?> <?=formatDate($l['expiry_date'])?></div></div><span class="badge bg-warning text-dark"><?=(int)$l['dl']?>d</span></div><?php endforeach;?>
          <?php foreach($expWar as $w): ?><div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom <?=(int)$w['dl']<=7?'row-critical':''?>"><i class="bi bi-shield-exclamation text-danger fs-5 flex-shrink-0"></i><div class="flex-grow-1"><div class="fw-semibold" style="font-size:13px;"><?=h($w['name'])?></div><div class="text-muted" style="font-size:12px;"><?=h(__('dash_warranty_expires'))?> <?=formatDate($w['warranty_expiry'])?></div></div><span class="badge bg-danger"><?=(int)$w['dl']?>d</span></div><?php endforeach;?>
        <?php endif;?>
      </div>
    </div>
  </div>
  <div class="col-md-7">
    <div class="card">
      <div class="card-header"><i class="bi bi-activity text-primary"></i> <?= h(__('dash_recent_activity')) ?></div>
      <div class="card-body" style="max-height:320px;overflow-y:auto;">
        <div class="timeline">
          <?php if(empty($activity)): ?><p class="text-muted text-center py-2"><?=h(__('dash_no_activity'))?></p>
          <?php else: foreach($activity as $log): ?>
          <div class="timeline-item">
            <div class="timeline-dot"></div>
            <div class="timeline-body">
              <div class="d-flex justify-content-between align-items-start">
                <div><span class="fw-semibold"><?=h($log['full_name']??'System')?></span> <span class="text-muted"><?=h(str_replace('_',' ',$log['action']))?></span> <span class="badge bg-secondary-subtle text-secondary ms-1"><?=h($log['module'])?></span></div>
                <span class="timeline-time"><?=formatDate($log['created_at'],'d M H:i')?></span>
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
document.addEventListener('DOMContentLoaded', function() {
  const dk    = document.documentElement.getAttribute('data-bs-theme') === 'dark';
  const grid  = dk ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.06)';
  const txt   = dk ? '#7d8590' : '#64748b';
  Chart.defaults.color = txt;
  Chart.defaults.borderColor = grid;
  Chart.defaults.font.family = "Inter,Cairo,sans-serif";

  const el1 = document.getElementById('chartCat');
  if (el1) new Chart(el1, {
    type: 'bar',
    data: { labels: <?= $jsCatL ?>, datasets: [{ label: 'Assets', data: <?= $jsCatD ?>, backgroundColor: 'rgba(59,130,246,.75)', borderRadius: 5, borderSkipped: false }] },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: grid }, ticks: { color: txt, precision: 0 } }, x: { grid: { display: false }, ticks: { color: txt } } } }
  });

  const el2 = document.getElementById('chartSt');
  if (el2) new Chart(el2, {
    type: 'doughnut',
    data: {
      labels: ['<?= h(__("dash_available")) ?>','<?= h(__("dash_assigned")) ?>','<?= h(__("dash_maintenance")) ?>','Retired'],
      datasets: [{ data: <?= $jsStD ?>, backgroundColor: ['#10b981','#3b82f6','#f59e0b','#6b7280'], borderWidth: 2, borderColor: dk?'#111827':'#ffffff' }]
    },
    options: { responsive: true, cutout: '72%', plugins: { legend: { position: 'bottom', labels: { padding: 10, font: { size: 11 } } } } }
  });

  const el3 = document.getElementById('chartMonth');
  if (el3) new Chart(el3, {
    type: 'line',
    data: { labels: <?= $jsML ?>, datasets: [{ label: 'New Assets', data: <?= $jsMD ?>, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,.1)', fill: true, tension: .4, pointRadius: 4, pointBackgroundColor: '#3b82f6' }] },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: grid }, ticks: { color: txt, precision: 0 } }, x: { grid: { display: false }, ticks: { color: txt } } } }
  });
});
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
