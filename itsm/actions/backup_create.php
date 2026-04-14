<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
if (!$auth->isAdmin()) { flash('danger','Admins only.'); redirect(APP_URL.'/pages/settings.php'); }
if (!$auth->verifyCsrfToken($_GET['csrf_token']??null)) { flash('danger','CSRF error.'); redirect(APP_URL.'/pages/settings.php'); }

$fname    = 'itsm_backup_'.date('Y-m-d_H-i-s').'.sql';
$fpath    = APP_ROOT.'/backups/'.$fname;
$tables   = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$sql      = "-- IT Manager Pro Backup\n-- ".date('Y-m-d H:i:s')."\nSET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {
    $create = $db->query("SHOW CREATE TABLE `$table`")->fetch();
    $sql   .= "DROP TABLE IF EXISTS `$table`;\n".$create['Create Table'].";\n\n";
    $rows   = $db->query("SELECT * FROM `$table`")->fetchAll();
    if (!empty($rows)) {
        $cols = '`'.implode('`,`',array_keys($rows[0])).'`';
        $sql .= "INSERT INTO `$table` ($cols) VALUES\n";
        $rowSqls=[];
        foreach ($rows as $row) {
            $vals=array_map(fn($v)=>$v===null?'NULL':$db->quote($v),array_values($row));
            $rowSqls[]='('.implode(',',$vals).')';
        }
        $sql.=implode(",\n",$rowSqls).";\n\n";
    }
}
$sql.="SET FOREIGN_KEY_CHECKS=1;\n";

if (!is_dir(APP_ROOT.'/backups')) mkdir(APP_ROOT.'/backups',0750,true);
file_put_contents($fpath, $sql);
$auth->logAudit($auth->getUserId(),'backup_create','system',null,['file'=>$fname]);

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Content-Length: '.strlen($sql));
echo $sql; exit;
