<?php
/**
 * PDO/SQLite connection + first-run schema bootstrap.
 */

declare(strict_types=1);

function fl_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';
    $dbPath = $config['db_path'];
    $dbDir  = dirname($dbPath);

    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0770, true);
    }

    $isNew = !file_exists($dbPath);

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');

    if ($isNew) {
        $schema = file_get_contents(dirname(__DIR__) . '/schema.sql');
        $pdo->exec($schema);
    }

    return $pdo;
}

/** Idempotently make sure schema exists even on an existing db file (e.g. after upgrade). */
function fl_ensure_schema(): void
{
    $pdo = fl_db();
    $schema = file_get_contents(dirname(__DIR__) . '/schema.sql');
    $pdo->exec($schema);
}
