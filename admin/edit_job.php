<?php
/**
 * edit_job.php — Redirects to the single-page editor.
 * The 8-step wizard is no longer used for editing.
 */
require_once __DIR__ . '/auth.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id > 0) {
    header('Location: edit_job_single.php?id=' . $id . '&section=1');
} else {
    header('Location: jobs.php');
}
exit;
