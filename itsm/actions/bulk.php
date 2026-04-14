<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requireCsrf();

$action = $_POST['action'] ?? '';
$ids    = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));

if (empty($ids)) jsonResponse(['success'=>false,'message'=>'No items selected.']);

try {
    switch ($action) {
        case 'delete_assets':
            $auth->requirePermission('assets','delete');
            $db->prepare("DELETE FROM assets WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")")->execute($ids);
            $auth->logAudit($auth->getUserId(),'bulk_delete','assets',null,['ids'=>$ids]);
            jsonResponse(['success'=>true,'message'=>count($ids).' assets deleted.']);

        case 'delete_users':
            $auth->requirePermission('users','delete');
            $ids = array_filter($ids, fn($id) => $id !== $auth->getUserId());
            if (empty($ids)) jsonResponse(['success'=>false,'message'=>'Cannot delete yourself.']);
            $db->prepare("DELETE FROM users WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")")->execute($ids);
            $auth->logAudit($auth->getUserId(),'bulk_delete','users',null,['ids'=>$ids]);
            jsonResponse(['success'=>true,'message'=>count($ids).' employees deleted.']);

        case 'delete_licenses':
            $auth->requirePermission('licenses','delete');
            $db->prepare("DELETE FROM licenses WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")")->execute($ids);
            jsonResponse(['success'=>true,'message'=>count($ids).' licenses deleted.']);

        case 'delete_documents':
            $auth->requirePermission('documents','delete');
            $stmt = $db->prepare("SELECT filename FROM documents WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll() as $doc) {
                $path = UPLOAD_DIR . 'documents/' . $doc['filename'];
                if (file_exists($path)) @unlink($path);
            }
            $db->prepare("DELETE FROM documents WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")")->execute($ids);
            jsonResponse(['success'=>true,'message'=>count($ids).' documents deleted.']);

        case 'mark_assets_maintenance':
            $auth->requirePermission('assets','edit');
            $db->prepare("UPDATE assets SET status='maintenance' WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")")->execute($ids);
            jsonResponse(['success'=>true,'message'=>count($ids).' assets updated to Maintenance.']);

        case 'mark_assets_retired':
            $auth->requirePermission('assets','edit');
            $db->prepare("UPDATE assets SET status='retired', assigned_to=NULL WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")")->execute($ids);
            jsonResponse(['success'=>true,'message'=>count($ids).' assets retired.']);

        case 'activate_users':
            $auth->requirePermission('users','edit');
            $db->prepare("UPDATE users SET status='active' WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")")->execute($ids);
            jsonResponse(['success'=>true,'message'=>count($ids).' employees activated.']);

        case 'deactivate_users':
            $auth->requirePermission('users','edit');
            $db->prepare("UPDATE users SET status='inactive' WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")")->execute($ids);
            jsonResponse(['success'=>true,'message'=>count($ids).' employees deactivated.']);

        default:
            jsonResponse(['success'=>false,'message'=>'Unknown bulk action: '.$action]);
    }
} catch (PDOException $e) {
    error_log('[bulk] '.$e->getMessage());
    jsonResponse(['success'=>false,'message'=>'Database error.'], 500);
}
