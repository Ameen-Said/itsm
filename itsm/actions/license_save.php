<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requireCsrf();

$isEdit = !empty($_POST['id']) && (int)$_POST['id'] > 0;
$isEdit ? $auth->requirePermission('licenses','edit') : $auth->requirePermission('licenses','add');

$id           = $isEdit ? (int)$_POST['id'] : null;
$softwareName = trim($_POST['software_name'] ?? '');
$licenseKey   = trim($_POST['license_key']   ?? '') ?: null;
$type         = $_POST['type'] ?? 'per_user';
$seats        = max(1, (int)($_POST['seats'] ?? 1));
$vendorId     = !empty($_POST['vendor_id'])     ? (int)$_POST['vendor_id']  : null;
$purchaseDate = sanitizeDate($_POST['purchase_date'] ?? null);
$expiryDate   = sanitizeDate($_POST['expiry_date']   ?? null);
$price        = max(0, (float)($_POST['price'] ?? 0));
$notes        = trim($_POST['notes']   ?? '');
$status       = $_POST['status'] ?? 'active';

$validTypes = ['per_user','per_device','enterprise','subscription','open_source'];
if (!in_array($type, $validTypes, true)) $type = 'per_user';
$validStatuses = ['active','expired','cancelled'];
if (!in_array($status, $validStatuses, true)) $status = 'active';

if (!$softwareName) jsonResponse(['success'=>false,'message'=>'Software name is required.']);

try {
    if ($isEdit) {
        $db->prepare(
            "UPDATE licenses SET software_name=?,license_key=?,type=?,seats=?,vendor_id=?,
             purchase_date=?,expiry_date=?,price=?,notes=?,status=? WHERE id=?"
        )->execute([$softwareName,$licenseKey,$type,$seats,$vendorId,$purchaseDate,$expiryDate,$price,$notes,$status,$id]);
        $auth->logAudit($auth->getUserId(),'edit','licenses',$id);
        jsonResponse(['success'=>true,'message'=>'License updated.','reload'=>true]);
    } else {
        $db->prepare(
            "INSERT INTO licenses (software_name,license_key,type,seats,vendor_id,purchase_date,expiry_date,price,notes,status)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        )->execute([$softwareName,$licenseKey,$type,$seats,$vendorId,$purchaseDate,$expiryDate,$price,$notes,$status]);
        $newId = (int)$db->lastInsertId();

        $lDays = (int)getSetting('license_alert_days', '30');
        if ($expiryDate && daysUntil($expiryDate) !== null && daysUntil($expiryDate) <= $lDays) {
            createNotification($db, null, 'License Expiring: '.$softwareName,
                "License expires ".formatDate($expiryDate), 'warning',
                APP_URL.'/pages/licenses.php?id='.$newId);
        }
        $auth->logAudit($auth->getUserId(),'create','licenses',$newId);
        jsonResponse(['success'=>true,'message'=>'License added.','reload'=>true]);
    }
} catch (PDOException $e) {
    error_log('[license_save] '.$e->getMessage());
    jsonResponse(['success'=>false,'message'=>'Database error.'], 500);
}
