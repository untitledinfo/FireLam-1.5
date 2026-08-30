<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (fl_current_user()) {
    header('Location: /chat.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fl_csrf_check();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Enter your username and password.';
    } elseif (fl_attempt_login($username, $password)) {
        header('Location: /chat.php');
        exit;
    } else {
        $error = 'Incorrect username or password.';
    }
}

$pageTitle = 'Sign in';
$siteName = fl_setting('site_name', 'FireLam');
require __DIR__ . '/partials_head.php';
?>
<div class="auth-shell">
  <div class="auth-card">
    <div class="auth-brand"><span class="ember-dot"></span> <?= fl_h($siteName) ?></div>
    <div class="auth-sub">sign in to continue</div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= fl_h($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/login.php">
      <input type="hidden" name="csrf" value="<?= fl_h(fl_csrf_token()) ?>">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" autocomplete="username" autofocus required>
      <label for="password">Password</label>
      <input type="password" id="password" name="password" autocomplete="current-password" required>
      <div style="margin-top:20px;">
        <button class="btn" type="submit" style="width:100%; justify-content:center;">Sign in</button>
      </div>
    </form>
  </div>
</div>
</body>
</html>
