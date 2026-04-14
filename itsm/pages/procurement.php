<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requirePermission('procurement', 'view');

$pageTitle  = 'Procurement';
$breadcrumb = [['label' => 'Procurement', 'active' => true]];

$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));

$where  = [];
$params = [];
if ($search) { $where[] = "(po.po_number LIKE ? OR v.name LIKE ?)"; $s="%$search%"; $params=array_merge($params,[$s,$s]); }
if ($status) { $where[] = "po.status = ?"; $params[] = $status; }
$whereStr = $where ? 'WHERE '.implode(' AND ',$where) : '';

$baseQuery = "SELECT po.*, v.name as vendor_name, d.name as dept_name, u.full_name as created_by_name,
    (SELECT COUNT(*) FROM purchase_order_items poi WHERE poi.po_id=po.id) as item_count
    FROM purchase_orders po
    LEFT JOIN vendors v  ON po.vendor_id=v.id
    LEFT JOIN departments d ON po.department_id=d.id
    LEFT JOIN users u ON po.created_by=u.id
    $whereStr ORDER BY po.created_at DESC";

$pag     = paginate($db, $baseQuery, $params, $page);
$vendors = $db->query("SELECT id,name FROM vendors WHERE status='active' ORDER BY name")->fetchAll();
$depts   = $db->query("SELECT id,name FROM departments ORDER BY name")->fetchAll();

// Summary
$summary = [
    'total'    => $db->query("SELECT COUNT(*) FROM purchase_orders")->fetchColumn(),
    'draft'    => $db->query("SELECT COUNT(*) FROM purchase_orders WHERE status='draft'")->fetchColumn(),
    'approved' => $db->query("SELECT COUNT(*) FROM purchase_orders WHERE status='approved'")->fetchColumn(),
    'spend'    => $db->query("SELECT COALESCE(SUM(total_amount),0) FROM purchase_orders WHERE status='received'")->fetchColumn(),
];

// Single PO view
$viewPO = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $stmt = $db->prepare("SELECT po.*, v.name as vendor_name, d.name as dept_name, u.full_name as created_by_name
        FROM purchase_orders po LEFT JOIN vendors v ON po.vendor_id=v.id LEFT JOIN departments d ON po.department_id=d.id
        LEFT JOIN users u ON po.created_by=u.id WHERE po.id=?");
    $stmt->execute([(int)$_GET['id']]);
    $viewPO = $stmt->fetch();
    if ($viewPO) {
        $istmt = $db->prepare("SELECT * FROM purchase_order_items WHERE po_id=? ORDER BY id");
        $istmt->execute([$viewPO['id']]);
        $viewPO['items'] = $istmt->fetchAll();
    }
}

$filterBase = '?'.http_build_query(array_filter(['q'=>$search,'status'=>$status]));
$statusColors = ['draft'=>'secondary','approved'=>'primary','received'=>'success','cancelled'=>'danger'];

include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-cart3"></i> Procurement</h1>
  <div class="d-flex gap-2">
    <?php if ($auth->can('procurement','export')): ?>
    <button class="btn btn-sm btn-outline-secondary" onclick="exportTable('poTable','procurement_export')"><i class="bi bi-download me-1"></i>Export</button>
    <?php endif; ?>
    <?php if ($auth->can('procurement','add')): ?>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddPO"><i class="bi bi-plus-lg me-1"></i>New Purchase Order</button>
    <?php endif; ?>
  </div>
</div>

<!-- Summary -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="stat-card primary"><div class="stat-icon primary"><i class="bi bi-file-earmark-text"></i></div><div class="stat-number"><?= $summary['total'] ?></div><div class="stat-label">Total POs</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card warning"><div class="stat-icon warning"><i class="bi bi-clock"></i></div><div class="stat-number"><?= $summary['draft'] ?></div><div class="stat-label">Pending Approval</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card info"><div class="stat-icon info"><i class="bi bi-check-circle"></i></div><div class="stat-number"><?= $summary['approved'] ?></div><div class="stat-label">Approved</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card success"><div class="stat-icon success"><i class="bi bi-currency-dollar"></i></div><div class="stat-number" style="font-size:18px;"><?= formatMoney($summary['spend']) ?></div><div class="stat-label">Total Received Spend</div></div></div>
</div>

<?php if ($viewPO): ?>
<!-- PO Detail View -->
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0 fw-bold">PO: <span class="text-primary mono"><?= h($viewPO['po_number']) ?></span></h4>
  <div class="d-flex gap-2">
    <?php if ($auth->can('procurement','edit') && $viewPO['status']==='draft'): ?>
    <button class="btn btn-sm btn-success" onclick="updatePOStatus(<?= $viewPO['id'] ?>,'approved')"><i class="bi bi-check2 me-1"></i>Approve</button>
    <button class="btn btn-sm btn-info text-white" onclick="updatePOStatus(<?= $viewPO['id'] ?>,'received')"><i class="bi bi-box-seam me-1"></i>Mark Received</button>
    <button class="btn btn-sm btn-outline-danger" onclick="updatePOStatus(<?= $viewPO['id'] ?>,'cancelled')"><i class="bi bi-x me-1"></i>Cancel</button>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/pages/procurement.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
