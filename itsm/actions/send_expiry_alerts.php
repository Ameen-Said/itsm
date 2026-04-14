<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
if (!$auth->verifyCsrfToken($_GET['csrf_token']??null)) { flash('danger','CSRF error.'); redirect(APP_URL.'/pages/settings.php'); }
$wDays=(int)getSetting('warranty_alert_days','30');
$lDays=(int)getSetting('license_alert_days','30');
$count=0;
// Warranty alerts
$rows=$db->prepare("SELECT id,name,asset_code,warranty_expiry FROM assets WHERE warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL ? DAY) AND status!='retired'");
$rows->execute([$wDays]); $rows=$rows->fetchAll();
foreach($rows as $r){ createNotification($db,null,'Warranty Expiring: '.$r['name'],"Asset {$r['asset_code']} warranty expires ".formatDate($r['warranty_expiry']),'warning',APP_URL.'/pages/assets.php?id='.$r['id']); $count++; }
// License alerts
$lic=$db->prepare("SELECT id,software_name,expiry_date FROM licenses WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL ? DAY) AND status='active'");
$lic->execute([$lDays]); $lic=$lic->fetchAll();
foreach($lic as $l){ createNotification($db,null,'License Expiring: '.$l['software_name'],"License expires ".formatDate($l['expiry_date']),'warning',APP_URL.'/pages/licenses.php?id='.$l['id']); $count++; }
flash('success',"$count expiry alerts created.");
redirect(APP_URL.'/pages/settings.php');
