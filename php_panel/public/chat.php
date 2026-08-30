<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$user = fl_require_login();

// Pick or create the active conversation.
$convId = isset($_GET['c']) ? (int) $_GET['c'] : 0;
$db = fl_db();

if ($convId) {
    $stmt = $db->prepare('SELECT * FROM conversations WHERE id = :id AND user_id = :u');
    $stmt->execute(['id' => $convId, 'u' => $user['id']]);
    $conv = $stmt->fetch();
    if (!$conv) {
        $convId = 0;
    }
}

if (!$convId) {
    $ins = $db->prepare('INSERT INTO conversations (user_id, title) VALUES (:u, :t)');
    $ins->execute(['u' => $user['id'], 't' => 'New chat']);
    $convId = (int) $db->lastInsertId();
}

// Recent conversations for the picker.
$recent = $db->prepare('SELECT id, title, created_at FROM conversations WHERE user_id = :u ORDER BY id DESC LIMIT 20');
$recent->execute(['u' => $user['id']]);
$conversations = $recent->fetchAll();

// Existing messages for the active conversation.
$msgStmt = $db->prepare('SELECT role, content FROM messages WHERE conversation_id = :c ORDER BY id ASC');
$msgStmt->execute(['c' => $convId]);
$messages = $msgStmt->fetchAll();

$pageTitle = 'Chat';
$active = 'chat';
require __DIR__ . '/partials_head.php';
?>
<div class="app-shell">
  <?php require __DIR__ . '/partials_sidebar.php'; ?>

  <div class="main" style="padding:0; display:flex;">
    <div style="width:200px; border-right:1px solid var(--ash-dim); padding:18px 12px; overflow-y:auto;">
      <a href="/chat.php" class="btn btn-ghost btn-sm" style="width:100%; justify-content:center; margin-bottom:12px;">+ New chat</a>
      <?php foreach ($conversations as $c): ?>
        <a href="/chat.php?c=<?= (int) $c['id'] ?>"
           class="nav-link<?= $c['id'] == $convId ? ' active' : '' ?>"
           style="display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
          <?= fl_h($c['title']) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="chat-shell" style="flex:1;">
      <div class="chat-log" id="chatLog">
        <?php foreach ($messages as $m): ?>
          <div class="msg msg-<?= fl_h($m['role']) ?>">
            <div class="msg-role"><?= $m['role'] === 'user' ? fl_h($user['username']) : fl_h(fl_setting('site_name', 'FireLam')) ?></div>
            <div class="bubble"><?= fl_h($m['content']) ?></div>
          </div>
        <?php endforeach; ?>
        <?php if (!$messages): ?>
          <div style="margin:auto; text-align:center; color:var(--ash);">
            <span class="ember-dot"></span><br><br>Say something to start the conversation.
          </div>
        <?php endif; ?>
      </div>

      <form class="chat-input-bar" id="chatForm">
        <input type="hidden" id="convId" value="<?= (int) $convId ?>">
        <input type="hidden" id="csrf" value="<?= fl_h(fl_csrf_token()) ?>">
        <textarea id="chatInput" placeholder="Message FireLam…" required></textarea>
        <button class="btn" type="submit" id="sendBtn">Send</button>
      </form>
    </div>
  </div>
</div>
<script src="/assets/js/chat.js"></script>
</body>
</html>
