<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requireCsrf();

$uid     = $auth->getUserId();
$current = $_POST['current_password'] ?? '';
$new     = $_POST['new_password']     ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if (!$current || !$new || !$confirm) jsonResponse(['success'=>false,'message'=>'All fields are required.']);
if ($new !== $confirm) jsonResponse(['success'=>false,'message'=>'Passwords do not match.']);
if (strlen($new) < 8)  jsonResponse(['success'=>false,'message'=>'Password must be at least 8 characters.']);

$stmt = $db->prepare("SELECT password_hash FROM users WHERE id=?");
$stmt->execute([$uid]);
$user = $stmt->fetch();
if (!$user || !password_verify($current, $user['password_hash'])) {
    jsonResponse(['success'=>false,'message'=>'Current password is incorrect.']);
}

$hash = password_hash($new, PASSWORD_BCRYPT, ['cost'=>BCRYPT_COST]);
$db->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $uid]);
$auth->logAudit($uid,'change_password','auth',$uid);
jsonResponse(['success'=>true,'message'=>'Password changed successfully.']);
