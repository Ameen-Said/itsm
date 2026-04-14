#!/usr/bin/env php
<?php
/**
 * IT Manager Pro — Cron: Send Expiry Alerts
 * Run daily: 0 9 * * * php /path/to/itsm/cron/send_alerts.php
 */

define('CRON_MODE', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();
$now = date('Y-m-d');

$settingsFile = APP_ROOT.'/config/settings.json';
$settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile),true) : [];
$warrantyDays = (int)($settings['warranty_alert_days'] ?? 30);
$licenseDays  = (int)($settings['license_alert_days']  ?? 30);

echo "[".date('Y-m-d H:i:s')."] Starting expiry alerts check...\n";

// ── Warranty Expiry Alerts ─────────────────────────────────
$warranties = $db->prepare("
    SELECT a.name, a.asset_code, a.warranty_expiry, a.id,
           DATEDIFF(a.warranty_expiry, CURDATE()) as days_left,
           u.email as assigned_email, u.full_name as assigned_name
    FROM assets a
    LEFT JOIN users u ON a.assigned_to = u.id
    WHERE a.warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
    AND a.status != 'retired'
");
$warranties->execute([$warrantyDays]);
$warrantyItems = $warranties->fetchAll();

foreach ($warrantyItems as $item) {
    // Insert notification for all admins
    $admins = $db->query("SELECT id FROM users WHERE role_id=1 AND status='active'")->fetchAll();
    foreach ($admins as $admin) {
        // Check if notification already sent today
        $exists = $db->prepare("SELECT id FROM notifications WHERE user_id=? AND link LIKE ? AND DATE(created_at)=CURDATE()");
        $exists->execute([$admin['id'], '%assets.php?id='.$item['id'].'%']);
        if ($exists->fetch()) continue;

        $days = (int)$item['days_left'];
        $type = $days <= 7 ? 'danger' : ($days <= 14 ? 'warning' : 'info');
        $db->prepare("INSERT INTO notifications (user_id,title,message,type,link) VALUES (?,?,?,?,?)")
           ->execute([
               $admin['id'],
               'Warranty Expiring: '.$item['name'],
               "Asset {$item['asset_code']} ({$item['name']}) warranty expires in {$days} days on ".formatDate($item['warranty_expiry']),
               $type,
               APP_URL.'/pages/assets.php?id='.$item['id']
           ]);
    }
    echo "  [WARRANTY] {$item['name']} — {$item['days_left']} days left\n";
}

// ── License Expiry Alerts ──────────────────────────────────
$licenses = $db->prepare("
    SELECT l.id, l.software_name, l.expiry_date,
           DATEDIFF(l.expiry_date, CURDATE()) as days_left
    FROM licenses l
    WHERE l.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
    AND l.status = 'active'
");
$licenses->execute([$licenseDays]);
$licenseItems = $licenses->fetchAll();

foreach ($licenseItems as $item) {
    $admins = $db->query("SELECT id FROM users WHERE role_id=1 AND status='active'")->fetchAll();
    foreach ($admins as $admin) {
        $exists = $db->prepare("SELECT id FROM notifications WHERE user_id=? AND link LIKE ? AND DATE(created_at)=CURDATE()");
        $exists->execute([$admin['id'], '%licenses.php?id='.$item['id'].'%']);
        if ($exists->fetch()) continue;

        $days = (int)$item['days_left'];
        $type = $days <= 7 ? 'danger' : ($days <= 14 ? 'warning' : 'info');
        $db->prepare("INSERT INTO notifications (user_id,title,message,type,link) VALUES (?,?,?,?,?)")
           ->execute([
               $admin['id'],
               'License Expiring: '.$item['software_name'],
               "License for {$item['software_name']} expires in {$days} days on ".formatDate($item['expiry_date']),
               $type,
               APP_URL.'/pages/licenses.php?id='.$item['id']
           ]);
    }
    echo "  [LICENSE] {$item['software_name']} — {$item['days_left']} days left\n";
}

// ── Auto-expire overdue licenses ───────────────────────────
$expired = $db->prepare("UPDATE licenses SET status='expired' WHERE expiry_date < CURDATE() AND status='active'");
$expired->execute();
if ($expired->rowCount() > 0) {
    echo "  [EXPIRED] Marked {$expired->rowCount()} licenses as expired.\n";
}

// ── Log to file ────────────────────────────────────────────
$logLine = date('Y-m-d H:i:s')." | warranties=".count($warrantyItems)." | licenses=".count($licenseItems)."\n";
file_put_contents(APP_ROOT.'/logs/cron_alerts.log', $logLine, FILE_APPEND);

echo "[".date('Y-m-d H:i:s')."] Done. Warranty alerts: ".count($warrantyItems)." | License alerts: ".count($licenseItems)."\n";