</div>

<div class="row g-4">
  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-info-circle text-primary"></i> PO Details</div>
      <div class="card-body">
        <?php
        $fields = [
          'Status'     => '<span class="badge bg-'.($statusColors[$viewPO['status']]??'secondary').'">'.ucfirst($viewPO['status']).'</span>',
          'Vendor'     => h($viewPO['vendor_name']??'—'),
          'Department' => h($viewPO['dept_name']??'—'),
          'Created By' => h($viewPO['created_by_name']??'—'),
          'Order Date' => formatDate($viewPO['ordered_at']),
          'Received'   => formatDate($viewPO['received_at']),
          'Total'      => '<strong class="text-success">'.formatMoney((float)$viewPO['total_amount']).'</strong>',
        ];
        foreach ($fields as $label => $val): ?>
        <div class="mb-3">
          <div class="text-muted" style="font-size:11px;text-transform:uppercase;font-weight:700;letter-spacing:.6px;"><?= $label ?></div>
          <div><?= $val ?></div>
        </div>
        <?php endforeach; ?>
        <?php if ($viewPO['notes']): ?>
        <div class="mb-2">
          <div class="text-muted" style="font-size:11px;text-transform:uppercase;font-weight:700;letter-spacing:.6px;">Notes</div>
          <div style="font-size:13px;"><?= h($viewPO['notes']) ?></div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <div class="card">
      <div class="card-header"><i class="bi bi-list-check text-primary"></i> Line Items</div>
      <div class="card-body p-0">
        <table class="table mb-0">
          <thead><tr><th>#</th><th>Description</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>
          <tbody>
            <?php foreach ($viewPO['items'] as $i => $item): ?>
            <tr>
              <td class="text-muted"><?= $i+1 ?></td>
              <td><?= h($item['description']) ?></td>
              <td><?= number_format($item['quantity']) ?></td>
              <td class="mono"><?= formatMoney($item['unit_price']) ?></td>
              <td class="mono fw-semibold"><?= formatMoney($item['total_price']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($viewPO['items'])): ?>
            <tr><td colspan="5" class="text-center py-3 text-muted">No items</td></tr>
            <?php endif; ?>
          </tbody>
          <tfoot>
            <tr class="table-active fw-bold">
              <td colspan="4" class="text-end">Grand Total:</td>
              <td class="mono text-success"><?= formatMoney((float)$viewPO['total_amount']) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>

<?php else: ?>
<!-- PO List View -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-4"><input type="text" name="q" class="form-control form-control-sm" placeholder="Search PO number or vendor..." value="<?= h($search) ?>"></div>
      <div class="col-md-2">
        <select name="status" class="form-select form-select-sm">
          <option value="">All Status</option>
          <?php foreach(['draft','approved','received','cancelled'] as $s): ?>
          <option value="<?=$s?>" <?=$status===$s?'selected':''?>><?=ucfirst($s)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
        <a href="<?= APP_URL ?>/pages/procurement.php" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="table-card">
  <div class="table-toolbar"><span class="text-muted small">Showing <strong><?= count($pag['records']) ?></strong> of <strong><?= $pag['total'] ?></strong> purchase orders</span></div>
  <div class="table-responsive">
    <table class="table table-hover" id="poTable">
      <thead><tr><th>PO Number</th><th>Vendor</th><th>Department</th><th>Items</th><th>Total</th><th>Order Date</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php if (empty($pag['records'])): ?>
        <tr><td colspan="8" class="text-center py-4 text-muted">No purchase orders found</td></tr>
        <?php endif; ?>
        <?php foreach ($pag['records'] as $po): ?>
        <tr>
          <td><a href="<?= APP_URL ?>/pages/procurement.php?id=<?= $po['id'] ?>" class="mono fw-semibold text-primary text-decoration-none"><?= h($po['po_number']) ?></a></td>
          <td><?= h($po['vendor_name']??'—') ?></td>
          <td><?= h($po['dept_name']??'—') ?></td>
          <td><span class="badge bg-secondary-subtle text-secondary"><?= $po['item_count'] ?> items</span></td>
          <td class="mono fw-semibold"><?= formatMoney((float)$po['total_amount']) ?></td>
          <td style="font-size:12px;"><?= formatDate($po['ordered_at']) ?></td>
          <td><span class="badge bg-<?= $statusColors[$po['status']]??'secondary' ?>"><?= ucfirst($po['status']) ?></span></td>
          <td class="text-end">
            <div class="d-flex justify-content-end gap-1">
              <a href="<?= APP_URL ?>/pages/procurement.php?id=<?= $po['id'] ?>" class="btn btn-icon btn-sm btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
              <?php if ($auth->can('procurement','edit') && $po['status']==='draft'): ?>
              <button class="btn btn-icon btn-sm btn-outline-success" onclick="updatePOStatus(<?=$po['id']?>,'approved')" title="Approve"><i class="bi bi-check2"></i></button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="table-footer">
    <span><?= $pag['total'] ?> records</span>
    <?= renderPagination($pag, APP_URL.'/pages/procurement.php'.$filterBase) ?>
  </div>
