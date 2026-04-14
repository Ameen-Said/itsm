<?php
require_once __DIR__ . '/includes/bootstrap.php';
if ($auth->isLoggedIn()) {
    redirect(APP_URL . '/pages/dashboard.php');
} else {
    redirect(APP_URL . '/login.php');
}
