<?php
/**
 * Job Status Actions: Close (Archive) and Reopen
 *
 * Handles POST requests to change a job posting's lifecycle status.
 * - close:  Sets status to 'Closed' and records the closure timestamp.
 * - reopen: Sets status back to 'Active' and clears the closure timestamp.
 *
 * Application records are NEVER modified by these actions.
 *
 * Requirements: 6.1, 6.2, 6.3, 6.6
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';

$db_file = __DIR__ . '/cpvia_database.sqlite';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: jobs.php');
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$csrf_ok = cpvia_csrf_check($_POST['csrf_token'] ?? null);

// Validate required fields and CSRF token
if ($id <= 0 || !$csrf_ok) {
    header('Location: jobs.php');
    exit;
}

// Only allow known actions
if (!in_array($action, ['close', 'reopen'], true)) {
    header('Location: jobs.php');
    exit;
}

try {
    $pdo = cpvia_db($db_file);

    if ($action === 'close') {
        // Archive the job: set status to Closed and record timestamp
        // Application records are preserved — no cascade operations
        $stmt = $pdo->prepare("
            UPDATE jobs
            SET status = 'Closed',
                closed_at = datetime('now'),
                updated_at = datetime('now')
            WHERE id = ?
        ");
        $stmt->execute([$id]);

        header('Location: jobs.php?success=closed');
        exit;
    } elseif ($action === 'reopen') {
        // Reopen the job: restore Active status and clear closure timestamp
        $stmt = $pdo->prepare("
            UPDATE jobs
            SET status = 'Active',
                closed_at = NULL,
                updated_at = datetime('now')
            WHERE id = ?
        ");
        $stmt->execute([$id]);

        header('Location: jobs.php?success=reopened');
        exit;
    }
} catch (Exception $e) {
    // Do not expose raw database errors to the browser
    header('Location: jobs.php?error=status');
    exit;
}

header('Location: jobs.php');
exit;
