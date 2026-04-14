<?php
// ============================================================
// IT Manager Pro — Production Configuration
// ============================================================
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_NAME',    getenv('DB_NAME')    ?: 'itsm_db');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME',    'IT Manager Pro');
define('APP_VERSION', '2.0.0');
define('APP_URL',     rtrim(getenv('APP_URL') ?: 'http://localhost/itsm', '/'));
define('APP_ROOT',    dirname(__DIR__));

define('UPLOAD_DIR',  APP_ROOT . '/uploads/');
define('MAX_FILE_SIZE', 20 * 1024 * 1024);

define('ALLOWED_EXTENSIONS', [
    'pdf','doc','docx','xls','xlsx',
    'png','jpg','jpeg','gif','webp',
    'zip','txt','csv'
]);
define('ALLOWED_IMG_EXT', ['png','jpg','jpeg','gif','webp']);

define('ENCRYPTION_KEY', getenv('ENCRYPTION_KEY') ?: 'ChangeThis32CharSecretKey!!2024!');
define('SESSION_TIMEOUT', 3600);
define('BCRYPT_COST', 12);
define('PAGINATION_LIMIT', 25);
define('LOG_DIR', APP_ROOT . '/logs/');

date_default_timezone_set(getenv('TZ') ?: 'UTC');

if (getenv('APP_ENV') === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', LOG_DIR . 'php_errors.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}
