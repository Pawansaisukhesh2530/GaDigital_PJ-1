<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';

$db_file = __DIR__ . '/cpvia_database.sqlite';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $csrf_ok = cpvia_csrf_check($_POST['csrf_token'] ?? null);

    if ($id > 0 && $csrf_ok) {
        try {
            $pdo = cpvia_db($db_file);

            $stmt = $pdo->prepare("DELETE FROM jobs WHERE id = ?");
            $stmt->execute([$id]);

            header('Location: jobs.php?success=deleted');
            exit;
        } catch (Exception $e) {
            die('Database error: ' . htmlspecialchars($e->getMessage()));
        }
    }
}

header('Location: jobs.php');
exit;
