<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
if (!$auth->verifyCsrfToken($_GET['csrf_token']??null)) { flash('danger','CSRF error.'); redirect(APP_URL.'/pages/vault.php'); }
$id=(int)($_GET['id']??0);
if ($id) { $db->prepare("DELETE FROM vault_entries WHERE id=? AND user_id=?")->execute([$id,$auth->getUserId()]); flash('success','Entry deleted.'); }
redirect(APP_URL.'/pages/vault.php');
