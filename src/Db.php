<?php
declare(strict_types=1);

namespace CodeManager;

use PDO;

/** The module keeps its data in its own SQLite file (storage/code-manager.sqlite) — it never touches your application's database. */
final class Db
{
    private static ?PDO $pdo = null;

    public static function ensureStorage(): void
    {
        $d = Config::storage();
        if (!is_dir($d)) @mkdir($d, 0775, true);
        if (!is_file("$d/.htaccess")) file_put_contents("$d/.htaccess", "# never web-accessible\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
        if (!is_file("$d/index.html")) file_put_contents("$d/index.html", '');
    }

    public static function pdo(): PDO
    {
        if (self::$pdo) return self::$pdo;
        self::ensureStorage();
        $pdo = new PDO('sqlite:' . Config::storage() . '/code-manager.sqlite', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA journal_mode = WAL');
        if ((int) $pdo->query('PRAGMA user_version')->fetchColumn() < 1) {
            $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS cm_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    kind TEXT NOT NULL, slug TEXT NOT NULL, name TEXT NOT NULL DEFAULT '', description TEXT NOT NULL DEFAULT '',
    target TEXT NOT NULL DEFAULT '*', position TEXT NOT NULL DEFAULT '', sort INTEGER NOT NULL DEFAULT 0, active INTEGER NOT NULL DEFAULT 1,
    html TEXT NOT NULL DEFAULT '', css TEXT NOT NULL DEFAULT '', js TEXT NOT NULL DEFAULT '',
    published_version INTEGER NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL DEFAULT 0, updated_at INTEGER NOT NULL DEFAULT 0, published_at INTEGER NOT NULL DEFAULT 0,
    UNIQUE(kind, slug)
);
CREATE TABLE IF NOT EXISTS cm_versions (
    id INTEGER PRIMARY KEY AUTOINCREMENT, item_id INTEGER NOT NULL, version INTEGER NOT NULL,
    html TEXT NOT NULL DEFAULT '', css TEXT NOT NULL DEFAULT '', js TEXT NOT NULL DEFAULT '',
    target TEXT NOT NULL DEFAULT '*', position TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'archived', note TEXT NOT NULL DEFAULT '',
    created_at INTEGER NOT NULL DEFAULT 0, published_at INTEGER NOT NULL DEFAULT 0, UNIQUE(item_id, version)
);
CREATE INDEX IF NOT EXISTS idx_cmv_item ON cm_versions(item_id, version);
CREATE TABLE IF NOT EXISTS cm_snippets (
    id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL DEFAULT '', category TEXT NOT NULL DEFAULT '', description TEXT NOT NULL DEFAULT '',
    html TEXT NOT NULL DEFAULT '', css TEXT NOT NULL DEFAULT '', js TEXT NOT NULL DEFAULT '', created_at INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS cm_meta (key TEXT PRIMARY KEY, value TEXT NOT NULL DEFAULT '');
SQL);
            $pdo->exec('PRAGMA user_version = 1');
        }
        return self::$pdo = $pdo;
    }
}
