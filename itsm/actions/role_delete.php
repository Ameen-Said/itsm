<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requireCsrf();
$auth->requirePermission('roles','delete');
$id = (int)($_POST['id']??0);
if (!$id) jsonResponse(['success'=>false,'message'=>'ID required.']);
$role=$db->prepare("SELECT is_system,(SELECT COUNT(*) FROM users WHERE role_id=?) as uc FROM roles WHERE id=?");
$role->execute([$id,$id]); $role=$role->fetch();
if (!$role) jsonResponse(['success'=>false,'message'=>'Role not found.']);
if ($role['is_system']) jsonResponse(['success'=>false,'message'=>'Cannot delete system role.']);
if ($role['uc']>0) jsonResponse(['success'=>false,'message'=>'Cannot delete — '.$role['uc'].' user(s) assigned to this role.']);
$db->prepare("DELETE FROM roles WHERE id=?")->execute([$id]);
$auth->logAudit($auth->getUserId(),'delete','roles',$id);
jsonResponse(['success'=>true,'message'=>'Role deleted.']);
