<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requireCsrf();
$isEdit = !empty($_POST['id']) && (int)$_POST['id'] > 0;
$isEdit ? $auth->requirePermission('vendors','edit') : $auth->requirePermission('vendors','add');

$id     = $isEdit ? (int)$_POST['id'] : null;
$name   = trim($_POST['name'] ?? '');
$status = in_array($_POST['status']??'',['active','inactive']) ? $_POST['status'] : 'active';

if (!$name) jsonResponse(['success'=>false,'message'=>'Vendor name is required.']);

$fields = ['name','contact_name','email','phone','website','address','notes','status'];
$vals   = [];
foreach ($fields as $f) {
    $vals[] = $f === 'name' ? $name : ($f === 'status' ? $status : (trim($_POST[$f]??'') ?: null));
}

try {
    if ($isEdit) {
        $set = implode(',', array_map(fn($f) => "$f=?", $fields));
        $db->prepare("UPDATE vendors SET $set WHERE id=?")->execute([...$vals, $id]);
        $auth->logAudit($auth->getUserId(),'edit','vendors',$id);
    } else {
        $cols = implode(',', $fields);
        $ph   = implode(',', array_fill(0, count($fields), '?'));
        $db->prepare("INSERT INTO vendors ($cols) VALUES ($ph)")->execute($vals);
        $auth->logAudit($auth->getUserId(),'create','vendors',(int)$db->lastInsertId());
    }
    jsonResponse(['success'=>true,'message'=>'Vendor saved.','reload'=>true]);
} catch (PDOException $e) {
    jsonResponse(['success'=>false,'message'=>'Database error.'], 500);
}
