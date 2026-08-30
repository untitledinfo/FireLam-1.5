<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

$admin = fl_require_admin();
$db = fl_db();

$typeFilter = trim((string) ($_GET['type'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 40;
$offset = ($page - 1) * $perPage;

$where = '';
$params = [];
if ($typeFilter !== '') {
    $where = 'WHERE type = :t';
    $params['t'] = $typeFilter;
}

$countStmt = $db->prepare("SELECT COUNT(*) c FROM logs $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetch()['c'];

$stmt = $db->prepare("SELECT * FROM logs $where ORDER BY id DESC LIMIT :limit OFFSET :offset");
foreach ($params as $k => $v) {
    $stmt->bindValue(":$k", $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

$types = $db->query('SELECT DISTINCT type FROM logs ORDER BY type')->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Logs';
$active = 'logs';
$user = $admin;
require __DIR__ . '/../partials_head.php';
?>
<div class="app-shell">
  <?php require __DIR__ . '/../partials_sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <h1>Logs</h1>
      <form method="get" style="display:flex; gap:8px; align-items:center;">
        <select name="type" onchange="this.form.submit()">
          <option value="">All types</option>
          <?php foreach ($types as $t): ?>
            <option value="<?= fl_h($t) ?>" <?= $t === $typeFilter ? 'selected' : '' ?>><?= fl_h($t) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <div class="card">
      <table>
        <thead><tr><th>Type</th><th>Message</th><th>User</th><th>IP</th><th>When</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
          <tr>
            <td><span class="badge <?= str_contains($l['type'], 'failed') || $l['type'] === 'error' ? 'badge-danger' : 'badge-ok' ?>"><?= fl_h($l['type']) ?></span></td>
            <td><?= fl_h($l['message']) ?></td>
            <td class="mono"><?= $l['user_id'] ? '#' . (int) $l['user_id'] : '—' ?></td>
            <td class="mono"><?= fl_h((string) $l['ip']) ?></td>
            <td class="mono"><?= fl_h($l['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?>
          <tr><td colspan="5" style="color:var(--ash);">No log entries.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>

      <?php $pages = (int) ceil($total / $perPage); ?>
      <?php if ($pages > 1): ?>
        <div style="display:flex; gap:6px; margin-top:14px;">
          <?php for ($p = 1; $p <= $pages; $p++): ?>
            <a class="btn btn-ghost btn-sm" href="?page=<?= $p ?>&type=<?= urlencode($typeFilter) ?>" style="<?= $p === $page ? 'border-color:var(--ember); color:var(--amber);' : '' ?>"><?= $p ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
