<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
if (!$id) jsonResponse(['success'=>false,'message'=>'ID required.'], 400);
$stmt = $db->prepare("SELECT id,system_name,url,username,category,is_favourite FROM vault_entries WHERE id=? AND user_id=?");
$stmt->execute([$id, $auth->getUserId()]);
$row = $stmt->fetch();
if (!$row) jsonResponse(['success'=>false,'message'=>'Not found.'], 404);
jsonResponse(['success'=>true,'entry'=>$row]);
