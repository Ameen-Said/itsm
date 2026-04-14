<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requireCsrf();
$isEdit = !empty($_POST['id']) && (int)$_POST['id'] > 0;
$isEdit ? $auth->requirePermission('users','edit') : $auth->requirePermission('users','add');

$id         = $isEdit ? (int)$_POST['id'] : null;
$fullName   = trim($_POST['full_name']  ?? '');
$username   = trim($_POST['username']   ?? '');
$email      = trim($_POST['email']      ?? '');
$phone      = trim($_POST['phone']      ?? '');
$jobTitle   = trim($_POST['job_title']  ?? '');
$employeeId = trim($_POST['employee_id']?? '') ?: null;
$roleId     = (int)($_POST['role_id']   ?? 0);
$deptId     = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
$status     = in_array($_POST['status']??'',['active','inactive','suspended']) ? $_POST['status'] : 'active';
$password   = $_POST['password'] ?? '';

if (!$fullName||!$username||!$email||!$roleId) jsonResponse(['success'=>false,'message'=>'Required fields missing.']);
if (!filter_var($email,FILTER_VALIDATE_EMAIL)) jsonResponse(['success'=>false,'message'=>'Invalid email.']);
if (!$isEdit && !$password) jsonResponse(['success'=>false,'message'=>'Password is required for new users.']);
if ($password && strlen($password)<8) jsonResponse(['success'=>false,'message'=>'Password must be at least 8 characters.']);
if (!$auth->isAdmin() && $roleId===1) jsonResponse(['success'=>false,'message'=>'Cannot assign Administrator role.']);

$dup = $db->prepare("SELECT id FROM users WHERE (username=? OR email=?) AND id!=?");
$dup->execute([$username,$email,$id??0]);
if ($dup->fetch()) jsonResponse(['success'=>false,'message'=>'Username or email already exists.']);

try {
    if ($isEdit) {
        $db->prepare("UPDATE users SET full_name=?,username=?,email=?,phone=?,job_title=?,employee_id=?,role_id=?,department_id=?,status=? WHERE id=?")
           ->execute([$fullName,$username,$email,$phone,$jobTitle,$employeeId,$roleId,$deptId,$status,$id]);
        if ($password) {
            $hash = password_hash($password,PASSWORD_BCRYPT,['cost'=>BCRYPT_COST]);
            $db->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash,$id]);
        }
        // Avatar upload
        if (!empty($_FILES['avatar']['name'])) {
            $img = handleUpload($_FILES['avatar'],'avatars',ALLOWED_IMG_EXT);
            if ($img['success']) {
                $old = $db->prepare("SELECT avatar FROM users WHERE id=?"); $old->execute([$id]); $old=$old->fetchColumn();
                if ($old && file_exists(UPLOAD_DIR.'avatars/'.$old)) @unlink(UPLOAD_DIR.'avatars/'.$old);
                $db->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$img['filename'],$id]);
            }
        }
        $auth->logAudit($auth->getUserId(),'edit','users',$id);
        jsonResponse(['success'=>true,'message'=>'Employee updated.','reload'=>true]);
    } else {
        $hash = password_hash($password,PASSWORD_BCRYPT,['cost'=>BCRYPT_COST]);
        $db->prepare("INSERT INTO users (full_name,username,email,password_hash,phone,job_title,employee_id,role_id,department_id,status) VALUES (?,?,?,?,?,?,?,?,?,?)")
           ->execute([$fullName,$username,$email,$hash,$phone,$jobTitle,$employeeId,$roleId,$deptId,$status]);
        $newId=(int)$db->lastInsertId();
        // Avatar upload
        if (!empty($_FILES['avatar']['name'])) {
            $img = handleUpload($_FILES['avatar'],'avatars',ALLOWED_IMG_EXT);
            if ($img['success']) $db->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$img['filename'],$newId]);
        }
        $auth->logAudit($auth->getUserId(),'create','users',$newId);
        jsonResponse(['success'=>true,'message'=>'Employee created.','reload'=>true]);
    }
} catch (PDOException $e) {
    error_log('[user_save] '.$e->getMessage());
    jsonResponse(['success'=>false,'message'=>'Database error.'], 500);
}
