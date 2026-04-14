<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requireCsrf();
$to=trim($_POST['to_email']??'');
if (!filter_var($to,FILTER_VALIDATE_EMAIL)) jsonResponse(['success'=>false,'message'=>'Invalid email address.']);
$db->prepare("INSERT INTO email_logs (to_email,subject,body,status) VALUES (?,?,?,'sent')")->execute([$to,'Test Email from '.APP_NAME,'This is a test email from IT Manager Pro.']);
jsonResponse(['success'=>true,'message'=>'Test email logged successfully. (Configure SMTP for actual delivery)']);
