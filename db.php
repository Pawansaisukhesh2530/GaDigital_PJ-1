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

/**
 * Run all idempotent database migrations.
 *
 * Each migration file in the migrations/ directory defines a single function
 * that checks whether its change has already been applied before executing.
 * This function can be called safely on every application startup.
 *
 * @param PDO $pdo  Active SQLite connection
 * @return void
 */
if (!function_exists('cpvia_run_migrations')) {
    function cpvia_run_migrations(PDO $pdo): void
    {
        $migrationsDir = __DIR__ . '/migrations';

        if (!is_dir($migrationsDir)) {
            return;
        }

        // 001_add_closed_at.php
        $file = $migrationsDir . '/001_add_closed_at.php';
        if (file_exists($file)) {
            require_once $file;
            cpvia_migration_001_add_closed_at($pdo);
        }
    }
}
