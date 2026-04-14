<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requireCsrf();
$id=(int)($_POST['id']??0);
$db->query("UPDATE email_configs SET is_default=0");
$db->prepare("UPDATE email_configs SET is_default=1 WHERE id=?")->execute([$id]);
jsonResponse(['success'=>true,'message'=>'Default updated.']);
