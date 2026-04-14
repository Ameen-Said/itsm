<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$type = $_GET['type'] ?? 'assets';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$type.'_template.csv"');
$out = fopen('php://output','w');
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
if ($type==='users') {
    fputcsv($out,['full_name','email','username','phone','job_title','employee_id','department','role']);
    fputcsv($out,['John Smith','john@company.com','jsmith','+1234567890','IT Engineer','EMP001','Information Technology','IT Staff']);
} else {
    fputcsv($out,['name','brand','model','serial_number','category','purchase_date','warranty_expiry','price','status','location','notes']);
    fputcsv($out,['Dell Latitude 5520','Dell','Latitude 5520','SN12345','Laptop','2024-01-15','2027-01-15','1500.00','available','Office A','Good condition']);
}
fclose($out);
