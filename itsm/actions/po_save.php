<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requirePermission('procurement','add');
if (!$auth->verifyCsrfToken($_POST['csrf_token']??null)) { flash('danger','CSRF token invalid.'); redirect(APP_URL.'/pages/procurement.php'); }

$vendorId = !empty($_POST['vendor_id'])     ? (int)$_POST['vendor_id']     : null;
$deptId   = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
$orderedAt= sanitizeDate($_POST['ordered_at'] ?? null) ?? date('Y-m-d');
$notes    = trim($_POST['notes'] ?? '');
$items    = $_POST['items'] ?? [];

if (empty($items)) { flash('danger','At least one line item is required.'); redirect(APP_URL.'/pages/procurement.php'); }

$total = 0;
$cleanItems = [];
foreach ($items as $item) {
    $desc  = trim($item['description'] ?? '');
    if (!$desc) continue;
    $qty   = max(1, (int)($item['quantity'] ?? 1));
    $price = max(0, (float)($item['unit_price'] ?? 0));
    $cleanItems[] = ['description'=>$desc,'quantity'=>$qty,'unit_price'=>$price,'total'=>$qty*$price];
    $total += $qty * $price;
}

if (empty($cleanItems)) { flash('danger','No valid items found.'); redirect(APP_URL.'/pages/procurement.php'); }

try {
    $poNumber = generatePONumber();
    $db->prepare("INSERT INTO purchase_orders (po_number,vendor_id,department_id,total_amount,status,ordered_at,notes,created_by) VALUES (?,?,?,?,'draft',?,?,?)")
       ->execute([$poNumber,$vendorId,$deptId,$total,$orderedAt,$notes,$auth->getUserId()]);
    $poId = (int)$db->lastInsertId();

    $iStmt = $db->prepare("INSERT INTO purchase_order_items (po_id,description,quantity,unit_price,total_price) VALUES (?,?,?,?,?)");
    foreach ($cleanItems as $item) {
        $iStmt->execute([$poId,$item['description'],$item['quantity'],$item['unit_price'],$item['total']]);
    }
    $auth->logAudit($auth->getUserId(),'create','procurement',$poId);
    flash('success',"Purchase Order $poNumber created successfully.");
} catch (PDOException $e) {
    error_log('[po_save] '.$e->getMessage());
    flash('danger','Database error creating PO.');
}
redirect(APP_URL.'/pages/procurement.php');
