<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
if (!$auth->verifyCsrfToken($_GET['csrf_token']??null)) { flash('danger','CSRF error.'); redirect(APP_URL.'/pages/settings.php'); }
$db->prepare("DELETE FROM notifications WHERE is_read=1 AND (user_id=? OR user_id IS NULL)")->execute([$auth->getUserId()]);
flash('success','Read notifications cleared.');
redirect(APP_URL.'/pages/settings.php');
