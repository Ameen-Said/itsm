<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requireCsrf();

$isEdit = !empty($_POST['id']) && (int)$_POST['id'] > 0;
$isEdit ? $auth->requirePermission('assets','edit') : $auth->requirePermission('assets','add');

$name         = trim($_POST['name'] ?? '');
$categoryId   = !empty($_POST['category_id'])   ? (int)$_POST['category_id']   : null;
$brand        = trim($_POST['brand']   ?? '');
$model        = trim($_POST['model']   ?? '');
$serial       = trim($_POST['serial_number'] ?? '');
$purchaseDate = sanitizeDate($_POST['purchase_date'] ?? null);
$warrantyExp  = sanitizeDate($_POST['warranty_expiry'] ?? null);
$price        = max(0, (float)($_POST['price'] ?? 0));
$status       = $_POST['status'] ?? 'available';
$vendorId     = !empty($_POST['vendor_id'])     ? (int)$_POST['vendor_id']     : null;
$deptId       = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
$assignedTo   = !empty($_POST['assigned_to'])   ? (int)$_POST['assigned_to']   : null;
$location     = trim($_POST['location'] ?? '');
$notes        = trim($_POST['notes']    ?? '');

if (!$name) jsonResponse(['success'=>false,'message'=>'Asset name is required.']);

$validStatuses = ['available','assigned','maintenance','retired'];
if (!in_array($status, $validStatuses, true)) $status = 'available';

// Auto-correct status based on assignment
if (!in_array($status, ['maintenance','retired'])) {
    $status = $assignedTo ? 'assigned' : 'available';
}

try {
    if ($isEdit) {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("SELECT * FROM assets WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if (!$old) jsonResponse(['success'=>false,'message'=>'Asset not found.']);

        $db->prepare(
            "UPDATE assets SET name=?,category_id=?,brand=?,model=?,serial_number=?,
             purchase_date=?,warranty_expiry=?,price=?,status=?,vendor_id=?,
             department_id=?,assigned_to=?,location=?,notes=? WHERE id=?"
        )->execute([$name,$categoryId,$brand,$model,$serial,$purchaseDate,$warrantyExp,
                    $price,$status,$vendorId,$deptId,$assignedTo,$location,$notes,$id]);

        // Track assignment change
        if ((int)$old['assigned_to'] !== (int)$assignedTo) {
            $db->prepare("UPDATE asset_assignments SET returned_at=NOW() WHERE asset_id=? AND returned_at IS NULL")
               ->execute([$id]);
            if ($assignedTo) {
                $db->prepare("INSERT INTO asset_assignments (asset_id,user_id,assigned_by) VALUES (?,?,?)")
                   ->execute([$id,$assignedTo,$auth->getUserId()]);
            }
        }

        // Handle image upload
        if (!empty($_FILES['image']['name'])) {
            $img = handleUpload($_FILES['image'], 'assets', ['png','jpg','jpeg','gif','webp']);
            if ($img['success']) {
                if ($old['image'] && file_exists(UPLOAD_DIR.'assets/'.$old['image'])) {
                    @unlink(UPLOAD_DIR.'assets/'.$old['image']);
                }
                $db->prepare("UPDATE assets SET image=? WHERE id=?")->execute([$img['filename'], $id]);
            }
        }

        $auth->logAudit($auth->getUserId(),'edit','assets',$id);
        jsonResponse(['success'=>true,'message'=>'Asset updated successfully.','reload'=>true]);
    } else {
        $assetCode = generateAssetCode();
        $barcode   = generateBarcode();

        $db->prepare(
            "INSERT INTO assets (asset_code,barcode,name,category_id,brand,model,serial_number,
             purchase_date,warranty_expiry,price,status,vendor_id,department_id,assigned_to,location,notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([$assetCode,$barcode,$name,$categoryId,$brand,$model,$serial,$purchaseDate,
                    $warrantyExp,$price,$status,$vendorId,$deptId,$assignedTo,$location,$notes]);
        $newId = (int)$db->lastInsertId();

        if ($assignedTo) {
            $db->prepare("INSERT INTO asset_assignments (asset_id,user_id,assigned_by) VALUES (?,?,?)")
               ->execute([$newId,$assignedTo,$auth->getUserId()]);
        }

        // Handle image upload
        if (!empty($_FILES['image']['name'])) {
            $img = handleUpload($_FILES['image'], 'assets', ['png','jpg','jpeg','gif','webp']);
            if ($img['success']) {
                $db->prepare("UPDATE assets SET image=? WHERE id=?")->execute([$img['filename'], $newId]);
            }
        }

        // Expiry notifications
        $wDays = (int)getSetting('warranty_alert_days', '30');
        if ($warrantyExp && daysUntil($warrantyExp) !== null && daysUntil($warrantyExp) <= $wDays) {
            createNotification($db, null, 'Warranty Expiring: '.$name,
                "Asset $assetCode warranty expires ".formatDate($warrantyExp), 'warning',
                APP_URL.'/pages/assets.php?id='.$newId);
        }

        $auth->logAudit($auth->getUserId(),'create','assets',$newId);
        jsonResponse(['success'=>true,'message'=>'Asset created successfully.','reload'=>true]);
    }
} catch (PDOException $e) {
    error_log('[asset_save] '.$e->getMessage());
    jsonResponse(['success'=>false,'message'=>'Database error: '.$e->getMessage()], 500);
}
