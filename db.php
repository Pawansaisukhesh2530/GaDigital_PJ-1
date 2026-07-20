<?php

if (!function_exists('cpvia_db')) {
    function cpvia_db(string $db_path): PDO
    {
        static $connections = [];

        $key = $db_path;

        if (isset($connections[$key]) && $connections[$key] instanceof PDO) {
            return $connections[$key];
        }

        $pdo = new PDO('sqlite:' . $db_path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');

        $connections[$key] = $pdo;

        return $pdo;
    }
}

if (!function_exists('cpvia_ensure_admins_table')) {
    function cpvia_ensure_admins_table(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }
}
