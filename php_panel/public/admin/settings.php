<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

$admin = fl_require_admin();
$db = fl_db();
$ok = '';
$error = '';

$fields = ['site_name', 'ollama_url', 'model_name', 'system_prompt', 'temperature', 'max_tokens', 'allow_registration'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fl_csrf_check();

    $temp = (float) ($_POST['temperature'] ?? 0.7);
    $maxTok = (int) ($_POST['max_tokens'] ?? 1024);

    if ($temp < 0 || $temp > 2) {
        $error = 'Temperature should be between 0 and 2.';
    } elseif ($maxTok < 1 || $maxTok > 8192) {
        $error = 'Max tokens should be between 1 and 8192.';
    } else {
        fl_set_setting('site_name', trim((string) ($_POST['site_name'] ?? 'FireLam')) ?: 'FireLam');
        fl_set_setting('ollama_url', rtrim(trim((string) ($_POST['ollama_url'] ?? '')), '/'));
        fl_set_setting('model_name', trim((string) ($_POST['model_name'] ?? 'firelam-1.5')));
        fl_set_setting('system_prompt', (string) ($_POST['system_prompt'] ?? ''));
        fl_set_setting('temperature', (string) $temp);
        fl_set_setting('max_tokens', (string) $maxTok);
        fl_set_setting('allow_registration', isset($_POST['allow_registration']) ? '1' : '0');
        fl_log('admin_action', 'Updated settings', (int) $admin['id']);
        $ok = 'Settings saved.';
    }
}

$vals = [];
foreach ($fields as $f) {
    $vals[$f] = fl_setting($f, '');
}

$pageTitle = 'Settings';
$active = 'settings';
$user = $admin;
require __DIR__ . '/../partials_head.php';
?>
<div class="app-shell">
  <?php require __DIR__ . '/../partials_sidebar.php'; ?>
  <div class="main main-narrow" style="max-width:640px; margin:0;">
    <div class="topbar"><h1>Settings</h1></div>

    <?php if ($error): ?><div class="alert alert-error"><?= fl_h($error) ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert alert-ok"><?= fl_h($ok) ?></div><?php endif; ?>

    <div class="card">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= fl_h(fl_csrf_token()) ?>">

        <label>Site name</label>
        <input type="text" name="site_name" value="<?= fl_h($vals['site_name']) ?>">

        <label>Ollama URL</label>
        <input type="text" name="ollama_url" value="<?= fl_h($vals['ollama_url']) ?>">
        <div class="help-text">Base URL of the Ollama server hosting your FireLam model, e.g. http://127.0.0.1:11434</div>

        <label>Model name</label>
        <input type="text" name="model_name" value="<?= fl_h($vals['model_name']) ?>">
        <div class="help-text">Must match a model already pulled/created in Ollama (see <code>ollama list</code>).</div>

        <label>System prompt</label>
        <textarea name="system_prompt" rows="4"><?= fl_h($vals['system_prompt']) ?></textarea>

        <div style="display:flex; gap:16px;">
          <div style="flex:1;">
            <label>Temperature</label>
            <input type="number" step="0.1" min="0" max="2" name="temperature" value="<?= fl_h($vals['temperature']) ?>">
          </div>
          <div style="flex:1;">
            <label>Max tokens</label>
            <input type="number" min="1" max="8192" name="max_tokens" value="<?= fl_h($vals['max_tokens']) ?>">
          </div>
        </div>

        <label style="display:flex; align-items:center; gap:8px; text-transform:none; font-family:var(--font-body); margin-top:18px;">
          <input type="checkbox" name="allow_registration" style="width:auto;" <?= $vals['allow_registration'] === '1' ? 'checked' : '' ?>>
          Allow self-registration (currently self-registration is not exposed in this build — admins add users)
        </label>

        <div style="margin-top:20px;">
          <button class="btn" type="submit">Save settings</button>
        </div>
      </form>
    </div>
  </div>
</div>
</body>
</html>
