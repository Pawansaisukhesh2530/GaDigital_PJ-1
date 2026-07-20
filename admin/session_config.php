<?php
if (session_status() === PHP_SESSION_NONE) {
    $sessions_dir = __DIR__ . '/sessions';

    if (!file_exists($sessions_dir)) {
        @mkdir($sessions_dir, 0700, true);
    }

    if (is_dir($sessions_dir) && is_writable($sessions_dir)) {
        session_save_path($sessions_dir);
    }
}
