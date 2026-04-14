<?php
/**
 * IT Manager Pro — REST API
 * Base URL: /api/v1/
 *
 * Authentication: Bearer token via Authorization header
 * All responses: JSON { success, data|message, meta? }
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

// API Router
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Token Auth (simple API key stored in users table or config)
function requireApiAuth(): int {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
    $token  = str_starts_with($header,'Bearer ') ? substr($header,7) : $header;
    if (!$token) apiError('No authentication token provided.', 401);

    global $db;
    // In production, use a dedicated api_tokens table
    // Here we check against a hashed env key for simplicity
    $validKey = getenv('API_KEY') ?: 'change-this-api-key';
    if (!hash_equals($validKey, $token)) apiError('Invalid API key.', 401);
    return 1; // Return user_id or role
}

function apiSuccess(mixed $data, int $code = 200, array $meta = []): never {
    http_response_code($code);
    $resp = ['success' => true, 'data' => $data];
    if ($meta) $resp['meta'] = $meta;
    echo json_encode($resp, JSON_PRETTY_PRINT);
    exit;
}

function apiError(string $message, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function getPaginationMeta(int $total, int $page, int $perPage): array {
    return [
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => (int)ceil($total / $perPage),
    ];
}

// Route parsing
$path   = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$parts  = explode('/', $path);
// Find 'v1' segment
$v1idx  = array_search('v1', $parts);
$parts  = $v1idx !== false ? array_slice($parts, $v1idx + 1) : [];
$resource = $parts[0] ?? '';
$id       = isset($parts[1]) && is_numeric($parts[1]) ? (int)$parts[1] : null;
$method   = $_SERVER['REQUEST_METHOD'];

// Parse body
$body = [];
if (in_array($method, ['POST','PUT','PATCH'])) {
    $raw = file_get_contents('php://input');
    if ($raw) $body = json_decode($raw, true) ?? [];
}

// ── Auth all API requests ────────────────────────────────────
requireApiAuth();

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(100, max(1, (int)($_GET['per_page'] ?? 25)));
$offset  = ($page - 1) * $perPage;

// ── Route Dispatch ───────────────────────────────────────────
switch ($resource) {

    // ── GET /api/v1/assets ───────────────────────────────────
    case 'assets':
        if ($method === 'GET' && !$id) {
            $search = $_GET['q'] ?? '';
            $status = $_GET['status'] ?? '';
            $where  = []; $params = [];
            if ($search) { $where[]="(a.name LIKE ? OR a.asset_code LIKE ?)"; $s="%$search%"; $params=array_merge($params,[$s,$s]); }
            if ($status) { $where[]="a.status=?"; $params[]=$status; }
            $w = $where ? 'WHERE '.implode(' AND ',$where) : '';
            $total = (int)$db->prepare("SELECT COUNT(*) FROM assets a $w")->execute($params)->fetchColumn();
            // Re-execute for count
            $cstmt=$db->prepare("SELECT COUNT(*) FROM assets a $w"); $cstmt->execute($params); $total=(int)$cstmt->fetchColumn();
            $stmt=$db->prepare("SELECT a.*,ac.name as category,u.full_name as assigned_to_name,d.name as department FROM assets a LEFT JOIN asset_categories ac ON a.category_id=ac.id LEFT JOIN users u ON a.assigned_to=u.id LEFT JOIN departments d ON a.department_id=d.id $w ORDER BY a.created_at DESC LIMIT $perPage OFFSET $offset");
            $stmt->execute($params);
            apiSuccess($stmt->fetchAll(), 200, getPaginationMeta($total, $page, $perPage));
        }
        if ($method === 'GET' && $id) {
            $stmt=$db->prepare("SELECT a.*,ac.name as category,u.full_name as assigned_to_name FROM assets a LEFT JOIN asset_categories ac ON a.category_id=ac.id LEFT JOIN users u ON a.assigned_to=u.id WHERE a.id=?");
            $stmt->execute([$id]); $row=$stmt->fetch();
            if (!$row) apiError('Asset not found.', 404);
            apiSuccess($row);
        }
        if ($method === 'POST') {
            $name = trim($body['name'] ?? '');
            if (!$name) apiError('name is required.');
            $assetCode = generateAssetCode();
            $barcode   = generateBarcode();
            $db->prepare("INSERT INTO assets (asset_code,barcode,name,category_id,brand,model,serial_number,purchase_date,warranty_expiry,price,status,vendor_id,department_id,assigned_to,location,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$assetCode,$barcode,$name,$body['category_id']??null,$body['brand']??'',$body['model']??'',$body['serial_number']??'',$body['purchase_date']??null,$body['warranty_expiry']??null,$body['price']??0,$body['status']??'available',$body['vendor_id']??null,$body['department_id']??null,$body['assigned_to']??null,$body['location']??'',$body['notes']??'']);
            $newId=(int)$db->lastInsertId();
            $stmt=$db->prepare("SELECT * FROM assets WHERE id=?"); $stmt->execute([$newId]);
            apiSuccess($stmt->fetch(), 201);
        }
        if ($method === 'PUT' && $id) {
            $db->prepare("UPDATE assets SET name=COALESCE(?,name),brand=COALESCE(?,brand),model=COALESCE(?,model),serial_number=COALESCE(?,serial_number),status=COALESCE(?,status),price=COALESCE(?,price),notes=COALESCE(?,notes) WHERE id=?")
               ->execute([$body['name']??null,$body['brand']??null,$body['model']??null,$body['serial_number']??null,$body['status']??null,$body['price']??null,$body['notes']??null,$id]);
            $stmt=$db->prepare("SELECT * FROM assets WHERE id=?"); $stmt->execute([$id]);
            apiSuccess($stmt->fetch());
        }
        if ($method === 'DELETE' && $id) {
            $db->prepare("DELETE FROM assets WHERE id=?")->execute([$id]);
            apiSuccess(['deleted' => true, 'id' => $id]);
        }
        apiError('Method not allowed.', 405);

    // ── GET /api/v1/users ─────────────────────────────────────
    case 'users':
        if ($method === 'GET' && !$id) {
            $search=$_GET['q']??''; $where=[]; $params=[];
            if ($search) { $where[]="(full_name LIKE ? OR email LIKE ?)"; $s="%$search%"; $params=array_merge($params,[$s,$s]); }
            $w=$where?'WHERE '.implode(' AND ',$where):'';
            $cstmt=$db->prepare("SELECT COUNT(*) FROM users $w"); $cstmt->execute($params); $total=(int)$cstmt->fetchColumn();
            $stmt=$db->prepare("SELECT id,username,full_name,email,phone,job_title,employee_id,status,last_login,created_at FROM users $w ORDER BY full_name LIMIT $perPage OFFSET $offset");
            $stmt->execute($params);
            apiSuccess($stmt->fetchAll(),200,getPaginationMeta($total,$page,$perPage));
        }
        if ($method === 'GET' && $id) {
            $stmt=$db->prepare("SELECT id,username,full_name,email,phone,job_title,employee_id,status,last_login,created_at FROM users WHERE id=?");
            $stmt->execute([$id]); $row=$stmt->fetch();
            if (!$row) apiError('User not found.',404);
            apiSuccess($row);
        }
        apiError('Method not allowed.',405);

    // ── GET /api/v1/licenses ──────────────────────────────────
    case 'licenses':
        if ($method === 'GET' && !$id) {
            $cstmt=$db->query("SELECT COUNT(*) FROM licenses"); $total=(int)$cstmt->fetchColumn();
            $stmt=$db->prepare("SELECT l.*,v.name as vendor_name FROM licenses l LEFT JOIN vendors v ON l.vendor_id=v.id ORDER BY l.expiry_date ASC LIMIT $perPage OFFSET $offset");
            $stmt->execute();
            apiSuccess($stmt->fetchAll(),200,getPaginationMeta($total,$page,$perPage));
        }
        if ($method === 'GET' && $id) {
            $stmt=$db->prepare("SELECT * FROM licenses WHERE id=?"); $stmt->execute([$id]); $row=$stmt->fetch();
            if (!$row) apiError('License not found.',404);
            apiSuccess($row);
        }
        apiError('Method not allowed.',405);

    // ── GET /api/v1/vendors ───────────────────────────────────
    case 'vendors':
        if ($method === 'GET') {
            if ($id) {
                $stmt=$db->prepare("SELECT * FROM vendors WHERE id=?"); $stmt->execute([$id]); $row=$stmt->fetch();
                if (!$row) apiError('Vendor not found.',404);
                apiSuccess($row);
            }
            $cstmt=$db->query("SELECT COUNT(*) FROM vendors"); $total=(int)$cstmt->fetchColumn();
            $stmt=$db->prepare("SELECT * FROM vendors ORDER BY name LIMIT $perPage OFFSET $offset");
            $stmt->execute();
            apiSuccess($stmt->fetchAll(),200,getPaginationMeta($total,$page,$perPage));
        }
        apiError('Method not allowed.',405);

    // ── GET /api/v1/departments ───────────────────────────────
    case 'departments':
        if ($method === 'GET') {
            $stmt=$db->query("SELECT d.*,u.full_name as manager_name,(SELECT COUNT(*) FROM users e WHERE e.department_id=d.id) as employee_count FROM departments d LEFT JOIN users u ON d.manager_id=u.id ORDER BY d.name");
            apiSuccess($stmt->fetchAll());
        }
        apiError('Method not allowed.',405);

    // ── GET /api/v1/dashboard ─────────────────────────────────
    case 'dashboard':
        if ($method === 'GET') {
            apiSuccess([
                'total_assets'       => (int)$db->query("SELECT COUNT(*) FROM assets")->fetchColumn(),
                'assigned_assets'    => (int)$db->query("SELECT COUNT(*) FROM assets WHERE status='assigned'")->fetchColumn(),
                'available_assets'   => (int)$db->query("SELECT COUNT(*) FROM assets WHERE status='available'")->fetchColumn(),
                'active_users'       => (int)$db->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn(),
                'total_licenses'     => (int)$db->query("SELECT COUNT(*) FROM licenses")->fetchColumn(),
                'expiring_licenses'  => (int)$db->query("SELECT COUNT(*) FROM licenses WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) AND status='active'")->fetchColumn(),
                'expiring_warranties'=> (int)$db->query("SELECT COUNT(*) FROM assets WHERE warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)")->fetchColumn(),
                'total_asset_value'  => (float)$db->query("SELECT COALESCE(SUM(price),0) FROM assets")->fetchColumn(),
                'open_purchase_orders'=> (int)$db->query("SELECT COUNT(*) FROM purchase_orders WHERE status='draft'")->fetchColumn(),
            ]);
        }
        apiError('Method not allowed.',405);

    // ── GET /api/v1/search ─────────────────────────────────────
    case 'search':
        if ($method === 'GET') {
            $q = trim($_GET['q'] ?? '');
            if (strlen($q) < 2) apiError('Query must be at least 2 characters.');
            apiSuccess(globalSearch($db, $q));
        }
        apiError('Method not allowed.',405);

    default:
        apiError("Unknown resource: '{$resource}'. Available: assets, users, licenses, vendors, departments, dashboard, search", 404);
}
