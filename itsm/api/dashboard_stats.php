<?php
// REST API: GET /api/dashboard_stats.php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
header('Content-Type: application/json');
$stats = [
    'total_assets'    => (int)$db->query("SELECT COUNT(*) FROM assets")->fetchColumn(),
    'assigned_assets' => (int)$db->query("SELECT COUNT(*) FROM assets WHERE status='assigned'")->fetchColumn(),
    'active_users'    => (int)$db->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn(),
    'expiring_licenses'=>(int)$db->query("SELECT COUNT(*) FROM licenses WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) AND status='active'")->fetchColumn(),
    'expiring_warranties'=>(int)$db->query("SELECT COUNT(*) FROM assets WHERE warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)")->fetchColumn(),
    'open_pos'        => (int)$db->query("SELECT COUNT(*) FROM purchase_orders WHERE status='draft'")->fetchColumn(),
    'total_cost'      => (float)$db->query("SELECT COALESCE(SUM(price),0) FROM assets")->fetchColumn(),
];
echo json_encode(['success'=>true,'data'=>$stats]);
