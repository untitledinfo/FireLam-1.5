<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = fl_require_admin();
$db = fl_db();

$userCount = (int) $db->query('SELECT COUNT(*) c FROM users')->fetch()['c'];
$convCount = (int) $db->query('SELECT COUNT(*) c FROM conversations')->fetch()['c'];
$msgToday = (int) $db->query("SELECT COUNT(*) c FROM messages WHERE date(created_at) = date('now')")->fetch()['c'];
$failedLogins24h = (int) $db->query("SELECT COUNT(*) c FROM logs WHERE type='login_failed' AND created_at >= datetime('now','-1 day')")->fetch()['c'];

// Quick live check against Ollama.
$ollamaUrl = rtrim((string) fl_setting('ollama_url', 'http://127.0.0.1:11434'), '/');
$modelOnline = false;
$ch = curl_init($ollamaUrl . '/api/tags');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3]);
$resp = curl_exec($ch);
if ($resp !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) {
    $modelOnline = true;
}
curl_close($ch);

$recentLogs = $db->query('SELECT * FROM logs ORDER BY id DESC LIMIT 8')->fetchAll();

$pageTitle = 'Dashboard';
$active = 'dashboard';
require __DIR__ . '/../partials_head.php';
?>
<div class="app-shell">
  <?php require __DIR__ . '/../partials_sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <h1>Dashboard</h1>
      <div class="mono" style="color:var(--paper-dim); font-size:.85rem; display:flex; align-items:center; gap:7px;">
        <span class="ember-dot <?= $modelOnline ? '' : 'off' ?>"></span>
        Model server <?= $modelOnline ? 'online' : 'unreachable' ?>
        (<?= fl_h(fl_setting('model_name', '')) ?>)
      </div>
    </div>

    <div class="grid-stats">
      <div class="card"><div class="stat-num"><?= $userCount ?></div><div class="stat-label">Users</div></div>
      <div class="card"><div class="stat-num"><?= $convCount ?></div><div class="stat-label">Conversations</div></div>
      <div class="card"><div class="stat-num"><?= $msgToday ?></div><div class="stat-label">Messages today</div></div>
      <div class="card"><div class="stat-num"><?= $failedLogins24h ?></div><div class="stat-label">Failed logins (24h)</div></div>
    </div>

    <div class="card" style="margin-top:20px;">
      <h3>Recent activity</h3>
      <table>
        <thead><tr><th>Type</th><th>Message</th><th>IP</th><th>When</th></tr></thead>
        <tbody>
        <?php foreach ($recentLogs as $l): ?>
          <tr>
            <td><span class="badge <?= str_contains($l['type'], 'failed') || $l['type'] === 'error' ? 'badge-danger' : 'badge-ok' ?>"><?= fl_h($l['type']) ?></span></td>
            <td><?= fl_h($l['message']) ?></td>
            <td class="mono"><?= fl_h((string) $l['ip']) ?></td>
            <td class="mono"><?= fl_h($l['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$recentLogs): ?>
          <tr><td colspan="4" style="color:var(--ash);">No activity yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
