<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requireCsrf();
$auth->requirePermission('assets','edit');

$assetId = (int)($_POST['asset_id'] ?? 0);
$userId  = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
$notes   = trim($_POST['notes'] ?? '');

if (!$assetId) jsonResponse(['success'=>false,'message'=>'Asset ID required.']);

try {
    // Close any open assignments
    $db->prepare("UPDATE asset_assignments SET returned_at=NOW() WHERE asset_id=? AND returned_at IS NULL")
       ->execute([$assetId]);

    // Determine correct status
    $newStatus = $userId ? 'assigned' : 'available';

    // Get existing status to preserve maintenance/retired
    $cur = $db->prepare("SELECT status FROM assets WHERE id=?");
    $cur->execute([$assetId]);
    $curAsset = $cur->fetch();
    if ($curAsset && in_array($curAsset['status'], ['maintenance','retired'])) {
        $newStatus = $curAsset['status'];
    }

    $db->prepare("UPDATE assets SET assigned_to=?, status=? WHERE id=?")
       ->execute([$userId, $newStatus, $assetId]);

    if ($userId) {
        $db->prepare("INSERT INTO asset_assignments (asset_id,user_id,assigned_by,notes) VALUES (?,?,?,?)")
           ->execute([$assetId,$userId,$auth->getUserId(),$notes]);
    }

    $auth->logAudit($auth->getUserId(),'assign','assets',$assetId,['user_id'=>$userId,'status'=>$newStatus]);
    $msg = $userId ? 'Asset assigned successfully.' : 'Asset unassigned successfully.';
    jsonResponse(['success'=>true,'message'=>$msg,'reload'=>true]);

} catch (PDOException $e) {
    error_log('[asset_assign] '.$e->getMessage());
    jsonResponse(['success'=>false,'message'=>'Database error.'], 500);
}
