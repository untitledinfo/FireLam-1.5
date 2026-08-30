<?php
/**
 * Renders the sidebar nav. Expects $user (array) and $active (string: 'chat'|'dashboard'|'users'|'settings'|'logs').
 */
$siteName = fl_setting('site_name', 'FireLam');
$active = $active ?? '';
function fl_nav_class(string $key, string $active): string
{
    return 'nav-link' . ($key === $active ? ' active' : '');
}
?>
<div class="sidebar">
  <div class="brand"><span class="ember-dot"></span> <?= fl_h($siteName) ?></div>

  <a class="<?= fl_nav_class('chat', $active) ?>" href="/chat.php">&#9679; Chat</a>

  <?php if ($user['role'] === 'admin'): ?>
    <div class="nav-section-label">Admin</div>
    <a class="<?= fl_nav_class('dashboard', $active) ?>" href="/admin/index.php">Dashboard</a>
    <a class="<?= fl_nav_class('users', $active) ?>" href="/admin/users.php">Users</a>
    <a class="<?= fl_nav_class('settings', $active) ?>" href="/admin/settings.php">Settings</a>
    <a class="<?= fl_nav_class('logs', $active) ?>" href="/admin/logs.php">Logs</a>
  <?php endif; ?>

  <div class="sidebar-footer">
    <?= fl_h($user['username']) ?> · <a href="/logout.php">Sign out</a>
  </div>
</div>
