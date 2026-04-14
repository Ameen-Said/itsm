<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requireCsrf();
$isEdit = !empty($_POST['id']) && (int)$_POST['id'] > 0;
$isEdit ? $auth->requirePermission('departments','edit') : $auth->requirePermission('departments','add');

$id        = $isEdit ? (int)$_POST['id'] : null;
$name      = trim($_POST['name'] ?? '');
$desc      = trim($_POST['description'] ?? '') ?: null;
$managerId = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;
$budget    = max(0, (float)($_POST['budget'] ?? 0));

if (!$name) jsonResponse(['success'=>false,'message'=>'Department name is required.']);

try {
    if ($isEdit) {
        $db->prepare("UPDATE departments SET name=?,description=?,manager_id=?,budget=? WHERE id=?")
           ->execute([$name,$desc,$managerId,$budget,$id]);
        $auth->logAudit($auth->getUserId(),'edit','departments',$id);
    } else {
        $db->prepare("INSERT INTO departments (name,description,manager_id,budget) VALUES (?,?,?,?)")
           ->execute([$name,$desc,$managerId,$budget]);
        $auth->logAudit($auth->getUserId(),'create','departments',(int)$db->lastInsertId());
    }
    jsonResponse(['success'=>true,'message'=>'Department saved.','reload'=>true]);
} catch (PDOException $e) {
    jsonResponse(['success'=>false,'message'=>'Database error.'], 500);
}
