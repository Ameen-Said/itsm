<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$auth->requirePermission('settings', 'edit');

// Support both AJAX and regular form POST
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_POST['_ajax']);

if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    if ($isAjax) jsonResponse(['success' => false, 'message' => 'CSRF token invalid.'], 403);
    flash('danger', 'CSRF token invalid.');
    redirect(APP_URL . '/pages/settings.php');
}

$keys = [
    'company_name', 'date_format', 'currency', 'timezone',
    'session_timeout', 'warranty_alert_days', 'license_alert_days',
    'pagination_limit', 'maintenance_mode', 'default_language'
];

try {
    foreach ($keys as $key) {
        $val = $_POST[$key] ?? '';
        if ($key === 'maintenance_mode') {
            $val = !empty($_POST[$key]) ? '1' : '0';
        } elseif (in_array($key, ['session_timeout','warranty_alert_days','license_alert_days','pagination_limit'])) {
            $val = (string)max(1, (int)$val);
        } else {
            $val = trim($val);
        }
        setSetting($key, $val);
    }

    // Handle logo upload
    if (!empty($_FILES['company_logo']['name']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
        $img = handleUpload($_FILES['company_logo'], 'logos', ALLOWED_IMG_EXT);
        if ($img['success']) {
            $old = getSetting('company_logo', '');
            if ($old && file_exists(UPLOAD_DIR . 'logos/' . $old)) {
                @unlink(UPLOAD_DIR . 'logos/' . $old);
            }
            setSetting('company_logo', $img['filename']);
        }
    }

    $auth->logAudit($auth->getUserId(), 'settings_update', 'system');

    if ($isAjax) {
        jsonResponse(['success' => true, 'message' => 'Settings saved successfully.']);
    }
    flash('success', 'Settings saved successfully.');
} catch (Throwable $e) {
    error_log('[settings_save] ' . $e->getMessage());
    if ($isAjax) jsonResponse(['success' => false, 'message' => 'Error saving settings.'], 500);
    flash('danger', 'Error saving settings: ' . $e->getMessage());
}

redirect(APP_URL . '/pages/settings.php');
