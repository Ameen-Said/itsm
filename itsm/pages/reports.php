<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requirePermission('reports', 'view');

$pageTitle  = 'Reports';
$breadcrumb = [['label' => 'Reports', 'active' => true]];
$report     = $_GET['report'] ?? 'overview';

// ── Report Data ─────────────────────────────────────────────
$data = [];

switch ($report) {
    case 'assets_by_dept':
        $data = $db->query("SELECT d.name as department, COUNT(a.id) as total_assets,
            SUM(CASE WHEN a.status='available' THEN 1 ELSE 0 END) as available,
            SUM(CASE WHEN a.status='assigned' THEN 1 ELSE 0 END) as assigned,
            SUM(CASE WHEN a.status='maintenance' THEN 1 ELSE 0 END) as maintenance,
            COALESCE(SUM(a.price),0) as total_value
            FROM departments d LEFT JOIN assets a ON a.department_id=d.id
            GROUP BY d.id ORDER BY total_assets DESC")->fetchAll();
        break;

    case 'cost_analysis':
        $data = $db->query("SELECT ac.name as category, COUNT(a.id) as count,
            COALESCE(SUM(a.price),0) as total_cost, COALESCE(AVG(a.price),0) as avg_cost
            FROM asset_categories ac LEFT JOIN assets a ON a.category_id=ac.id
            GROUP BY ac.id ORDER BY total_cost DESC")->fetchAll();
        break;

    case 'license_usage':
        $data = $db->query("SELECT l.software_name, l.type, l.seats, l.seats_used,
            (SELECT COUNT(*) FROM license_assignments la WHERE la.license_id=l.id) as actual_used,
            l.expiry_date, l.price, l.status
            FROM licenses l ORDER BY l.software_name")->fetchAll();
        break;

    case 'expiry_report':
        $data = [
            'licenses' => $db->query("SELECT software_name, expiry_date, DATEDIFF(expiry_date,CURDATE()) as days_left, status FROM licenses WHERE expiry_date IS NOT NULL ORDER BY expiry_date ASC")->fetchAll(),
            'warranties'=> $db->query("SELECT name, asset_code, warranty_expiry, DATEDIFF(warranty_expiry,CURDATE()) as days_left, status FROM assets WHERE warranty_expiry IS NOT NULL ORDER BY warranty_expiry ASC")->fetchAll(),
        ];
        break;

    case 'inventory':
        $data = $db->query("SELECT a.asset_code, a.name, ac.name as category, a.brand, a.model,
            a.serial_number, a.status, u.full_name as assigned_to, d.name as department,
            v.name as vendor, a.purchase_date, a.warranty_expiry, a.price
            FROM assets a
            LEFT JOIN asset_categories ac ON a.category_id=ac.id
            LEFT JOIN users u ON a.assigned_to=u.id
            LEFT JOIN departments d ON a.department_id=d.id
            LEFT JOIN vendors v ON a.vendor_id=v.id
            ORDER BY a.asset_code")->fetchAll();
        break;

    default: // overview
        $data = [
            'asset_summary' => $db->query("SELECT status, COUNT(*) as cnt, COALESCE(SUM(price),0) as value FROM assets GROUP BY status")->fetchAll(),
            'monthly_spend' => $db->query("SELECT DATE_FORMAT(purchase_date,'%Y-%m') as month, COUNT(*) as purchases, COALESCE(SUM(price),0) as spend FROM assets WHERE purchase_date >= DATE_SUB(CURDATE(),INTERVAL 12 MONTH) GROUP BY month ORDER BY month")->fetchAll(),
            'top_vendors'   => $db->query("SELECT v.name, COUNT(a.id) as assets, COALESCE(SUM(a.price),0) as total FROM vendors v LEFT JOIN assets a ON a.vendor_id=v.id GROUP BY v.id ORDER BY total DESC LIMIT 5")->fetchAll(),
            'dept_cost'     => $db->query("SELECT d.name, COALESCE(SUM(a.price),0) as cost FROM departments d LEFT JOIN assets a ON a.department_id=d.id GROUP BY d.id ORDER BY cost DESC")->fetchAll(),
        ];
}

include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-bar-chart-line-fill"></i> Reports</h1>
  <div class="d-flex gap-2">
    <?php if ($auth->can('reports','export')): ?>
    <button class="btn btn-sm btn-outline-success" onclick="exportTable('reportTable','<?= h($report) ?>_report')"><i class="bi bi-filetype-csv me-1"></i>Export CSV</button>
    <button class="btn btn-sm btn-outline-danger" onclick="window.print()"><i class="bi bi-filetype-pdf me-1"></i>Print/PDF</button>
    <?php endif; ?>
  </div>
</div>

<!-- Report Type Selector -->
<div class="card mb-4">
  <div class="card-body py-2">
    <div class="d-flex gap-2 flex-wrap">
      <?php
      $reports = [
        'overview'      => ['bi-speedometer2','Overview'],
        'assets_by_dept'=> ['bi-building','Assets by Dept'],
        'cost_analysis' => ['bi-currency-dollar','Cost Analysis'],
        'license_usage' => ['bi-tags','License Usage'],
        'expiry_report' => ['bi-clock-history','Expiry Report'],
        'inventory'     => ['bi-list-check','Full Inventory'],
      ];
      foreach ($reports as $key => [$icon, $label]):
        $active = $report === $key ? 'btn-primary' : 'btn-outline-secondary';
      ?>
      <a href="?report=<?= $key ?>" class="btn btn-sm <?= $active ?>"><i class="bi <?= $icon ?> me-1"></i><?= $label ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ── Report Content ────────────────────────────────────── -->

<?php if ($report === 'overview'): ?>
<div class="row g-4">
  <!-- Asset Summary -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header"><i class="bi bi-pie-chart text-primary"></i> Asset Summary by Status</div>
      <div class="card-body p-0">
        <table class="table mb-0" id="reportTable">
          <thead><tr><th>Status</th><th>Count</th><th>Total Value</th><th>% of Total</th></tr></thead>
          <tbody>
            <?php
            $totalAssets = array_sum(array_column($data['asset_summary'], 'cnt'));
            foreach ($data['asset_summary'] as $row):
              $pct = $totalAssets > 0 ? round($row['cnt']/$totalAssets*100,1) : 0;
            ?>
            <tr>
              <td><?= assetStatusBadge($row['status']) ?></td>
              <td class="fw-semibold"><?= number_format($row['cnt']) ?></td>
              <td class="mono"><?= formatMoney($row['value']) ?></td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="progress flex-grow-1" style="height:6px;"><div class="progress-bar bg-primary" style="width:<?=$pct?>%"></div></div>
                  <span style="font-size:12px;min-width:36px;"><?=$pct?>%</span>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Top Vendors -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header"><i class="bi bi-trophy text-warning"></i> Top Vendors by Spend</div>
      <div class="card-body p-0">
        <table class="table mb-0">
          <thead><tr><th>Vendor</th><th>Assets</th><th>Total Spend</th></tr></thead>
          <tbody>
            <?php foreach ($data['top_vendors'] as $v): ?>
            <tr>
              <td class="fw-semibold"><?= h($v['name']) ?></td>
              <td><span class="badge bg-primary-subtle text-primary"><?= $v['assets'] ?></span></td>
              <td class="mono fw-semibold text-success"><?= formatMoney($v['total']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Monthly Spend Chart -->
  <div class="col-12">
    <div class="card">
      <div class="card-header"><i class="bi bi-graph-up text-primary"></i> Monthly Asset Purchases & Spend (12 months)</div>
      <div class="chart-wrapper"><canvas id="monthlyChart" height="100"></canvas></div>
    </div>
  </div>

  <!-- Dept Cost -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header"><i class="bi bi-building text-primary"></i> Asset Value by Department</div>
      <div class="chart-wrapper"><canvas id="deptChart" height="180"></canvas></div>
    </div>
  </div>
</div>

<?php elseif ($report === 'assets_by_dept'): ?>
<div class="table-card">
  <table class="table" id="reportTable">
    <thead><tr><th>Department</th><th>Total Assets</th><th>Available</th><th>Assigned</th><th>Maintenance</th><th>Total Value</th></tr></thead>
    <tbody>
      <?php foreach ($data as $row): ?>
      <tr>
        <td class="fw-semibold"><?= h($row['department']) ?></td>
        <td><?= number_format($row['total_assets']) ?></td>
        <td><span class="badge bg-success"><?= $row['available'] ?></span></td>
        <td><span class="badge bg-primary"><?= $row['assigned'] ?></span></td>
        <td><span class="badge bg-warning text-dark"><?= $row['maintenance'] ?></span></td>
        <td class="mono fw-semibold"><?= formatMoney($row['total_value']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php elseif ($report === 'cost_analysis'): ?>
<div class="row g-4">
  <div class="col-md-7">
    <div class="table-card">
      <table class="table" id="reportTable">
        <thead><tr><th>Category</th><th>Count</th><th>Total Cost</th><th>Avg Cost</th><th>% of Total</th></tr></thead>
        <tbody>
          <?php
          $grandTotal = array_sum(array_column($data,'total_cost'));
          foreach ($data as $row):
            $pct = $grandTotal > 0 ? round($row['total_cost']/$grandTotal*100,1) : 0;
          ?>
          <tr>
            <td class="fw-semibold"><?= h($row['category']) ?></td>
            <td><?= $row['count'] ?></td>
            <td class="mono"><?= formatMoney($row['total_cost']) ?></td>
            <td class="mono"><?= formatMoney($row['avg_cost']) ?></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="progress flex-grow-1" style="height:6px;"><div class="progress-bar bg-info" style="width:<?=$pct?>%"></div></div>
                <small><?=$pct?>%</small>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <tr class="fw-bold table-secondary">
            <td>TOTAL</td>
            <td><?= array_sum(array_column($data,'count')) ?></td>
            <td class="mono"><?= formatMoney($grandTotal) ?></td>
            <td>—</td><td>100%</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
  <div class="col-md-5">
    <div class="card"><div class="card-header"><i class="bi bi-pie-chart text-primary"></i> Cost by Category</div>
    <div class="chart-wrapper d-flex justify-content-center"><canvas id="costChart" width="280" height="280"></canvas></div></div>
  </div>
</div>

<?php elseif ($report === 'license_usage'): ?>
<div class="table-card">
  <table class="table" id="reportTable">
    <thead><tr><th>Software</th><th>Type</th><th>Seats</th><th>Used</th><th>Utilization</th><th>Expiry</th><th>Cost</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($data as $row):
        $used = $row['actual_used'];
        $pct  = $row['seats'] > 0 ? round($used/$row['seats']*100) : 0;
      ?>
      <tr class="<?= $row['expiry_date'] && daysUntil($row['expiry_date']) < 30 ? 'expiry-warning' : '' ?>">
        <td class="fw-semibold"><?= h($row['software_name']) ?></td>
        <td><span class="badge bg-secondary-subtle text-secondary"><?= h(str_replace('_',' ',ucfirst($row['type']))) ?></span></td>
        <td><?= $row['seats'] ?></td>
        <td><?= $used ?></td>
        <td>
          <div class="d-flex align-items-center gap-2">
            <div class="progress flex-grow-1" style="height:6px;">
              <div class="progress-bar <?= $pct>=90?'bg-danger':($pct>=70?'bg-warning':'bg-success') ?>" style="width:<?=$pct?>%"></div>
            </div>
            <small><?=$pct?>%</small>
          </div>
        </td>
        <td class="<?= expiryClass($row['expiry_date']) ?>" style="font-size:12px;"><?= formatDate($row['expiry_date']) ?></td>
        <td class="mono" style="font-size:12px;"><?= formatMoney($row['price']) ?></td>
        <td><?= licenseStatusBadge($row['status']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php elseif ($report === 'expiry_report'): ?>
<div class="row g-4">
  <div class="col-md-6">
    <div class="card">
      <div class="card-header text-warning"><i class="bi bi-tags me-1"></i> License Expiries</div>
      <div class="table-card">
        <table class="table table-sm" id="reportTable">
          <thead><tr><th>Software</th><th>Expiry</th><th>Days Left</th></tr></thead>
          <tbody>
            <?php foreach ($data['licenses'] as $l):
              $cls = ($l['days_left']??999) < 0 ? 'expiry-critical' : (($l['days_left']??999) <= 30 ? 'expiry-warning' : '');
            ?>
            <tr class="<?= $cls ?>">
              <td class="fw-semibold"><?= h($l['software_name']) ?></td>
              <td style="font-size:12px;"><?= formatDate($l['expiry_date']) ?></td>
              <td><?php
                if ($l['days_left'] < 0) echo '<span class="badge bg-danger">Expired '.abs($l['days_left']).'d ago</span>';
                elseif ($l['days_left'] <= 7) echo '<span class="badge bg-danger">'.$l['days_left'].'d</span>';
                elseif ($l['days_left'] <= 30) echo '<span class="badge bg-warning text-dark">'.$l['days_left'].'d</span>';
                else echo '<span class="badge bg-success">'.$l['days_left'].'d</span>';
              ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card">
      <div class="card-header text-danger"><i class="bi bi-shield-exclamation me-1"></i> Warranty Expiries</div>
      <div class="table-card">
        <table class="table table-sm">
          <thead><tr><th>Asset</th><th>Warranty</th><th>Days Left</th></tr></thead>
          <tbody>
            <?php foreach ($data['warranties'] as $w):
              $cls = ($w['days_left']??999) < 0 ? 'expiry-critical' : (($w['days_left']??999) <= 30 ? 'expiry-warning' : '');
            ?>
            <tr class="<?= $cls ?>">
              <td>
                <div class="fw-semibold" style="font-size:13px;"><?= h($w['name']) ?></div>
                <div class="mono text-muted" style="font-size:11px;"><?= h($w['asset_code']) ?></div>
              </td>
              <td style="font-size:12px;"><?= formatDate($w['warranty_expiry']) ?></td>
              <td><?php
                if ($w['days_left'] < 0) echo '<span class="badge bg-danger">Expired</span>';
                elseif ($w['days_left'] <= 30) echo '<span class="badge bg-warning text-dark">'.$w['days_left'].'d</span>';
                else echo '<span class="badge bg-success">'.$w['days_left'].'d</span>';
              ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php elseif ($report === 'inventory'): ?>
<div class="table-card">
  <table class="table table-sm" id="reportTable">
    <thead><tr><th>Code</th><th>Name</th><th>Category</th><th>Brand/Model</th><th>Serial</th><th>Status</th><th>Assigned To</th><th>Department</th><th>Vendor</th><th>Purchase</th><th>Warranty</th><th>Price</th></tr></thead>
    <tbody>
      <?php foreach ($data as $a): ?>
      <tr>
        <td class="mono" style="font-size:11px;"><?= h($a['asset_code']) ?></td>
        <td class="fw-semibold"><?= h($a['name']) ?></td>
        <td><?= h($a['category'] ?? '—') ?></td>
        <td style="font-size:12px;"><?= h($a['brand'].' '.$a['model']) ?></td>
        <td class="mono" style="font-size:11px;"><?= h($a['serial_number'] ?? '—') ?></td>
        <td><?= assetStatusBadge($a['status']) ?></td>
        <td style="font-size:12px;"><?= h($a['assigned_to'] ?? '—') ?></td>
        <td style="font-size:12px;"><?= h($a['department'] ?? '—') ?></td>
        <td style="font-size:12px;"><?= h($a['vendor'] ?? '—') ?></td>
        <td style="font-size:12px;"><?= formatDate($a['purchase_date']) ?></td>
        <td class="<?= expiryClass($a['warranty_expiry']) ?>" style="font-size:12px;"><?= formatDate($a['warranty_expiry']) ?></td>
        <td class="mono" style="font-size:12px;"><?= formatMoney($a['price']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php
// Chart data for overview
if ($report === 'overview'):
  $monthLabels = json_encode(array_column($data['monthly_spend'],'month'));
  $monthSpend  = json_encode(array_map('floatval', array_column($data['monthly_spend'],'spend')));
  $monthCount  = json_encode(array_map('intval', array_column($data['monthly_spend'],'purchases')));
  $deptLabels  = json_encode(array_column($data['dept_cost'],'name'));
  $deptValues  = json_encode(array_map('floatval', array_column($data['dept_cost'],'cost')));
endif;

if ($report === 'cost_analysis'):
  $costLabels  = json_encode(array_column($data,'category'));
  $costValues  = json_encode(array_map('floatval', array_column($data,'total_cost')));
endif;

$extraJs = <<<JS
<script>
const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
const gridColor = isDark ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.06)';
const textColor = isDark ? '#7d8590' : '#718096';
Chart.defaults.color = textColor;
Chart.defaults.borderColor = gridColor;

JS;

if ($report === 'overview') {
    $extraJs .= <<<JS
// Monthly spend
const monthCtx = document.getElementById('monthlyChart');
if (monthCtx) {
  new Chart(monthCtx, {
    type:'bar',
    data:{
      labels:{$monthLabels},
      datasets:[
        {label:'Purchases',data:{$monthCount},backgroundColor:'rgba(37,99,235,.7)',borderRadius:4,yAxisID:'y'},
        {label:'Spend (USD)',data:{$monthSpend},type:'line',borderColor:'#16a34a',backgroundColor:'rgba(22,163,74,.1)',fill:true,tension:.4,yAxisID:'y1'}
      ]
    },
    options:{responsive:true,plugins:{legend:{position:'top'}},scales:{
      y:{grid:{color:gridColor},ticks:{color:textColor,precision:0}},
      y1:{position:'right',grid:{display:false},ticks:{color:textColor,callback:v=>'$'+v.toLocaleString()}},
      x:{grid:{display:false},ticks:{color:textColor}}
    }}
  });
}

const deptCtx = document.getElementById('deptChart');
if (deptCtx) {
  new Chart(deptCtx, {
    type:'doughnut',
    data:{labels:{$deptLabels},datasets:[{data:{$deptValues},backgroundColor:['#2563eb','#16a34a','#d97706','#dc2626','#7c3aed','#0891b2'],borderWidth:2,borderColor:isDark?'#161b22':'#fff'}]},
    options:{responsive:true,cutout:'65%',plugins:{legend:{position:'bottom'}}}
  });
}
JS;
} elseif ($report === 'cost_analysis') {
    $extraJs .= <<<JS
const costCtx = document.getElementById('costChart');
if (costCtx) {
  new Chart(costCtx, {
    type:'doughnut',
    data:{labels:{$costLabels},datasets:[{data:{$costValues},backgroundColor:['#2563eb','#16a34a','#d97706','#dc2626','#7c3aed','#0891b2','#f59e0b','#6366f1'],borderWidth:2,borderColor:isDark?'#161b22':'#fff'}]},
    options:{responsive:true,cutout:'60%',plugins:{legend:{position:'bottom'}}}
  });
}
JS;
}

$extraJs .= '</script>';

include APP_ROOT . '/includes/footer.php';
?>
