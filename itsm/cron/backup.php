#!/usr/bin/env php
<?php
/**
 * IT Manager Pro — Cron: Auto Backup
 * Run weekly: 0 2 * * 0 php /path/to/itsm/cron/backup.php
 */

define('CRON_MODE', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();

echo "[".date('Y-m-d H:i:s')."] Starting automated backup...\n";

$filename = 'itsm_auto_backup_'.date('Y-m-d').'.sql';
$filepath = APP_ROOT.'/backups/'.$filename;

if (!is_dir(APP_ROOT.'/backups')) mkdir(APP_ROOT.'/backups', 0750, true);

$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$sql = "-- IT Manager Pro Auto Backup\n-- ".date('Y-m-d H:i:s')."\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {
    $createStmt = $db->query("SHOW CREATE TABLE `$table`")->fetch();
    $sql .= "DROP TABLE IF EXISTS `$table`;\n".$createStmt['Create Table'].";\n\n";
    $rows = $db->query("SELECT * FROM `$table`")->fetchAll();
    if (!empty($rows)) {
        $cols = '`'.implode('`,`', array_keys($rows[0])).'`';
        $sql .= "INSERT INTO `$table` ($cols) VALUES\n";
        $vals = array_map(function($row) use($db) {
            return '('.implode(',', array_map(fn($v) => $v===null?'NULL':$db->quote($v), $rows[0] ? array_values($row) : [])).')';
        }, $rows);
        $sql .= implode(",\n",$vals).";\n\n";
    }
}
$sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

file_put_contents($filepath, $sql);
echo "  Backup saved: $filename (".formatBytes(strlen($sql)).")\n";

// Keep only last 4 backups
$files = glob(APP_ROOT.'/backups/itsm_auto_backup_*.sql') ?: [];
rsort($files);
foreach (array_slice($files, 4) as $old) {
    unlink($old);
    echo "  Deleted old backup: ".basename($old)."\n";
}

file_put_contents(APP_ROOT.'/logs/cron_backup.log', date('Y-m-d H:i:s')." | $filename | ".formatBytes(strlen($sql))."\n", FILE_APPEND);
echo "[".date('Y-m-d H:i:s')."] Backup complete.\n";
