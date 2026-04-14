<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->requireLogin();
$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!$auth->verifyCsrfToken($token)) {
    jsonResponse(['success' => false, 'message' => 'CSRF invalid.'], 403);
}
$lang = in_array($_POST['lang'] ?? '', ['en','ar']) ? $_POST['lang'] : 'en';
$auth->updateLang($lang);
$_SESSION['lang'] = $lang;
setcookie('itsm_lang', $lang, time() + (365*24*3600), '/', '', false, true);
jsonResponse(['success' => true, 'lang' => $lang]);
