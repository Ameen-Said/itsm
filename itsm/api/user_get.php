<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requirePermission('users','view');
$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
if (!$id) jsonResponse(['success'=>false,'message'=>'ID required.'], 400);
$stmt = $db->prepare("SELECT id,full_name,username,email,phone,job_title,employee_id,role_id,department_id,status,avatar FROM users WHERE id=?");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) jsonResponse(['success'=>false,'message'=>'User not found.'], 404);
jsonResponse(['success'=>true,'user'=>$row]);
