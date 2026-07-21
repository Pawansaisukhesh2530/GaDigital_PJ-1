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
    $pdo->exec('PRAGMA foreign_keys = ON');

    $stmt = $pdo->prepare("SELECT resume_path FROM applications WHERE id = ?");
    $stmt->execute([$id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$application) {
        header('Location: applications.php?deleted=notfound');
        exit;
    }

    // Collect stored document filenames before delete (cover letter, resume,
    // etc.) so we can also remove them from disk after the DB commit.
    $doc_files = [];
    if (!empty($application['resume_path'])) {
        $doc_files[] = $application['resume_path'];
    }
    try {
        $docStmt = $pdo->prepare("SELECT stored_filename FROM application_documents WHERE application_id = ?");
        $docStmt->execute([$id]);
        foreach ($docStmt->fetchAll(PDO::FETCH_COLUMN) as $fname) {
            if (!empty($fname)) {
                $doc_files[] = $fname;
            }
        }
    } catch (Throwable $e) {
        // application_documents may be empty for legacy rows; not fatal.
    }
    $doc_files = array_unique($doc_files);

    // Delete the application and all dependent rows (professional details,
    // education, documents metadata, skills) atomically. FK ON DELETE CASCADE
    // handles the child tables now that foreign_keys is enabled for this call.
    $pdo->beginTransaction();
    $delete_stmt = $pdo->prepare("DELETE FROM applications WHERE id = ?");
    $delete_stmt->execute([$id]);
    $pdo->commit();

    foreach ($doc_files as $fname) {
        $file_path = __DIR__ . '/../uploads/resumes/' . basename($fname);
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
    }

    header('Location: applications.php?deleted=success');
    exit;
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: applications.php?deleted=notfound');
    exit;
}
