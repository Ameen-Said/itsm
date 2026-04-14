<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    jsonResponse(['success'=>false,'message'=>'CSRF token invalid.'], 403);
}
if (empty($_FILES['avatar']['name'])) jsonResponse(['success'=>false,'message'=>'No file selected.']);

$uid = $auth->getUserId();
$result = handleUpload($_FILES['avatar'], 'avatars', ALLOWED_IMG_EXT);
if (!$result['success']) jsonResponse(['success'=>false,'message'=>$result['message']]);

// Delete old avatar
$old = $db->prepare("SELECT avatar FROM users WHERE id=?"); $old->execute([$uid]); $old=$old->fetchColumn();
if ($old && file_exists(UPLOAD_DIR.'avatars/'.$old)) @unlink(UPLOAD_DIR.'avatars/'.$old);

$db->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$result['filename'], $uid]);
jsonResponse(['success'=>true,'message'=>'Avatar updated.']);
