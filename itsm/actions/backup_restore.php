<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
if (!$auth->isAdmin()) { flash('danger','Admins only.'); redirect(APP_URL.'/pages/settings.php'); }
if (!$auth->verifyCsrfToken($_POST['csrf_token']??null)) { flash('danger','CSRF error.'); redirect(APP_URL.'/pages/settings.php'); }
if (empty($_FILES['sql_file'])||$_FILES['sql_file']['error']!==UPLOAD_ERR_OK) { flash('danger','No file.'); redirect(APP_URL.'/pages/settings.php'); }
$ext=strtolower(pathinfo($_FILES['sql_file']['name'],PATHINFO_EXTENSION));
if ($ext!=='sql') { flash('danger','Only .sql files.'); redirect(APP_URL.'/pages/settings.php'); }
$sql=file_get_contents($_FILES['sql_file']['tmp_name']);
try { $db->exec($sql); $auth->logAudit($auth->getUserId(),'backup_restore','system'); flash('success','Database restored.'); }
catch(PDOException $e) { flash('danger','Restore error: '.h($e->getMessage())); }
redirect(APP_URL.'/pages/settings.php');
