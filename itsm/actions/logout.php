<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$auth->logout();
redirect(APP_URL . '/login.php');
