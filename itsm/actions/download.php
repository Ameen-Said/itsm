<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requirePermission('documents','view');

$id      = (int)($_GET['id'] ?? 0);
$preview = !empty($_GET['preview']);

if (!$id) { http_response_code(400); exit('Invalid request.'); }

$stmt = $db->prepare("SELECT * FROM documents WHERE id=?");
$stmt->execute([$id]);
$doc  = $stmt->fetch();

if (!$doc) { http_response_code(404); exit('Document not found.'); }

$filePath = UPLOAD_DIR . 'documents/' . $doc['filename'];
if (!file_exists($filePath)) { http_response_code(404); exit('File not found on disk.'); }

// Security: prevent path traversal
$realPath   = realpath($filePath);
$realUpload = realpath(UPLOAD_DIR . 'documents');
if (!$realPath || !str_starts_with($realPath, $realUpload)) { http_response_code(403); exit('Access denied.'); }

$auth->logAudit($auth->getUserId(), $preview?'preview':'download', 'documents', $id);

$mime = $doc['mime_type'] ?: (mime_content_type($filePath) ?: 'application/octet-stream');
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');

if ($preview && in_array($mime, ['application/pdf','image/jpeg','image/png','image/gif','image/webp'])) {
    header('Content-Disposition: inline; filename="' . addslashes($doc['original_name']) . '"');
} else {
    header('Content-Disposition: attachment; filename="' . addslashes($doc['original_name']) . '"');
    header('Cache-Control: no-cache');
}
readfile($filePath);
exit;
