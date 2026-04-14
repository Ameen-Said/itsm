<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requireCsrf();

$userId     = $auth->getUserId();
$user       = $auth->getUser();
$id         = (int)($_POST['id'] ?? 0);
$masterPass = $_POST['master_password'] ?? null;

if (!$id) jsonResponse(['success'=>false,'message'=>'Invalid entry.']);

$stmt = $db->prepare("SELECT * FROM vault_entries WHERE id=? AND user_id=?");
$stmt->execute([$id, $userId]);
$entry = $stmt->fetch();
if (!$entry) jsonResponse(['success'=>false,'message'=>'Entry not found.'], 404);

if (!empty($user['vault_salt']) && !$masterPass) {
    jsonResponse(['success'=>false,'message'=>'Master password required.','need_master'=>true]);
}

$vaultKey  = getVaultKey($user, $masterPass);
$decrypted = decryptData($entry['password_enc'], $entry['iv'], $vaultKey);

if ($decrypted === false) {
    jsonResponse(['success'=>false,'message'=>'Decryption failed. Wrong master password?']);
}

$auth->logAudit($userId,'reveal_password','vault',$id);
jsonResponse(['success'=>true,'password'=>$decrypted]);
