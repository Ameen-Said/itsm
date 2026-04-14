<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();

$pageTitle  = 'API Documentation';
$breadcrumb = [['label' => 'API Docs', 'active' => true]];

include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title"><i class="bi bi-code-slash"></i> REST API Documentation</h1>
  <div class="d-flex gap-2">
    <span class="badge bg-success px-3 py-2">v1.0</span>
    <span class="badge bg-primary px-3 py-2"><i class="bi bi-shield-lock me-1"></i>Bearer Auth</span>
  </div>
</div>

<div class="row g-4">
  <div class="col-md-3">
    <!-- Sidebar nav -->
    <div class="card" style="position:sticky;top:76px;">
      <div class="card-header fw-bold" style="font-size:13px;">Endpoints</div>
      <div class="card-body p-0">
        <?php
        $sections = ['Authentication','Dashboard','Assets','Users','Licenses','Vendors','Departments','Search'];
        foreach ($sections as $s): ?>
        <a href="#<?= strtolower($s) ?>" class="d-block px-3 py-2 text-decoration-none border-bottom" style="font-size:13px;color:var(--color-text);"><?= $s ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="col-md-9">
    <!-- Base URL -->
    <div class="card mb-4">
      <div class="card-body">
        <div class="d-flex align-items-center gap-3">
          <span class="badge bg-secondary px-3 py-2 mono">BASE URL</span>
          <code class="mono fs-6"><?= APP_URL ?>/api/v1/</code>
        </div>
      </div>
    </div>

    <!-- Authentication -->
    <div class="card mb-4" id="authentication">
      <div class="card-header fw-bold"><i class="bi bi-shield-lock text-warning me-2"></i>Authentication</div>
      <div class="card-body">
        <p>All API requests require an API key passed as a Bearer token in the Authorization header.</p>
        <div class="p-3 rounded mono" style="background:var(--color-bg);font-size:13px;">
          Authorization: Bearer YOUR_API_KEY
        </div>
        <div class="alert alert-warning mt-3 small">
          <i class="bi bi-info-circle me-1"></i>
          Set your API key via the <code>API_KEY</code> environment variable on the server.
        </div>
        <h6 class="fw-bold mt-3">Response Format</h6>
        <pre class="p-3 rounded mono" style="background:var(--color-bg);font-size:12px;">{
  "success": true,
  "data": { ... },
  "meta": {
    "total": 150,
    "page": 1,
    "per_page": 25,
    "total_pages": 6
  }
}</pre>
      </div>
    </div>

    <!-- Endpoints -->
    <?php
    $endpoints = [
      'Dashboard' => [
        ['GET','/dashboard','Get system summary statistics','','{"success":true,"data":{"total_assets":142,"assigned_assets":98,"active_users":23,"total_asset_value":245000.00}}'],
      ],
      'Assets' => [
        ['GET','/assets','List all assets. Query: ?q=search&status=available&page=1&per_page=25','','{"success":true,"data":[{"id":1,"asset_code":"AST-ABC-123","name":"Dell Laptop","status":"assigned",...}],"meta":{...}}'],
        ['GET','/assets/{id}','Get a single asset by ID','','{"success":true,"data":{"id":1,"asset_code":"AST-ABC-123","name":"Dell Laptop",...}}'],
        ['POST','/assets','Create a new asset','{"name":"HP ProBook","brand":"HP","model":"450 G9","serial_number":"SN12345","status":"available","price":1200.00}','{"success":true,"data":{"id":143,...}}'],
        ['PUT','/assets/{id}','Update an asset','{"status":"maintenance","notes":"Sent for repair"}','{"success":true,"data":{...}}'],
        ['DELETE','/assets/{id}','Delete an asset','','{"success":true,"data":{"deleted":true,"id":143}}'],
      ],
      'Users' => [
        ['GET','/users','List all employees. Query: ?q=search&page=1','','{"success":true,"data":[...],"meta":{...}}'],
        ['GET','/users/{id}','Get employee profile','','{"success":true,"data":{"id":5,"full_name":"John Smith","email":"john@company.com",...}}'],
      ],
      'Licenses' => [
        ['GET','/licenses','List all licenses. Query: ?page=1&per_page=25','','{"success":true,"data":[{"id":1,"software_name":"Office 365","type":"subscription","seats":50,...}],"meta":{...}}'],
        ['GET','/licenses/{id}','Get single license','','{"success":true,"data":{...}}'],
      ],
      'Vendors' => [
        ['GET','/vendors','List all vendors','','{"success":true,"data":[...],"meta":{...}}'],
        ['GET','/vendors/{id}','Get single vendor','','{"success":true,"data":{...}}'],
      ],
      'Departments' => [
        ['GET','/departments','List all departments with employee count','','{"success":true,"data":[{"id":1,"name":"IT","employee_count":12,...}]}'],
      ],
      'Search' => [
        ['GET','/search?q=laptop','Global search across assets, users, licenses, documents','','{"success":true,"data":{"assets":[...],"users":[...],"licenses":[...],"documents":[...]}}'],
      ],
    ];

    $methodColors = ['GET'=>'success','POST'=>'primary','PUT'=>'warning','DELETE'=>'danger','PATCH'=>'info'];

    foreach ($endpoints as $section => $eps):
    ?>
    <div class="card mb-4" id="<?= strtolower($section) ?>">
      <div class="card-header fw-bold"><i class="bi bi-arrow-right-circle text-primary me-2"></i><?= $section ?></div>
      <div class="card-body p-0">
        <?php foreach ($eps as [$method, $path, $desc, $body, $example]): ?>
        <div class="p-3 border-bottom">
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="badge bg-<?= $methodColors[$method]??'secondary' ?> mono" style="font-size:12px;min-width:60px;text-align:center;"><?= $method ?></span>
            <code class="mono" style="font-size:13px;">/api/v1<?= $path ?></code>
          </div>
          <p class="text-muted mb-2" style="font-size:13px;"><?= h($desc) ?></p>
          <?php if ($body): ?>
          <div class="mb-2">
            <div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px;">Request Body</div>
            <pre class="p-2 rounded mono mb-0" style="background:var(--color-bg);font-size:11px;"><?= h($body) ?></pre>
          </div>
          <?php endif; ?>
          <div>
            <div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px;">Example Response</div>
            <pre class="p-2 rounded mono mb-0" style="background:var(--color-bg);font-size:11px;"><?= h($example) ?></pre>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- Error Codes -->
    <div class="card">
      <div class="card-header fw-bold"><i class="bi bi-exclamation-triangle text-danger me-2"></i>HTTP Status Codes</div>
      <div class="card-body p-0">
        <?php
        $codes = [
          ['200','OK','Request successful'],
          ['201','Created','Resource created successfully'],
          ['204','No Content','Request successful, no body returned'],
          ['400','Bad Request','Invalid request parameters'],
          ['401','Unauthorized','Missing or invalid API key'],
          ['403','Forbidden','Insufficient permissions'],
          ['404','Not Found','Resource does not exist'],
          ['405','Method Not Allowed','HTTP method not supported'],
          ['500','Server Error','Internal server error'],
        ];
        foreach ($codes as [$code, $name, $desc]): ?>
        <div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom">
          <span class="badge <?= $code[0]==='2'?'bg-success':($code[0]==='4'?'bg-warning text-dark':'bg-danger') ?> mono" style="min-width:48px;"><?= $code ?></span>
          <span class="fw-semibold" style="font-size:13px;min-width:140px;"><?= $name ?></span>
          <span class="text-muted" style="font-size:13px;"><?= $desc ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>
