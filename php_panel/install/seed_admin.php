<?php
/**
 * One-shot CLI helper: creates (or resets) the initial admin account.
 * Usage: php install/seed_admin.php <username> <password>
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script is for command-line use only.\n");
    exit(1);
}

[, $username, $password] = array_pad($argv, 3, null);
if (!$username || !$password) {
    fwrite(STDERR, "Usage: php install/seed_admin.php <username> <password>\n");
    exit(1);
}
if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}

require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';

$pdo = fl_db();
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    'INSERT INTO users (username, password_hash, role, is_active) VALUES (:u, :p, \'admin\', 1)
     ON CONFLICT(username) DO UPDATE SET password_hash = excluded.password_hash, role = \'admin\', is_active = 1'
);
$stmt->execute(['u' => $username, 'p' => $hash]);

echo "Admin user '$username' is ready.\n";
