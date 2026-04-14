<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requirePermission('assets','add');
if (!$auth->verifyCsrfToken($_POST['csrf_token']??null)) { flash('danger','CSRF error.'); redirect(APP_URL.'/pages/import.php'); }
if (empty($_FILES['csv_file'])||$_FILES['csv_file']['error']!==UPLOAD_ERR_OK) { flash('danger','No file uploaded.'); redirect(APP_URL.'/pages/import.php'); }
if ($_FILES['csv_file']['size']>5*1024*1024) { flash('danger','File too large.'); redirect(APP_URL.'/pages/import.php'); }

$skipErrors = !empty($_POST['skip_errors']);
$handle = fopen($_FILES['csv_file']['tmp_name'],'r');
if (!$handle) { flash('danger','Cannot read file.'); redirect(APP_URL.'/pages/import.php'); }

$header = fgetcsv($handle);
if (!$header) { flash('danger','Empty CSV file.'); fclose($handle); redirect(APP_URL.'/pages/import.php'); }
$header = array_map(fn($h)=>strtolower(trim($h)), $header);

// Category map
$catMap=[];
foreach($db->query("SELECT id,LOWER(name) as name FROM asset_categories")->fetchAll() as $r) $catMap[$r['name']]=$r['id'];

$imported=0; $errors=[]; $row=0;
$stmt=$db->prepare("INSERT INTO assets (asset_code,barcode,name,category_id,brand,model,serial_number,purchase_date,warranty_expiry,price,status,location,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");

while(($data=fgetcsv($handle))!==false) {
    $row++;
    if (count($data) < count($header)) continue;
    $r = array_combine($header, array_pad($data,count($header),''));
    $name = trim($r['name']??'');
    if (!$name) { $errors[]="Row $row: name required."; if(!$skipErrors)break; continue; }
    $catId = !empty($r['category'])?($catMap[strtolower(trim($r['category']))]??null):null;
    $pd    = sanitizeDate($r['purchase_date']??null);
    $we    = sanitizeDate($r['warranty_expiry']??null);
    $price = max(0,(float)preg_replace('/[^0-9.]/','',$r['price']??'0'));
    $stat  = in_array($r['status']??'',['available','assigned','maintenance','retired'])?$r['status']:'available';
    try {
        $stmt->execute([generateAssetCode(),generateBarcode(),$name,$catId,trim($r['brand']??''),trim($r['model']??''),trim($r['serial_number']??''),$pd,$we,$price,$stat,trim($r['location']??''),trim($r['notes']??'')]);
        $imported++;
    } catch(PDOException $e) { $errors[]="Row $row: ".$e->getMessage(); if(!$skipErrors)break; }
}
fclose($handle);
$auth->logAudit($auth->getUserId(),'bulk_import','assets',null,['imported'=>$imported,'errors'=>count($errors)]);
$msg="Import complete: <strong>$imported</strong> assets imported.";
if ($errors) $msg.=' '.count($errors).' errors: '.implode(' | ',array_slice($errors,0,3));
flash($imported>0?'success':'warning',$msg);
redirect(APP_URL.'/pages/import.php');
