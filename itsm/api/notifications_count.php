<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
header('Content-Type: application/json');
$stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id=? OR user_id IS NULL) AND is_read=0");
$stmt->execute([$auth->getUserId()]);
echo json_encode(['success'=>true,'count'=>(int)$stmt->fetchColumn()]);
