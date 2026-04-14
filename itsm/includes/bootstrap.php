<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../includes/functions.php';

$db   = Database::getInstance();
$auth = Auth::getInstance();

// Determine active language
$lang = 'en';
if ($auth->isLoggedIn()) {
    $lang = $auth->getUser()['language'] ?? 'en';
} elseif (!empty($_SESSION['lang'])) {
    $lang = in_array($_SESSION['lang'], ['en','ar']) ? $_SESSION['lang'] : 'en';
} elseif (!empty($_COOKIE['lang'])) {
    $lang = in_array($_COOKIE['lang'], ['en','ar']) ? $_COOKIE['lang'] : 'en';
}

if (!defined('CURRENT_LANG')) {
    define('CURRENT_LANG', $lang);
    define('IS_RTL', $lang === 'ar');
}

// Load language strings
$langFile = APP_ROOT . '/lang/' . ($lang === 'ar' ? 'ar' : 'en') . '.php';
if (file_exists($langFile)) {
    loadLang($lang);
}
