<?php
/**
 * FireLam panel configuration.
 * Values are pulled from environment variables (set by systemd/php-fpm pool
 * or a .env loaded by env.php) with sane local defaults as fallback.
 */

declare(strict_types=1);

if (!function_exists('fl_env')) {
    function fl_env(string $key, ?string $default = null): ?string
    {
        $val = getenv($key);
        return ($val === false || $val === '') ? $default : $val;
    }
}

// Load a .env file if present (simple KEY=VALUE parser, no external deps).
// Guarded so this file can safely be require()'d more than once per request.
if (!defined('FL_ENV_LOADED')) {
    define('FL_ENV_LOADED', true);
    $envFile = dirname(__DIR__) . '/.env';
    if (is_readable($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v, " \t\n\r\0\x0B\"'");
            if (getenv($k) === false) {
                putenv("$k=$v");
            }
        }
    }
}

return [
    'app_env'      => fl_env('FIRELAM_ENV', 'production'),
    'app_debug'    => fl_env('FIRELAM_DEBUG', '0') === '1',
    'db_path'      => fl_env('FIRELAM_DB_PATH', dirname(__DIR__) . '/storage/firelam.db'),
    'session_name' => 'firelam_session',
    // Ollama connection defaults; overridable per-install and again in Settings UI.
    'ollama_url'   => fl_env('FIRELAM_OLLAMA_URL', 'http://127.0.0.1:11434'),
    'model_name'   => fl_env('FIRELAM_MODEL', 'firelam-1.5'),
];
