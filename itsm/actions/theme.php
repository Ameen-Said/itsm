<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_GET['csrf_token'] ?? null;
if (!$auth->verifyCsrfToken($token)) {
    jsonResponse(['success' => false, 'message' => 'CSRF invalid.'], 403);
}
$theme = in_array($_POST['theme'] ?? '', ['light','dark']) ? $_POST['theme'] : 'light';
$auth->updateTheme($theme);
// Also update session so current page knows immediately
$_SESSION['theme'] = $theme;
jsonResponse(['success' => true, 'theme' => $theme]);
