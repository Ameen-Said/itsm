<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requirePermission('users','add');
if (!$auth->verifyCsrfToken($_POST['csrf_token']??null)) { flash('danger','CSRF error.'); redirect(APP_URL.'/pages/import.php'); }
if (empty($_FILES['csv_file'])||$_FILES['csv_file']['error']!==UPLOAD_ERR_OK) { flash('danger','No file uploaded.'); redirect(APP_URL.'/pages/import.php'); }

$defaultRole = (int)($_POST['default_role_id']??5);
$defaultDept = !empty($_POST['default_dept_id'])?(int)$_POST['default_dept_id']:null;

$handle=fopen($_FILES['csv_file']['tmp_name'],'r');
if (!$handle) { flash('danger','Cannot read file.'); redirect(APP_URL.'/pages/import.php'); }
$header=fgetcsv($handle);
if (!$header) { flash('danger','Empty CSV.'); fclose($handle); redirect(APP_URL.'/pages/import.php'); }
$header=array_map(fn($h)=>strtolower(trim($h)),$header);

$deptMap=[]; foreach($db->query("SELECT id,LOWER(name) as n FROM departments")->fetchAll() as $r) $deptMap[$r['n']]=$r['id'];
$roleMap=[]; foreach($db->query("SELECT id,LOWER(name) as n FROM roles")->fetchAll() as $r) $roleMap[$r['n']]=$r['id'];

$imported=0; $errors=[]; $row=0;
$stmt=$db->prepare("INSERT INTO users (full_name,email,username,password_hash,phone,job_title,employee_id,role_id,department_id,status) VALUES (?,?,?,?,?,?,?,?,?,'active')");

while(($data=fgetcsv($handle))!==false) {
    $row++;
    if (count($data)<2) continue;
    $r=array_combine($header,array_pad($data,count($header),''));
    $name=trim($r['full_name']??''); $email=trim($r['email']??'');
    if (!$name||!$email) { $errors[]="Row $row: full_name and email required."; continue; }
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) { $errors[]="Row $row: invalid email '$email'."; continue; }
    $dup=$db->prepare("SELECT id FROM users WHERE email=?"); $dup->execute([$email]);
    if ($dup->fetch()) { $errors[]="Row $row: email '$email' already exists."; continue; }
    $username=trim($r['username']??'')?:strtolower(str_replace(' ','.', $name)).rand(10,99);
    $dept=!empty($r['department'])?($deptMap[strtolower(trim($r['department']))]??$defaultDept):$defaultDept;
    $role=!empty($r['role'])?($roleMap[strtolower(trim($r['role']))]??$defaultRole):$defaultRole;
    $pass=bin2hex(random_bytes(6));
    $hash=password_hash($pass,PASSWORD_BCRYPT,['cost'=>BCRYPT_COST]);
    try { $stmt->execute([$name,$email,$username,$hash,trim($r['phone']??''),trim($r['job_title']??''),trim($r['employee_id']??''),$role,$dept]); $imported++; }
    catch(PDOException $e) { $errors[]="Row $row: ".$e->getMessage(); }
}
fclose($handle);
$auth->logAudit($auth->getUserId(),'bulk_import','users',null,['imported'=>$imported,'errors'=>count($errors)]);
$msg="Import complete: <strong>$imported</strong> employees created.";
if ($errors) $msg.=' '.count($errors).' errors.';
flash($imported>0?'success':'warning',$msg);
redirect(APP_URL.'/pages/import.php');
