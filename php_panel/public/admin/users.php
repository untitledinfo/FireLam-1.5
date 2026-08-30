<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

$admin = fl_require_admin();
$db = fl_db();
$error = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fl_csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

        if (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
            $error = 'Username must be at least 3 characters (letters, numbers, . _ - only).';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            try {
                $stmt = $db->prepare('INSERT INTO users (username, password_hash, role) VALUES (:u, :p, :r)');
                $stmt->execute(['u' => $username, 'p' => password_hash($password, PASSWORD_DEFAULT), 'r' => $role]);
                fl_log('admin_action', "Created user '$username' ($role)", (int) $admin['id']);
                $ok = "User '$username' created.";
            } catch (PDOException $e) {
                $error = 'That username is already taken.';
            }
        }
    } elseif ($action === 'toggle_active') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) $admin['id']) {
            $error = "You can't deactivate your own account.";
        } else {
            $db->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = :id')->execute(['id' => $id]);
            fl_log('admin_action', "Toggled active state for user #$id", (int) $admin['id']);
            $ok = 'Updated.';
        }
    } elseif ($action === 'reset_password') {
        $id = (int) ($_POST['id'] ?? 0);
        $password = (string) ($_POST['password'] ?? '');
        if (strlen($password) < 8) {
            $error = 'New password must be at least 8 characters.';
        } else {
            $db->prepare('UPDATE users SET password_hash = :p WHERE id = :id')
               ->execute(['p' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id]);
            fl_log('admin_action', "Reset password for user #$id", (int) $admin['id']);
            $ok = 'Password updated.';
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) $admin['id']) {
            $error = "You can't delete your own account.";
        } else {
            $db->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $id]);
            fl_log('admin_action', "Deleted user #$id", (int) $admin['id']);
            $ok = 'User deleted.';
        }
    }
}

$users = $db->query('SELECT id, username, role, is_active, created_at, last_login_at FROM users ORDER BY id ASC')->fetchAll();

$pageTitle = 'Users';
$active = 'users';
$user = $admin;
require __DIR__ . '/../partials_head.php';
?>
<div class="app-shell">
  <?php require __DIR__ . '/../partials_sidebar.php'; ?>
  <div class="main">
    <div class="topbar"><h1>Users</h1></div>

    <?php if ($error): ?><div class="alert alert-error"><?= fl_h($error) ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert alert-ok"><?= fl_h($ok) ?></div><?php endif; ?>

    <div class="card">
      <h3>Add a user</h3>
      <form method="post" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
        <input type="hidden" name="csrf" value="<?= fl_h(fl_csrf_token()) ?>">
        <input type="hidden" name="action" value="create">
        <div style="flex:1; min-width:160px;"><label>Username</label><input type="text" name="username" required></div>
        <div style="flex:1; min-width:160px;"><label>Password</label><input type="password" name="password" required minlength="8"></div>
        <div style="min-width:120px;">
          <label>Role</label>
          <select name="role"><option value="user">user</option><option value="admin">admin</option></select>
        </div>
        <button class="btn" type="submit">Add user</button>
      </form>
    </div>

    <div class="card">
      <h3>All users</h3>
      <table>
        <thead><tr><th>Username</th><th>Role</th><th>Status</th><th>Last login</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= fl_h($u['username']) ?></td>
            <td><span class="badge <?= $u['role'] === 'admin' ? 'badge-admin' : 'badge-user' ?>"><?= fl_h($u['role']) ?></span></td>
            <td><span class="badge <?= $u['is_active'] ? 'badge-ok' : 'badge-danger' ?>"><?= $u['is_active'] ? 'active' : 'disabled' ?></span></td>
            <td class="mono"><?= fl_h((string) ($u['last_login_at'] ?? '—')) ?></td>
            <td>
              <details style="display:inline-block;">
                <summary class="btn btn-ghost btn-sm" style="display:inline-flex; cursor:pointer;">Manage</summary>
                <div style="margin-top:8px; display:flex; flex-direction:column; gap:8px; min-width:220px;">
                  <form method="post" style="display:flex; gap:6px;">
                    <input type="hidden" name="csrf" value="<?= fl_h(fl_csrf_token()) ?>">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                    <input type="password" name="password" placeholder="New password" minlength="8" required style="flex:1;">
                    <button class="btn btn-sm" type="submit">Reset</button>
                  </form>
                  <div style="display:flex; gap:6px;">
                    <form method="post" style="flex:1;">
                      <input type="hidden" name="csrf" value="<?= fl_h(fl_csrf_token()) ?>">
                      <input type="hidden" name="action" value="toggle_active">
                      <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                      <button class="btn btn-ghost btn-sm" type="submit" style="width:100%;"><?= $u['is_active'] ? 'Disable' : 'Enable' ?></button>
                    </form>
                    <form method="post" style="flex:1;" onsubmit="return confirm('Delete this user permanently?');">
                      <input type="hidden" name="csrf" value="<?= fl_h(fl_csrf_token()) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                      <button class="btn btn-danger btn-sm" type="submit" style="width:100%;">Delete</button>
                    </form>
                  </div>
                </div>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
