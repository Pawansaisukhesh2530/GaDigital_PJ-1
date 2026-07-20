<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';

$db_file = __DIR__ . '/cpvia_database.sqlite';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';
    $allowed_statuses = ['New', 'In Review', 'Shortlisted', 'Rejected', 'Hired'];
    $csrf_ok = cpvia_csrf_check($_POST['csrf_token'] ?? null);

    if ($id <= 0 || !in_array($status, $allowed_statuses, true) || !$csrf_ok) {
        header('Location: applications.php');
        exit;
    }

    try {
        $pdo = cpvia_db($db_file);

        $stmt = $pdo->prepare("
            UPDATE applications
            SET status = ?
            WHERE id = ?
        ");

        $stmt->execute([$status, $id]);

    } catch(Exception $e){
        die($e->getMessage());
    }

}

header("Location: applications.php");
exit;