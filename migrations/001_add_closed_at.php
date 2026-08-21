<?php
/**
 * Migration 001: Add `closed_at` column to the `jobs` table.
 *
 * This migration is idempotent — it checks via PRAGMA table_info whether
 * the column already exists before attempting the ALTER TABLE statement.
 *
 * @param PDO $pdo  Active SQLite connection
 * @return void
 */
function cpvia_migration_001_add_closed_at(PDO $pdo): void
{
    // Check if the column already exists
    $columns = $pdo->query("PRAGMA table_info(jobs)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        if ($col['name'] === 'closed_at') {
            return; // Column already exists — nothing to do
        }
    }

    $pdo->exec("ALTER TABLE jobs ADD COLUMN closed_at DATETIME DEFAULT NULL");
}
