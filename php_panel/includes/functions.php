<?php
/**
 * Shared helpers: settings, logging, Ollama proxy call.
 */

declare(strict_types=1);

function fl_setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (fl_db()->query('SELECT key, value FROM settings') as $row) {
            $cache[$row['key']] = $row['value'];
        }
    }
    return $cache[$key] ?? $default;
}

function fl_set_setting(string $key, string $value): void
{
    $stmt = fl_db()->prepare(
        'INSERT INTO settings (key, value) VALUES (:k, :v)
         ON CONFLICT(key) DO UPDATE SET value = excluded.value'
    );
    $stmt->execute(['k' => $key, 'v' => $value]);
}

function fl_client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function fl_log(string $type, string $message, ?int $userId = null): void
{
    $stmt = fl_db()->prepare(
        'INSERT INTO logs (type, user_id, message, ip) VALUES (:t, :u, :m, :ip)'
    );
    $stmt->execute([
        't'  => $type,
        'u'  => $userId,
        'm'  => $message,
        'ip' => fl_client_ip(),
    ]);
}

function fl_csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function fl_csrf_check(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        die('Invalid or expired form token. Go back and try again.');
    }
}

function fl_h(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Stream a chat completion from Ollama straight to the browser as
 * Server-Sent Events, and return the full assistant text once done.
 *
 * @param array<int, array{role:string, content:string}> $messages
 */
function fl_ollama_stream_chat(array $messages): string
{
    $baseUrl = rtrim((string) fl_setting('ollama_url', 'http://127.0.0.1:11434'), '/');
    $model   = fl_setting('model_name', 'firelam-1.5');
    $temp    = (float) fl_setting('temperature', '0.7');
    $maxTok  = (int) fl_setting('max_tokens', '1024');

    $payload = json_encode([
        'model'    => $model,
        'messages' => $messages,
        'stream'   => true,
        'options'  => [
            'temperature' => $temp,
            'num_predict' => $maxTok,
        ],
    ], JSON_UNESCAPED_UNICODE);

    $full = '';

    $ch = curl_init($baseUrl . '/api/chat');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_WRITEFUNCTION  => function ($curlHandle, $chunk) use (&$full) {
            foreach (explode("\n", trim($chunk)) as $line) {
                if ($line === '') {
                    continue;
                }
                $data = json_decode($line, true);
                if (!is_array($data)) {
                    continue;
                }
                if (isset($data['message']['content'])) {
                    $piece = $data['message']['content'];
                    $full .= $piece;
                    echo 'data: ' . json_encode(['delta' => $piece]) . "\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
                if (!empty($data['error'])) {
                    echo 'data: ' . json_encode(['error' => $data['error']]) . "\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            }
            return strlen($chunk);
        },
    ]);

    $ok = curl_exec($ch);
    if ($ok === false) {
        $err = curl_error($ch);
        echo 'data: ' . json_encode(['error' => "Could not reach the model server: $err"]) . "\n\n";
        flush();
        fl_log('error', "Ollama connection failed: $err");
    }
    curl_close($ch);

    echo "data: [DONE]\n\n";
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();

    return $full;
}
