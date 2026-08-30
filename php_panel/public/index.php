<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

header('Location: ' . (fl_current_user() ? '/chat.php' : '/login.php'));
exit;
