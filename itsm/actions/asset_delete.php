<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
if (!$auth->verifyCsrfToken($_GET['csrf_token']??null)) { flash('danger','CSRF error.'); redirect(APP_URL.'/pages/assets.php'); }
$auth->requirePermission('assets','delete');
$id=(int)($_GET['id']??0);
if ($id) {
    try {
        $db->prepare("DELETE FROM `assets` WHERE id=?")->execute([$id]);
        $auth->logAudit($auth->getUserId(),'delete','assets',$id);
        flash('success','Record deleted.');
    } catch (PDOException $e) {
        flash('danger','Cannot delete — record may have dependencies.');
    }
}
redirect(APP_URL.'/pages/assets.php');
