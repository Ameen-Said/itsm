<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requireCsrf();
if (!empty($_POST['warranty_days'])) setSetting('warranty_alert_days',(string)(int)$_POST['warranty_days']);
if (!empty($_POST['license_days']))  setSetting('license_alert_days', (string)(int)$_POST['license_days']);
jsonResponse(['success'=>true,'message'=>'Alert settings saved.']);
