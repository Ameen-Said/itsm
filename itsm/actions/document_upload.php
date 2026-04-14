<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requirePermission('documents','add');
if (!$auth->verifyCsrfToken($_POST['csrf_token']??null)) { flash('danger','CSRF error.'); redirect(APP_URL.'/pages/documents.php'); }

$title    = trim($_POST['title'] ?? '');
$category = in_array($_POST['category']??'',['contract','manual','invoice','policy','other']) ? $_POST['category'] : 'other';
$desc     = trim($_POST['description'] ?? '') ?: null;
$version  = trim($_POST['version']  ?? '1.0');
$folderId = !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;
$assetId  = !empty($_POST['asset_id'])  ? (int)$_POST['asset_id']  : null;
$userId2  = !empty($_POST['user_id'])   ? (int)$_POST['user_id']   : null;
$vendorId = !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null;

if (!$title) { flash('danger','Document title is required.'); redirect(APP_URL.'/pages/documents.php'); }
if (empty($_FILES['file']['name'])) { flash('danger','Please select a file.'); redirect(APP_URL.'/pages/documents.php'); }

$result = handleUpload($_FILES['file'], 'documents');
if (!$result['success']) { flash('danger', $result['message']); redirect(APP_URL.'/pages/documents.php'); }

try {
    // Check if folder_id column exists
    $hasFolder = false;
    try {
        $db->query("SELECT folder_id FROM documents LIMIT 0");
        $hasFolder = true;
    } catch(PDOException $e) {}

    if ($hasFolder) {
        $db->prepare("INSERT INTO documents (folder_id,title,description,category,filename,original_name,file_size,mime_type,version,asset_id,user_id,vendor_id,uploaded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$folderId,$title,$desc,$category,$result['filename'],$result['original'],$result['size'],$result['mime'],$version,$assetId,$userId2,$vendorId,$auth->getUserId()]);
    } else {
        $db->prepare("INSERT INTO documents (title,description,category,filename,original_name,file_size,mime_type,version,asset_id,user_id,vendor_id,uploaded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$title,$desc,$category,$result['filename'],$result['original'],$result['size'],$result['mime'],$version,$assetId,$userId2,$vendorId,$auth->getUserId()]);
    }
    $auth->logAudit($auth->getUserId(),'upload','documents',(int)$db->lastInsertId(),['title'=>$title]);
    flash('success','Document uploaded successfully.');
} catch (PDOException $e) {
    error_log('[doc_upload] '.$e->getMessage());
    flash('danger','Database error saving document.');
}
redirect(APP_URL.'/pages/documents.php');
