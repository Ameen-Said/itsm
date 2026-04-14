#!/usr/bin/env php
<?php
/**
 * IT Manager Pro — Cron: Cleanup
 * Run monthly: 0 0 1 * * php /path/to/itsm/cron/cleanup.php
 */

define('CRON_MODE', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();
echo "[".date('Y-m-d H:i:s')."] Starting cleanup...\n";

// Purge audit logs older than 90 days
$stmt = $db->prepare("DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
$stmt->execute();
echo "  Deleted {$stmt->rowCount()} audit log entries (90d+)\n";

// Purge read notifications older than 30 days
$stmt = $db->prepare("DELETE FROM notifications WHERE is_read=1 AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmt->execute();
echo "  Deleted {$stmt->rowCount()} old notifications\n";

// Purge email logs older than 60 days
$stmt = $db->prepare("DELETE FROM email_logs WHERE sent_at < DATE_SUB(NOW(), INTERVAL 60 DAY)");
$stmt->execute();
echo "  Deleted {$stmt->rowCount()} email log entries\n";

// Clean up orphaned uploads (documents deleted from DB but file remains)
$dbFiles = $db->query("SELECT filename FROM documents")->fetchAll(PDO::FETCH_COLUMN);
$diskFiles = glob(APP_ROOT.'/uploads/documents/*') ?: [];
$cleaned = 0;
foreach ($diskFiles as $diskFile) {
    $base = basename($diskFile);
    if ($base === '.gitkeep') continue;
    if (!in_array($base, $dbFiles)) {
        unlink($diskFile);
        $cleaned++;
    }
}
echo "  Removed $cleaned orphaned upload files\n";

file_put_contents(APP_ROOT.'/logs/cron_cleanup.log', date('Y-m-d H:i:s')." | cleanup complete\n", FILE_APPEND);
echo "[".date('Y-m-d H:i:s')."] Cleanup complete.\n";
