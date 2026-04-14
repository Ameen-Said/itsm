<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requireCsrf();
$mp   = $_POST['master_password']         ?? '';
$mp2  = $_POST['master_password_confirm'] ?? '';
if (strlen($mp) < 8)  jsonResponse(['success'=>false,'message'=>'Master password must be at least 8 characters.']);
if ($mp !== $mp2)     jsonResponse(['success'=>false,'message'=>'Passwords do not match.']);
$salt = bin2hex(random_bytes(16));
$hash = password_hash($mp, PASSWORD_BCRYPT, ['cost'=>BCRYPT_COST]);
$db->prepare("UPDATE users SET vault_key_hash=?,vault_salt=? WHERE id=?")->execute([$hash,$salt,$auth->getUserId()]);
$auth->logAudit($auth->getUserId(),'set_vault_key','vault',$auth->getUserId());
jsonResponse(['success'=>true,'message'=>'Master key set. Your vault is now secured.','reload'=>true]);
