<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requirePermission('assets', 'view');

$pageTitle  = __('asset_title');
$breadcrumb = [['label' => __('asset_title'), 'active' => true]];

// Filters
$search     = trim($_GET['q'] ?? '');
$status     = $_GET['status'] ?? '';
$categoryId = (int)($_GET['category'] ?? 0);
$deptId     = (int)($_GET['dept'] ?? 0);
$page       = max(1, (int)($_GET['page'] ?? 1));

$where  = [];
$params = [];

if ($search) {
    $where[] = "(a.name LIKE ? OR a.asset_code LIKE ? OR a.serial_number LIKE ? OR a.brand LIKE ? OR a.model LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s,$s,$s,$s,$s]);
}
if ($status) { $where[] = "a.status = ?"; $params[] = $status; }
if ($categoryId) { $where[] = "a.category_id = ?"; $params[] = $categoryId; }
if ($deptId) { $where[] = "a.department_id = ?"; $params[] = $deptId; }

$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$baseQuery = "SELECT a.*, ac.name as category_name, ac.icon as category_icon,
               u.full_name as assigned_user, d.name as dept_name, v.name as vendor_name
               FROM assets a
               LEFT JOIN asset_categories ac ON a.category_id = ac.id
               LEFT JOIN users u ON a.assigned_to = u.id
               LEFT JOIN departments d ON a.department_id = d.id
               LEFT JOIN vendors v ON a.vendor_id = v.id
               $whereStr
               ORDER BY a.created_at DESC";

$pag = paginate($db, $baseQuery, $params, $page);

$categories = $db->query("SELECT * FROM asset_categories ORDER BY name")->fetchAll();
$departments = $db->query("SELECT * FROM departments ORDER BY name")->fetchAll();
$vendors     = $db->query("SELECT * FROM vendors WHERE status='active' ORDER BY name")->fetchAll();
$employees   = $db->query("SELECT id, full_name, employee_id FROM users WHERE status='active' ORDER BY full_name")->fetchAll();

// Single asset view
$viewAsset = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $stmt = $db->prepare("SELECT a.*, ac.name as category_name, u.full_name as assigned_user,
        d.name as dept_name, v.name as vendor_name FROM assets a
        LEFT JOIN asset_categories ac ON a.category_id = ac.id
        LEFT JOIN users u ON a.assigned_to = u.id
        LEFT JOIN departments d ON a.department_id = d.id
        LEFT JOIN vendors v ON a.vendor_id = v.id
        WHERE a.id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $viewAsset = $stmt->fetch();

    // Assignment history
    if ($viewAsset) {
        $historyStmt = $db->prepare(
            "SELECT aa.*, u.full_name as user_name, ab.full_name as assigned_by_name
             FROM asset_assignments aa
             LEFT JOIN users u ON aa.user_id = u.id
             LEFT JOIN users ab ON aa.assigned_by = ab.id
             WHERE aa.asset_id = ? ORDER BY aa.assigned_at DESC"
        );
        $historyStmt->execute([$viewAsset['id']]);
        $viewAsset['history'] = $historyStmt->fetchAll();

        $docStmt = $db->prepare("SELECT * FROM documents WHERE asset_id = ? ORDER BY created_at DESC");
        $docStmt->execute([$viewAsset['id']]);
        $viewAsset['documents'] = $docStmt->fetchAll();
    }
}

$filterBase = '?' . http_build_query(array_filter(['q'=>$search,'status'=>$status,'category'=>$categoryId,'dept'=>$deptId]));

include APP_ROOT . '/includes/header.php';
?>

<?php if ($viewAsset): ?>
<!-- ── Asset Detail View ────────────────────────────────── -->
<div class="page-header">
  <h1 class="page-title"><i class="bi bi-laptop"></i> <?= h($viewAsset['name']) ?></h1>
  <div class="d-flex gap-2">
    <?php if ($auth->can('assets','edit')): ?>
      <button class="btn btn-sm btn-outline-primary" onclick="editAsset(<?= $viewAsset['id'] ?>)">
        <i class="bi bi-pencil me-1"></i>Edit
      </button>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/pages/assets.php" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>
  </div>
</div>

