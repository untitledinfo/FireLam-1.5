<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = fl_current_user();
if (!$user) {
    http_response_code(401);
    exit;
}

// CSRF check for this fetch()-based POST (token sent as a header, not a form field).
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string) $csrfHeader)) {
    http_response_code(403);
    exit('Invalid form token.');
}

$body = json_decode(file_get_contents('php://input'), true);
$convId = isset($body['conversation_id']) ? (int) $body['conversation_id'] : 0;
$text = trim((string) ($body['message'] ?? ''));

if ($convId <= 0 || $text === '') {
    http_response_code(400);
    exit('Missing conversation_id or message.');
}

$db = fl_db();
$stmt = $db->prepare('SELECT * FROM conversations WHERE id = :id AND user_id = :u');
$stmt->execute(['id' => $convId, 'u' => $user['id']]);
$conv = $stmt->fetch();
if (!$conv) {
    http_response_code(404);
    exit('Conversation not found.');
}

// Save the user's message.
$db->prepare('INSERT INTO messages (conversation_id, role, content) VALUES (:c, \'user\', :m)')
   ->execute(['c' => $convId, 'm' => $text]);

// Auto-title a fresh conversation from the first message.
if ($conv['title'] === 'New chat') {
    $title = function_exists('mb_substr') ? mb_substr($text, 0, 60) : substr($text, 0, 60);
    $db->prepare('UPDATE conversations SET title = :t WHERE id = :id')
       ->execute(['t' => $title, 'id' => $convId]);
}

// Build the message list for the model: system prompt + full history.
$history = $db->prepare('SELECT role, content FROM messages WHERE conversation_id = :c ORDER BY id ASC');
$history->execute(['c' => $convId]);
$rows = $history->fetchAll();

$modelMessages = [];
$systemPrompt = fl_setting('system_prompt', '');
if ($systemPrompt !== '') {
    $modelMessages[] = ['role' => 'system', 'content' => $systemPrompt];
}
foreach ($rows as $r) {
    $modelMessages[] = ['role' => $r['role'], 'content' => $r['content']];
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // disable nginx proxy buffering for this response
if (ob_get_level() === 0) {
    ob_start();
}

$assistantText = fl_ollama_stream_chat($modelMessages);

if (trim($assistantText) !== '') {
    $db->prepare('INSERT INTO messages (conversation_id, role, content) VALUES (:c, \'assistant\', :m)')
       ->execute(['c' => $convId, 'm' => $assistantText]);
}
