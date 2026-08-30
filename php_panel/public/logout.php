<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$user = fl_current_user();
if ($user) {
    fl_log('logout', "User '{$user['username']}' logged out", (int) $user['id']);
}
fl_logout();
header('Location: /login.php');
exit;
