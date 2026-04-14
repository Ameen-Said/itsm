<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requireCsrf();

$uid      = $auth->getUserId();
$fullName = trim($_POST['full_name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone'] ?? '');
$jobTitle = trim($_POST['job_title'] ?? '');
$theme    = in_array($_POST['theme']??'',['light','dark']) ? $_POST['theme'] : 'light';
$language = in_array($_POST['language']??'',['en','ar']) ? $_POST['language'] : 'en';

if (!$fullName || !$email) jsonResponse(['success'=>false,'message'=>'Name and email are required.']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(['success'=>false,'message'=>'Invalid email address.']);

$dup = $db->prepare("SELECT id FROM users WHERE email=? AND id!=?");
$dup->execute([$email, $uid]);
if ($dup->fetch()) jsonResponse(['success'=>false,'message'=>'Email already in use by another account.']);

try {
    $db->prepare("UPDATE users SET full_name=?,email=?,phone=?,job_title=?,theme=?,language=? WHERE id=?")
       ->execute([$fullName,$email,$phone,$jobTitle,$theme,$language,$uid]);
    $auth->logAudit($uid,'edit','profile',$uid);
    jsonResponse(['success'=>true,'message'=>'Profile updated successfully.']);
} catch (PDOException $e) {
    jsonResponse(['success'=>false,'message'=>'Database error.'], 500);
}
