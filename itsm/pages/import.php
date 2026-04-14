<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requirePermission('assets', 'add');

$pageTitle  = 'Bulk Import';
$breadcrumb = [['label' => 'Bulk Import', 'active' => true]];

$categories  = $db->query("SELECT * FROM asset_categories ORDER BY name")->fetchAll();
$departments = $db->query("SELECT * FROM departments ORDER BY name")->fetchAll();
$vendors     = $db->query("SELECT * FROM vendors WHERE status='active' ORDER BY name")->fetchAll();
$roles       = $db->query("SELECT * FROM roles ORDER BY name")->fetchAll();

include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-file-earmark-arrow-up"></i> Bulk Import</h1>
</div>

<div class="row g-4">
  <!-- Import Assets -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header"><i class="bi bi-laptop text-primary"></i> Import Assets (CSV)</div>
      <div class="card-body">
        <div class="alert alert-info small mb-3 d-flex gap-2">
          <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
          <div>Upload a CSV file with asset data. Download the template below to see the required format.</div>
        </div>
        <div class="mb-3">
          <a href="<?= APP_URL ?>/actions/import_template.php?type=assets" class="btn btn-sm btn-outline-success">
            <i class="bi bi-download me-1"></i>Download Asset CSV Template
          </a>
        </div>
        <form action="<?= APP_URL ?>/actions/import_assets.php" method="post" enctype="multipart/form-data" id="assetImportForm">
          <?= $auth->csrfField() ?>
          <div class="mb-3">
            <label class="form-label">CSV File <span class="text-danger">*</span></label>
            <input type="file" name="csv_file" class="form-control" accept=".csv,.txt" required>
            <div class="form-text">Max 5MB. Must have headers matching the template.</div>
          </div>
          <div class="mb-3">
            <div class="form-check">
              <input type="checkbox" name="skip_errors" value="1" class="form-check-input" id="skipErrors">
              <label class="form-check-label" for="skipErrors">Skip rows with errors and continue</label>
            </div>
          </div>
          <button type="submit" class="btn btn-primary w-100" id="assetImportBtn">
            <i class="bi bi-upload me-1"></i>Import Assets
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Import Users -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header"><i class="bi bi-people text-success"></i> Import Employees (CSV)</div>
      <div class="card-body">
        <div class="alert alert-info small mb-3 d-flex gap-2">
          <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
          <div>Import employees with role and department assignments. Passwords will be auto-generated and emailed.</div>
        </div>
        <div class="mb-3">
          <a href="<?= APP_URL ?>/actions/import_template.php?type=users" class="btn btn-sm btn-outline-success">
            <i class="bi bi-download me-1"></i>Download Employee CSV Template
          </a>
        </div>
        <form action="<?= APP_URL ?>/actions/import_users.php" method="post" enctype="multipart/form-data" id="userImportForm">
          <?= $auth->csrfField() ?>
          <div class="mb-3">
            <label class="form-label">CSV File <span class="text-danger">*</span></label>
            <input type="file" name="csv_file" class="form-control" accept=".csv,.txt" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Default Role for New Users</label>
            <select name="default_role_id" class="form-select">
              <?php foreach($roles as $r): ?>
              <option value="<?=$r['id']?>"><?=h($r['name'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Default Department</label>
            <select name="default_dept_id" class="form-select">
              <option value="">None</option>
              <?php foreach($departments as $d): ?><option value="<?=$d['id']?>"><?=h($d['name'])?></option><?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-success w-100" id="userImportBtn">
            <i class="bi bi-upload me-1"></i>Import Employees
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Results area -->
<div id="importResults" class="mt-4 d-none">
  <div class="card">
    <div class="card-header"><i class="bi bi-list-check text-primary"></i> Import Results</div>
    <div class="card-body" id="importResultsBody"></div>
  </div>
</div>

<!-- CSV Format Reference -->
<div class="card mt-4">
  <div class="card-header"><i class="bi bi-table text-info"></i> CSV Format Reference</div>
  <div class="card-body">
    <div class="row g-4">
      <div class="col-md-6">
        <h6 class="fw-bold mb-2">Assets CSV Columns</h6>
        <div class="table-responsive">
          <table class="table table-sm table-bordered">
            <thead class="table-light"><tr><th>Column</th><th>Required</th><th>Example</th></tr></thead>
            <tbody>
              <?php
              $assetCols = [
                ['name','Yes','Dell Laptop'],['brand','No','Dell'],['model','No','Latitude 5520'],
                ['serial_number','No','SN123456'],['category','No','Laptop'],['purchase_date','No','2024-01-15'],
                ['warranty_expiry','No','2027-01-15'],['price','No','1500.00'],['status','No','available'],
                ['location','No','Office A'],['notes','No','Any notes'],
              ];
              foreach ($assetCols as [$col,$req,$ex]): ?>
              <tr>
                <td class="mono" style="font-size:12px;"><?=$col?></td>
                <td><?=$req==='Yes'?'<span class="badge bg-danger-subtle text-danger">Yes</span>':'<span class="text-muted">No</span>'?></td>
                <td style="font-size:12px;"><?=h($ex)?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="col-md-6">
        <h6 class="fw-bold mb-2">Employees CSV Columns</h6>
        <div class="table-responsive">
          <table class="table table-sm table-bordered">
            <thead class="table-light"><tr><th>Column</th><th>Required</th><th>Example</th></tr></thead>
            <tbody>
              <?php
              $userCols = [
                ['full_name','Yes','John Smith'],['email','Yes','john@company.com'],
                ['username','No','jsmith'],['phone','No','+1234567890'],
                ['job_title','No','IT Engineer'],['employee_id','No','EMP001'],
                ['department','No','Information Technology'],['role','No','IT Staff'],
              ];
              foreach ($userCols as [$col,$req,$ex]): ?>
              <tr>
                <td class="mono" style="font-size:12px;"><?=$col?></td>
                <td><?=$req==='Yes'?'<span class="badge bg-danger-subtle text-danger">Yes</span>':'<span class="text-muted">No</span>'?></td>
                <td style="font-size:12px;"><?=h($ex)?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
['assetImportForm','userImportForm'].forEach(formId => {
  document.getElementById(formId)?.addEventListener('submit', function(e) {
    const btn = this.querySelector('[type=submit]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Importing...';
  });
});
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
