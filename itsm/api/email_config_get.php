<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requirePermission('email','view');
$id   = (int)($_GET['id']??0);
$stmt = $db->prepare("SELECT id,name,smtp_host,smtp_port,smtp_user,from_email,from_name,is_default FROM email_configs WHERE id=?");
$stmt->execute([$id]);
$row  = $stmt->fetch();
if (!$row) jsonResponse(['success'=>false,'message'=>'Not found.'],404);
jsonResponse(['success'=>true,'config'=>$row]);