<div class="row g-4">
  <div class="col-md-8">
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-info-circle text-primary"></i> Asset Information</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-sm-6">
            <div class="text-muted" style="font-size:11px;text-transform:uppercase;font-weight:700;letter-spacing:.6px;">Asset Code</div>
            <div class="mono fw-semibold"><?= h($viewAsset['asset_code']) ?></div>
          </div>
          <div class="col-sm-6">
            <div class="text-muted" style="font-size:11px;text-transform:uppercase;font-weight:700;letter-spacing:.6px;">Status</div>
            <div><?= assetStatusBadge($viewAsset['status']) ?></div>
          </div>
          <div class="col-sm-6">
            <div class="text-muted" style="font-size:11px;text-transform:uppercase;font-weight:700;letter-spacing:.6px;">Category</div>
            <div><?= h($viewAsset['category_name'] ?? '-') ?></div>
          </div>
          <div class="col-sm-6">
            <div class="text-muted" style="font-size:11px;text-transform:uppercase;font-weight:700;letter-spacing:.6px;">Brand / Model</div>
            <div><?= h(($viewAsset['brand'] ?? '') . ' ' . ($viewAsset['model'] ?? '')) ?></div>
          </div>
          <div class="col-sm-6">
            <div class="text-muted" style="font-size:11px;text-transform:uppercase;font-weight:700;letter-spacing:.6px;">Serial Number</div>
            <div class="mono"><?= h($viewAsset['serial_number'] ?? '-') ?></div>
          </div>
          <div class="col-sm-6">
            <div class="text-muted" style="font-size:11px;text-transform:uppercase;font-weight:700;letter-spacing:.6px;">Assigned To</div>
            <div><?= h($viewAsset['assigned_user'] ?? 'Unassigned') ?></div>
          </div>
          <div class="col-sm-6">
            <div class="text-muted" style="font-size:11px;text-transform:uppercase;font-weight:700;letter-spacing:.6px;">Department</div>
            <div><?= h($viewAsset['dept_name'] ?? '-') ?></div>
          </div>
          <div class="col-sm-6">
            <div class="text-muted" style="font-size:11px;text-transform:uppercase;font-weight:700;letter-spacing:.6px;">Vendor</div>
            <div><?= h($viewAsset['vendor_name'] ?? '-') ?></div>
          </div>
          <div class="col-sm-6">
            <div class="text-muted" style="font-size:11px;text-transform:uppercase;font-weight:700;letter-spacing:.6px;">Purchase Date</div>
            <div><?= formatDate($viewAsset['purchase_date']) ?></div>
          </div>
          <div class="col-sm-6">
            <div class="text-muted" style="font-size:11px;text-transform:uppercase;font-weight:700;letter-spacing:.6px;">Warranty Expiry</div>
            <div class="<?= expiryClass($viewAsset['warranty_expiry']) ?>"><?= formatDate($viewAsset['warranty_expiry']) ?></div>
          </div>
          <div class="col-sm-6">
            <div class="text-muted" style="font-size:11px;text-transform:uppercase;font-weight:700;letter-spacing:.6px;">Purchase Price</div>
            <div class="fw-semibold"><?= formatMoney((float)$viewAsset['price']) ?></div>
          </div>
          <div class="col-sm-6">
            <div class="text-muted" style="font-size:11px;text-transform:uppercase;font-weight:700;letter-spacing:.6px;">Location</div>
            <div><?= h($viewAsset['location'] ?? '-') ?></div>
          </div>
          <?php if ($viewAsset['notes']): ?>
          <div class="col-12">
            <div class="text-muted" style="font-size:11px;text-transform:uppercase;font-weight:700;letter-spacing:.6px;">Notes</div>
            <div class="text-truncate-2"><?= h($viewAsset['notes']) ?></div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Assignment History -->
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-clock-history text-primary"></i> Assignment History</div>
      <div class="card-body p-0">
        <?php if (empty($viewAsset['history'])): ?>
          <p class="text-muted text-center py-3">No assignment history</p>
        <?php else: ?>
        <table class="table table-sm mb-0">
          <thead><tr><th>User</th><th>Assigned</th><th>Returned</th><th>By</th></tr></thead>
          <tbody>
            <?php foreach ($viewAsset['history'] as $h): ?>
            <tr>
              <td><?= h($h['user_name'] ?? '-') ?></td>
              <td class="mono" style="font-size:12px;"><?= formatDate($h['assigned_at'], 'd M Y') ?></td>
              <td class="mono" style="font-size:12px;"><?= $h['returned_at'] ? formatDate($h['returned_at'], 'd M Y') : '<span class="badge bg-success">Current</span>' ?></td>
              <td><?= h($h['assigned_by_name'] ?? '-') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>

    <!-- Documents -->
    <?php if (!empty($viewAsset['documents'])): ?>
    <div class="card">
      <div class="card-header"><i class="bi bi-folder text-primary"></i> Linked Documents</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead><tr><th>Title</th><th>Category</th><th>Uploaded</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($viewAsset['documents'] as $doc): ?>
            <tr>
              <td><?= h($doc['title']) ?></td>
              <td><span class="badge bg-secondary-subtle text-secondary"><?= h($doc['category']) ?></span></td>
              <td style="font-size:12px;"><?= formatDate($doc['created_at']) ?></td>
              <td><a href="<?= APP_URL ?>/actions/download.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i></a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Barcode -->
  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-upc-scan text-primary"></i> Barcode</div>
      <div class="card-body text-center">
        <div class="barcode-wrapper mb-2 d-inline-block">
          <svg id="assetBarcode" data-barcode="<?= h($viewAsset['barcode'] ?? $viewAsset['asset_code']) ?>"></svg>
        </div>
        <div class="mb-2">
          <canvas id="assetQR" data-qr="<?= h($viewAsset['asset_code']) ?>"></canvas>
        </div>
        <div class="text-muted" style="font-size:12px;"><?= h($viewAsset['asset_code']) ?></div>
        <button class="btn btn-sm btn-outline-secondary mt-2" onclick="window.print()">
          <i class="bi bi-printer me-1"></i>Print Label
        </button>
      </div>
    </div>

    <?php if ($auth->can('assets','edit')): ?>
    <div class="card">
      <div class="card-header"><i class="bi bi-person-plus text-primary"></i> Quick Assign</div>
      <div class="card-body">
        <form data-ajax action="<?= APP_URL ?>/actions/asset_assign.php" method="post">
          <?= $auth->csrfField() ?>
          <input type="hidden" name="asset_id" value="<?= $viewAsset['id'] ?>">
          <div class="mb-3">
            <label class="form-label">Assign To Employee</label>
            <select name="user_id" class="form-select form-select-sm">
              <option value="">— Unassign —</option>
              <?php foreach ($employees as $emp): ?>
              <option value="<?= $emp['id'] ?>" <?= $viewAsset['assigned_to'] == $emp['id'] ? 'selected' : '' ?>>
                <?= h($emp['full_name']) ?> (<?= h($emp['employee_id']) ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control form-control-sm" rows="2"></textarea>
          </div>
          <button type="submit" class="btn btn-primary btn-sm w-100">
            <i class="bi bi-save me-1"></i>Update Assignment
          </button>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>
<!-- ── Assets List View ─────────────────────────────────── -->
<div class="page-header">
  <h1 class="page-title"><i class="bi bi-laptop"></i> Assets</h1>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($auth->can('assets','export')): ?>
    <button class="btn btn-sm btn-outline-secondary" onclick="exportTable('assetsTable','assets_export')">
      <i class="bi bi-download me-1"></i>Export CSV
    </button>
    <?php endif; ?>
    <?php if ($auth->can('assets','add')): ?>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddAsset">
      <i class="bi bi-plus-lg me-1"></i>Add Asset
    </button>
    <?php endif; ?>
  </div>
</div>

<!-- Filter Bar -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-3">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Search assets..." value="<?= h($search) ?>">
      </div>
      <div class="col-md-2">
        <select name="status" class="form-select form-select-sm">
          <option value="">All Status</option>
          <?php foreach (['available','assigned','maintenance','retired'] as $s): ?>
          <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="category" class="form-select form-select-sm">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>><?= h($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="dept" class="form-select form-select-sm">
          <option value="">All Departments</option>
          <?php foreach ($departments as $dept): ?>
          <option value="<?= $dept['id'] ?>" <?= $deptId == $dept['id'] ? 'selected' : '' ?>><?= h($dept['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
        <a href="<?= APP_URL ?>/pages/assets.php" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="table-card">
  <div class="table-toolbar">
    <div class="table-toolbar-left">
      <span class="text-muted small">
        Showing <strong><?= count($pag['records']) ?></strong> of <strong><?= number_format($pag['total']) ?></strong> assets
      </span>
    </div>
    <div class="table-toolbar-right">
      <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalQrScan">
        <i class="bi bi-qr-code-scan me-1"></i>Scan
      </button>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-hover" id="assetsTable">
      <thead>
        <tr>
          <th>Asset Code</th>
          <th>Name / Category</th>
          <th>Brand / Model</th>
          <th>Status</th>
          <th>Assigned To</th>
          <th>Department</th>
          <th>Warranty</th>
          <th>Price</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($pag['records'])): ?>
        <tr><td colspan="9" class="text-center py-4 text-muted">No assets found</td></tr>
        <?php endif; ?>
        <?php foreach ($pag['records'] as $asset):
          $daysLeft = daysUntil($asset['warranty_expiry']);
          $rowClass = ($daysLeft !== null && $daysLeft < 0) ? 'expiry-critical' :
                      ($daysLeft !== null && $daysLeft <= 30 ? 'expiry-warning' : '');
        ?>
        <tr class="<?= $rowClass ?>">
          <td>
            <a href="<?= APP_URL ?>/pages/assets.php?id=<?= $asset['id'] ?>" class="text-decoration-none">
              <span class="mono fw-semibold text-primary"><?= h($asset['asset_code']) ?></span>
            </a>
          </td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <i class="bi <?= h($asset['category_icon'] ?? 'bi-cpu') ?> text-muted"></i>
              <div>
                <div class="fw-semibold"><?= h($asset['name']) ?></div>
                <div class="text-muted" style="font-size:11px;"><?= h($asset['category_name'] ?? '-') ?></div>
              </div>
            </div>
          </td>
          <td>
            <div><?= h($asset['brand'] ?? '-') ?></div>
            <div class="text-muted" style="font-size:11px;"><?= h($asset['model'] ?? '-') ?></div>
          </td>
          <td><?= assetStatusBadge($asset['status']) ?></td>
          <td><?= h($asset['assigned_user'] ?? '<span class="text-muted">—</span>') ?></td>
          <td><?= h($asset['dept_name'] ?? '—') ?></td>
          <td>
            <span class="<?= expiryClass($asset['warranty_expiry']) ?>" style="font-size:12px;">
              <?= formatDate($asset['warranty_expiry']) ?>
            </span>
          </td>
          <td class="mono" style="font-size:12px;"><?= formatMoney((float)$asset['price']) ?></td>
          <td class="text-end">
            <div class="d-flex justify-content-end gap-1">
              <a href="<?= APP_URL ?>/pages/assets.php?id=<?= $asset['id'] ?>" class="btn btn-icon btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="View">
                <i class="bi bi-eye"></i>
              </a>
              <?php if ($auth->can('assets','edit')): ?>
              <button class="btn btn-icon btn-sm btn-outline-secondary" onclick="editAsset(<?= $asset['id'] ?>)" data-bs-toggle="tooltip" title="Edit">
                <i class="bi bi-pencil"></i>
              </button>
              <?php endif; ?>
              <?php if ($auth->can('assets','delete')): ?>
              <button class="btn btn-icon btn-sm btn-outline-danger" onclick="confirmDelete('<?= APP_URL ?>/actions/asset_delete.php?id=<?= $asset['id'] ?>&csrf_token=<?= h($auth->generateCsrfToken()) ?>')" data-bs-toggle="tooltip" title="Delete">
                <i class="bi bi-trash"></i>
              </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="table-footer">
    <span><?= $pag['total'] ?> total records</span>
    <?= renderPagination($pag, APP_URL . '/pages/assets.php' . $filterBase) ?>
  </div>
</div>

<!-- ── Add Asset Modal ──────────────────────────────────── -->
<?php if ($auth->can('assets','add')): ?>
<div class="modal fade" id="modalAddAsset" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-plus-circle text-primary me-2"></i>Add New Asset</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form data-ajax action="<?= APP_URL ?>/actions/asset_save.php" method="post">
        <?= $auth->csrfField() ?>
        <input type="hidden" name="id" value="">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Asset Name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" required placeholder="e.g. Dell Latitude 5520">
            </div>
            <div class="col-md-6">
              <label class="form-label">Category</label>
              <select name="category_id" class="form-select">
                <option value="">Select category</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= h($cat['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Brand</label>
              <input type="text" name="brand" class="form-control" placeholder="Dell, HP, Apple...">
            </div>
            <div class="col-md-4">
              <label class="form-label">Model</label>
              <input type="text" name="model" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">Serial Number</label>
              <input type="text" name="serial_number" class="form-control mono">
            </div>
            <div class="col-md-4">
              <label class="form-label">Purchase Date</label>
              <input type="date" name="purchase_date" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">Warranty Expiry</label>
              <input type="date" name="warranty_expiry" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">Price (USD)</label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" name="price" class="form-control" step="0.01" min="0" value="0">
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <option value="available">Available</option>
                <option value="assigned">Assigned</option>
                <option value="maintenance">Maintenance</option>
                <option value="retired">Retired</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Vendor</label>
              <select name="vendor_id" class="form-select">
                <option value="">Select vendor</option>
                <?php foreach ($vendors as $v): ?>
                <option value="<?= $v['id'] ?>"><?= h($v['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Department</label>
              <select name="department_id" class="form-select">
                <option value="">Select dept</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= $d['id'] ?>"><?= h($d['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Assign To Employee</label>
              <select name="assigned_to" class="form-select">
                <option value="">Not assigned</option>
                <?php foreach ($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>"><?= h($emp['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Location</label>
              <input type="text" name="location" class="form-control" placeholder="Room/Floor/Building">
            </div>
            <div class="col-12">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Asset</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- QR Scanner Modal -->
<div class="modal fade" id="modalQrScan" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-qr-code-scan me-2"></i>Scan Asset</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="stopQrScanner()"></button>
      </div>
      <div class="modal-body text-center">
        <video id="qrVideo" style="width:100%;border-radius:8px;"></video>
        <div class="mt-2">
          <input type="text" id="manualBarcode" class="form-control form-control-sm" placeholder="Or type barcode/asset code...">
          <button class="btn btn-sm btn-primary mt-2 w-100" onclick="lookupAsset()">
            <i class="bi bi-search me-1"></i>Lookup
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('modalQrScan')?.addEventListener('shown.bs.modal', () => startQrScanner());

function lookupAsset() {
  const code = document.getElementById('manualBarcode').value.trim();
  if (code) window.location.href = `<?= APP_URL ?>/pages/assets.php?q=${encodeURIComponent(code)}`;
}

async function editAsset(id) {
  const res = await api(`<?= APP_URL ?>/api/asset_get.php`, { id }, 'POST');
  if (!res.success) return Toast.show('Error loading asset', 'danger');
  const a = res.asset;
  const form = document.querySelector('#modalAddAsset form');
  form.querySelector('[name=id]').value = a.id;
  form.querySelector('[name=name]').value = a.name || '';
  form.querySelector('[name=brand]').value = a.brand || '';
  form.querySelector('[name=model]').value = a.model || '';
  form.querySelector('[name=serial_number]').value = a.serial_number || '';
  form.querySelector('[name=purchase_date]').value = a.purchase_date || '';
  form.querySelector('[name=warranty_expiry]').value = a.warranty_expiry || '';
  form.querySelector('[name=price]').value = a.price || 0;
  form.querySelector('[name=location]').value = a.location || '';
  form.querySelector('[name=notes]').value = a.notes || '';
  ['category_id','vendor_id','department_id','assigned_to','status'].forEach(f => {
    const el = form.querySelector(`[name=${f}]`);
    if (el) el.value = a[f] || '';
  });
  document.querySelector('.modal-title').innerHTML = '<i class="bi bi-pencil text-primary me-2"></i>Edit Asset';
  new bootstrap.Modal(document.getElementById('modalAddAsset')).show();
}
</script>
<?php endif; ?>

<?php include APP_ROOT . '/includes/footer.php'; ?>
