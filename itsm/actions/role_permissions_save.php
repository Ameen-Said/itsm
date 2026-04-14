<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requireCsrf();
$auth->requirePermission('roles','edit');

$roleId  = (int)($_POST['role_id'] ?? 0);
$permIds = array_filter(array_map('intval', (array)($_POST['permission_ids'] ?? [])));

if (!$roleId) jsonResponse(['success'=>false,'message'=>'Role ID is required.']);

$roleStmt = $db->prepare("SELECT id, is_system FROM roles WHERE id = ?");
$roleStmt->execute([$roleId]);
$role = $roleStmt->fetch();

if (!$role) jsonResponse(['success'=>false,'message'=>'Role not found.']);
if ($role['is_system'] && !$auth->isAdmin()) {
    jsonResponse(['success'=>false,'message'=>'Cannot modify system role permissions.']);
}

try {
    $db->beginTransaction();
    $db->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$roleId]);
    if (!empty($permIds)) {
        $stmt = $db->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?,?)");
        foreach ($permIds as $pid) {
            if ($pid > 0) $stmt->execute([$roleId, $pid]);
        }
    }
    $db->commit();
    $auth->logAudit($auth->getUserId(),'edit_permissions','roles',$roleId,['count'=>count($permIds)]);
    jsonResponse(['success'=>true,'message'=>'Permissions saved successfully.']);
} catch (PDOException $e) {
    $db->rollBack();
    error_log('[role_permissions_save] '.$e->getMessage());
    jsonResponse(['success'=>false,'message'=>'Database error.'], 500);
}
