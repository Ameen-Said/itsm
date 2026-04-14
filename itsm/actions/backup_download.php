<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
if (!$auth->isAdmin()) { http_response_code(403); exit('Forbidden'); }
if (!$auth->verifyCsrfToken($_GET['csrf_token']??null)) { http_response_code(403); exit('CSRF error'); }
$file = basename($_GET['file']??'');
$path = APP_ROOT.'/backups/'.$file;
if (!$file||!file_exists($path)||!str_ends_with($file,'.sql')) { http_response_code(404); exit('Not found'); }
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.$file.'"');
header('Content-Length: '.filesize($path));
readfile($path); exit;
