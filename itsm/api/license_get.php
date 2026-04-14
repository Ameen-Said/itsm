<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requirePermission('licenses','view');
$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
if (!$id) jsonResponse(['success'=>false,'message'=>'ID required.'], 400);
$stmt = $db->prepare("SELECT * FROM licenses WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) jsonResponse(['success'=>false,'message'=>'Not found.'], 404);
jsonResponse(['success'=>true,'license'=>$row]);
