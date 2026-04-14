<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requireCsrf();
$auth->requirePermission('licenses','edit');

$licenseId  = (int)($_POST['license_id'] ?? 0);
$assignType = $_POST['assign_type'] ?? 'user';
$userId     = !empty($_POST['user_id'])  ? (int)$_POST['user_id']  : null;
$assetId    = !empty($_POST['asset_id']) ? (int)$_POST['asset_id'] : null;
$notes      = trim($_POST['notes'] ?? '');

if (!$licenseId) jsonResponse(['success'=>false,'message'=>'License ID required.']);
if ($assignType === 'user'  && !$userId)  jsonResponse(['success'=>false,'message'=>'Please select an employee.']);
if ($assignType === 'asset' && !$assetId) jsonResponse(['success'=>false,'message'=>'Please select an asset.']);

try {
    // Get license info
    $licStmt = $db->prepare("SELECT seats FROM licenses WHERE id = ?");
    $licStmt->execute([$licenseId]);
    $lic = $licStmt->fetch();
    if (!$lic) jsonResponse(['success'=>false,'message'=>'License not found.']);

    // Count current assignments
    $usedStmt = $db->prepare("SELECT COUNT(*) FROM license_assignments WHERE license_id = ?");
    $usedStmt->execute([$licenseId]);
    $used = (int)$usedStmt->fetchColumn();

    if ($used >= (int)$lic['seats']) {
        jsonResponse(['success'=>false,'message'=>'No seats available. All '.$lic['seats'].' seats are assigned.']);
    }

    // Check if already assigned to this user/asset
    if ($userId) {
        $dupStmt = $db->prepare("SELECT id FROM license_assignments WHERE license_id=? AND user_id=?");
        $dupStmt->execute([$licenseId, $userId]);
        if ($dupStmt->fetch()) jsonResponse(['success'=>false,'message'=>'This license is already assigned to this employee.']);
    }

    $db->prepare("INSERT INTO license_assignments (license_id,user_id,asset_id,notes) VALUES (?,?,?,?)")
       ->execute([
           $licenseId,
           $assignType === 'user'  ? $userId  : null,
           $assignType === 'asset' ? $assetId : null,
           $notes
       ]);

    // Update seats_used counter
    $db->prepare("UPDATE licenses SET seats_used = (SELECT COUNT(*) FROM license_assignments WHERE license_id = ?) WHERE id = ?")
       ->execute([$licenseId, $licenseId]);

    $auth->logAudit($auth->getUserId(),'assign','licenses',$licenseId);
    jsonResponse(['success'=>true,'message'=>'License assigned successfully.','reload'=>true]);

} catch (PDOException $e) {
    error_log('[license_assign] '.$e->getMessage());
    jsonResponse(['success'=>false,'message'=>'Database error.'], 500);
}
