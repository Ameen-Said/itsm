<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();

$action = $_POST['action'] ?? '';
$uid    = $auth->getUserId();

if ($action === 'read' && !empty($_POST['id'])) {
    $id = (int)$_POST['id'];
    // Only mark own notifications (or global ones) as read
    $db->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND (user_id=? OR user_id IS NULL)")
       ->execute([$id, $uid]);
    jsonResponse(['success'=>true]);

} elseif ($action === 'read_all') {
    $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=? OR user_id IS NULL")
       ->execute([$uid]);
    jsonResponse(['success'=>true]);

} else {
    jsonResponse(['success'=>false,'message'=>'Invalid action.']);
}