</div>
<?php endif; ?>

<!-- Add PO Modal -->
<?php if ($auth->can('procurement','add')): ?>
<div class="modal fade" id="modalAddPO" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-cart-plus text-primary me-2"></i>New Purchase Order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form action="<?= APP_URL ?>/actions/po_save.php" method="post" id="poForm">
        <?= $auth->csrfField() ?>
        <div class="modal-body">
          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <label class="form-label">Vendor</label>
              <select name="vendor_id" class="form-select">
                <option value="">Select vendor</option>
                <?php foreach($vendors as $v): ?><option value="<?=$v['id']?>"><?=h($v['name'])?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Department</label>
              <select name="department_id" class="form-select">
                <option value="">Select department</option>
                <?php foreach($depts as $d): ?><option value="<?=$d['id']?>"><?=h($d['name'])?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Order Date</label>
              <input type="date" name="ordered_at" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0"><i class="bi bi-list-check me-2 text-primary"></i>Line Items</h6>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addPOItem()"><i class="bi bi-plus-lg me-1"></i>Add Item</button>
          </div>

          <div class="table-responsive">
            <table class="table table-sm" id="poItemsTable">
              <thead><tr><th>Description</th><th style="width:100px;">Qty</th><th style="width:140px;">Unit Price</th><th style="width:140px;">Total</th><th style="width:40px;"></th></tr></thead>
              <tbody id="poItems">
                <tr id="itemRow0">
                  <td><input type="text" name="items[0][description]" class="form-control form-control-sm" required placeholder="Item description"></td>
                  <td><input type="number" name="items[0][quantity]" class="form-control form-control-sm item-qty" value="1" min="1" oninput="calcRow(0)"></td>
                  <td><input type="number" name="items[0][unit_price]" class="form-control form-control-sm item-price" step="0.01" value="0" min="0" oninput="calcRow(0)"></td>
                  <td><input type="text" name="items[0][total]" class="form-control form-control-sm item-total mono" value="0.00" readonly></td>
                  <td><button type="button" class="btn btn-icon btn-sm btn-outline-danger" onclick="removePOItem(0)"><i class="bi bi-x"></i></button></td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="fw-bold table-active"><td colspan="3" class="text-end">Grand Total:</td><td colspan="2" id="poGrandTotal" class="mono text-success">$0.00</td></tr>
              </tfoot>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Create PO</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
let poRowCount = 1;

function addPOItem() {
  const i = poRowCount++;
  const tbody = document.getElementById('poItems');
  const row = document.createElement('tr');
  row.id = `itemRow${i}`;
  row.innerHTML = `
    <td><input type="text" name="items[${i}][description]" class="form-control form-control-sm" required placeholder="Item description"></td>
    <td><input type="number" name="items[${i}][quantity]" class="form-control form-control-sm item-qty" value="1" min="1" oninput="calcRow(${i})"></td>
    <td><input type="number" name="items[${i}][unit_price]" class="form-control form-control-sm item-price" step="0.01" value="0" min="0" oninput="calcRow(${i})"></td>
    <td><input type="text" name="items[${i}][total]" class="form-control form-control-sm item-total mono" value="0.00" readonly></td>
    <td><button type="button" class="btn btn-icon btn-sm btn-outline-danger" onclick="removePOItem(${i})"><i class="bi bi-x"></i></button></td>`;
  tbody.appendChild(row);
}

function removePOItem(i) {
  const row = document.getElementById(`itemRow${i}`);
  if (row && document.querySelectorAll('#poItems tr').length > 1) { row.remove(); updateGrandTotal(); }
}

function calcRow(i) {
  const row = document.getElementById(`itemRow${i}`);
  if (!row) return;
  const qty   = parseFloat(row.querySelector('.item-qty').value) || 0;
  const price = parseFloat(row.querySelector('.item-price').value) || 0;
  const total = qty * price;
  row.querySelector('.item-total').value = total.toFixed(2);
  updateGrandTotal();
}

function updateGrandTotal() {
  let grand = 0;
  document.querySelectorAll('.item-total').forEach(el => grand += parseFloat(el.value)||0);
  document.getElementById('poGrandTotal').textContent = '$' + grand.toLocaleString('en-US',{minimumFractionDigits:2});
}

async function updatePOStatus(id, status) {
  const labels = {approved:'Approve',received:'Mark as Received',cancelled:'Cancel'};
  if (!confirm(`${labels[status]??'Update'} this PO?`)) return;
  const res = await api('<?= APP_URL ?>/actions/po_status.php', { id, status });
  if (res.success) { Toast.show(res.message,'success'); setTimeout(()=>location.reload(),800); }
  else Toast.show(res.message||'Error','danger');
}

document.getElementById('poForm')?.addEventListener('submit', function(e) {
  const btn = this.querySelector('[type=submit]');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Creating...';
});
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
