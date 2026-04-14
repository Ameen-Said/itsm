<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requireCsrf();
$auth->requirePermission('email','add');
$id=$isEdit=!empty($_POST['id'])&&(int)$_POST['id']>0?(int)$_POST['id']:null;
$name=trim($_POST['name']??'');
$host=trim($_POST['smtp_host']??'');
$port=(int)($_POST['smtp_port']??587);
$user=trim($_POST['smtp_user']??'');
$pass=$_POST['smtp_pass']??'';
$from=trim($_POST['from_email']??'');
$fromName=trim($_POST['from_name']??'');
$isDef=!empty($_POST['is_default'])?1:0;
if (!$name||!$host) jsonResponse(['success'=>false,'message'=>'Name and host required.']);
if ($isDef) $db->query("UPDATE email_configs SET is_default=0");
try {
    if ($id) {
        $db->prepare("UPDATE email_configs SET name=?,smtp_host=?,smtp_port=?,smtp_user=?,from_email=?,from_name=?,is_default=? WHERE id=?")->execute([$name,$host,$port,$user,$from,$fromName,$isDef,$id]);
        if ($pass) $db->prepare("UPDATE email_configs SET smtp_pass=? WHERE id=?")->execute([base64_encode($pass),$id]);
    } else {
        $db->prepare("INSERT INTO email_configs (name,smtp_host,smtp_port,smtp_user,smtp_pass,from_email,from_name,is_default) VALUES (?,?,?,?,?,?,?,?)")->execute([$name,$host,$port,$user,base64_encode($pass),$from,$fromName,$isDef]);
    }
    jsonResponse(['success'=>true,'message'=>'Config saved.','reload'=>true]);
} catch(PDOException $e){ jsonResponse(['success'=>false,'message'=>'DB error.'],500); }
