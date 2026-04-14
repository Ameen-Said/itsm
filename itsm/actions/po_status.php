<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requireCsrf();
$auth->requirePermission('procurement','edit');
$id     = (int)($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';
$valid  = ['draft','approved','received','cancelled'];
if (!$id || !in_array($status,$valid,true)) jsonResponse(['success'=>false,'message'=>'Invalid request.']);
$received = $status==='received' ? date('Y-m-d') : null;
$db->prepare("UPDATE purchase_orders SET status=?,received_at=? WHERE id=?")->execute([$status,$received,$id]);
$auth->logAudit($auth->getUserId(),'status_update','procurement',$id,['status'=>$status]);
jsonResponse(['success'=>true,'message'=>'PO status updated to '.ucfirst($status).'.']);
