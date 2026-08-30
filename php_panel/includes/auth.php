<?php
/**
 * Session-based auth: login, logout, guards, current-user helpers.
 */

declare(strict_types=1);

function fl_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $config = require __DIR__ . '/config.php';
    session_name($config['session_name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        // 'secure' is set at the php.ini / session.cookie_secure level behind HTTPS (see install docs).
    ]);
    session_start();
}

function fl_current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $stmt = fl_db()->prepare('SELECT id, username, role, is_active FROM users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || !$user['is_active']) {
        return null;
    }
    $cached = $user;
    return $user;
}

function fl_require_login(): array
{
    $user = fl_current_user();
    if (!$user) {
        header('Location: /login.php');
        exit;
    }
    return $user;
}

function fl_require_admin(): array
{
    $user = fl_require_login();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        die('Admins only.');
    }
    return $user;
}

function fl_attempt_login(string $username, string $password): bool
{
    $stmt = fl_db()->prepare('SELECT * FROM users WHERE username = :u AND is_active = 1');
    $stmt->execute(['u' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        fl_log('login_failed', "Failed login for '$username'");
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];

    $upd = fl_db()->prepare('UPDATE users SET last_login_at = datetime(\'now\') WHERE id = :id');
    $upd->execute(['id' => $user['id']]);

    fl_log('login_success', "User '{$user['username']}' logged in", (int) $user['id']);
    return true;
}

function fl_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path']);
    }
    session_destroy();
}
