<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
header('Content-Type: application/json; charset=utf-8');
$q = trim($_POST['q'] ?? $_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}
try {
    $results = globalSearch($db, $q, 6);
    echo json_encode($results, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([]);
}
