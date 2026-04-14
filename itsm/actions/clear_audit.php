<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
if (!$auth->isAdmin()) { flash('danger','Admins only.'); redirect(APP_URL.'/pages/settings.php'); }
if (!$auth->verifyCsrfToken($_GET['csrf_token']??null)) { flash('danger','CSRF error.'); redirect(APP_URL.'/pages/settings.php'); }
$stmt=$db->prepare("DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(),INTERVAL 90 DAY)");
$stmt->execute();
flash('success',$stmt->rowCount().' old audit entries deleted.');
redirect(APP_URL.'/pages/settings.php');
