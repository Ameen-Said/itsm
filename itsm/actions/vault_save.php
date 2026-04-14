<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requireCsrf();

$isEdit = !empty($_POST['id']) && (int)$_POST['id'] > 0;
$isEdit ? $auth->requirePermission('vault','edit') : $auth->requirePermission('vault','add');

$userId     = $auth->getUserId();
$user       = $auth->getUser();
$id         = $isEdit ? (int)$_POST['id'] : null;
$systemName = trim($_POST['system_name'] ?? '');
$url        = trim($_POST['url']      ?? '');
$username   = trim($_POST['username'] ?? '');
$password   = $_POST['password'] ?? '';
$notes      = trim($_POST['notes']    ?? '');
$category   = trim($_POST['category'] ?? 'general');
$isFav      = !empty($_POST['is_favourite']) ? 1 : 0;
$masterPass = $_POST['master_password'] ?? null;

if (!$systemName || !$username) {
    jsonResponse(['success'=>false,'message'=>'System name and username are required.']);
}

// Verify ownership on edit
if ($id) {
    $ownerStmt = $db->prepare("SELECT user_id FROM vault_entries WHERE id=?");
    $ownerStmt->execute([$id]);
    $owner = $ownerStmt->fetch();
    if (!$owner || (int)$owner['user_id'] !== (int)$userId) {
        jsonResponse(['success'=>false,'message'=>'Access denied.'], 403);
    }
}

$vaultKey = getVaultKey($user, !empty($user['vault_salt']) ? $masterPass : null);

try {
    if ($id && !$password) {
        // Update without changing password
        $notesEnc = null;
        $notesIv  = null;
        if ($notes) {
            $enc = encryptData($notes, $vaultKey);
            $notesEnc = $enc['cipher'];
            $notesIv  = $enc['iv'];
        }
        $db->prepare(
            "UPDATE vault_entries SET system_name=?,url=?,username=?,notes_enc=?,notes_iv=?,
             category=?,is_favourite=?,updated_at=NOW() WHERE id=? AND user_id=?"
        )->execute([$systemName,$url,$username,$notesEnc,$notesIv,$category,$isFav,$id,$userId]);
    } else {
        if (!$password) jsonResponse(['success'=>false,'message'=>'Password is required.']);

        $passEnc  = encryptData($password, $vaultKey);
        $notesEnc = null;
        $notesIv  = null;
        if ($notes) {
            $enc = encryptData($notes, $vaultKey);
            $notesEnc = $enc['cipher'];
            $notesIv  = $enc['iv'];
        }

        if ($id) {
            $db->prepare(
                "UPDATE vault_entries SET system_name=?,url=?,username=?,password_enc=?,iv=?,
                 notes_enc=?,notes_iv=?,category=?,is_favourite=?,updated_at=NOW()
                 WHERE id=? AND user_id=?"
            )->execute([$systemName,$url,$username,$passEnc['cipher'],$passEnc['iv'],
                        $notesEnc,$notesIv,$category,$isFav,$id,$userId]);
        } else {
            $db->prepare(
                "INSERT INTO vault_entries (user_id,system_name,url,username,password_enc,iv,
                 notes_enc,notes_iv,category,is_favourite) VALUES (?,?,?,?,?,?,?,?,?,?)"
            )->execute([$userId,$systemName,$url,$username,$passEnc['cipher'],$passEnc['iv'],
                        $notesEnc,$notesIv,$category,$isFav]);
        }
    }
    $auth->logAudit($userId, $id?'edit':'create', 'vault', $id);
    jsonResponse(['success'=>true,'message'=>'Credential saved.','reload'=>true]);
} catch (PDOException $e) {
    error_log('[vault_save] '.$e->getMessage());
    jsonResponse(['success'=>false,'message'=>'Database error.'], 500);
}
