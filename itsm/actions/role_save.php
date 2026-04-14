<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requireCsrf();

$isEdit = !empty($_POST['id']) && (int)$_POST['id'] > 0;
$isEdit ? $auth->requirePermission('roles','edit') : $auth->requirePermission('roles','add');

$id   = $isEdit ? (int)$_POST['id'] : null;
$name = trim($_POST['name'] ?? '');
$desc = trim($_POST['description'] ?? '') ?: null;

if (!$name) jsonResponse(['success'=>false,'message'=>'Role name is required.']);

if ($id) {
    $roleStmt = $db->prepare("SELECT is_system FROM roles WHERE id = ?");
    $roleStmt->execute([$id]);
    $role = $roleStmt->fetch();
    if ($role && $role['is_system'] && !$auth->isAdmin()) {
        jsonResponse(['success'=>false,'message'=>'Cannot edit system role.']);
    }
}

try {
    if ($id) {
        $db->prepare("UPDATE roles SET name=?, description=? WHERE id=?")->execute([$name, $desc, $id]);
        $auth->logAudit($auth->getUserId(),'edit','roles',$id);
    } else {
        $db->prepare("INSERT INTO roles (name, description) VALUES (?,?)")->execute([$name, $desc]);
        $auth->logAudit($auth->getUserId(),'create','roles',(int)$db->lastInsertId());
    }
    jsonResponse(['success'=>true,'message'=>'Role saved.','reload'=>true]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false) {
        jsonResponse(['success'=>false,'message'=>'A role with this name already exists.']);
    }
    error_log('[role_save] '.$e->getMessage());
    jsonResponse(['success'=>false,'message'=>'Database error.'], 500);
}
