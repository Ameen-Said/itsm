<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requireCsrf();
$auth->requirePermission('documents','add');
$name = trim($_POST['name']??'');
$desc = trim($_POST['description']??'');
if (!$name) jsonResponse(['success'=>false,'message'=>'Folder name required.']);
try {
    $db->prepare("INSERT INTO document_folders (name,description,created_by) VALUES (?,?,?)")->execute([$name,$desc,$auth->getUserId()]);
    jsonResponse(['success'=>true,'message'=>'Folder created.','reload'=>true]);
} catch(PDOException $e) {
    jsonResponse(['success'=>false,'message'=>'Error: '.$e->getMessage()],500);
}
