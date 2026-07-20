<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';

$db_file = __DIR__ . '/cpvia_database.sqlite';

$id = 0;
if (isset($_POST['id'])) {
    $id = (int) $_POST['id'];
} elseif (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
}

$csrf_ok = cpvia_csrf_check($_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? null));

if ($id <= 0 || !$csrf_ok) {
    header('Location: applications.php?deleted=notfound');
    exit;
}

try {
    $pdo = cpvia_db($db_file);

    $stmt = $pdo->prepare("SELECT resume_path FROM applications WHERE id = ?");
    $stmt->execute([$id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$application) {
        header('Location: applications.php?deleted=notfound');
        exit;
    }

    if (!empty($application['resume_path'])) {
        $resume_file = __DIR__ . '/../uploads/resumes/' . basename($application['resume_path']);
        if (file_exists($resume_file)) {
            unlink($resume_file);
        }
    }

    $delete_stmt = $pdo->prepare("DELETE FROM applications WHERE id = ?");
    $delete_stmt->execute([$id]);

    header('Location: applications.php?deleted=success');
    exit;
} catch (Exception $e) {
    header('Location: applications.php?deleted=notfound');
    exit;
}
