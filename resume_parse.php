<?php
/**
 * resume_parse.php
 * -----------------------------------------------------------------------------
 * Dedicated, same-origin endpoint the Apply wizard calls to auto-fill from a
 * resume. It validates the upload + CSRF, forwards a TEMPORARY copy to the
 * FastAPI Resume Intelligence Service, and returns a safe normalized JSON
 * response. It never stores the resume (the final application submission still
 * owns resume storage) and never leaks AI/service internals to the browser.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

// This endpoint returns JSON only. Never let PHP notices/warnings leak into the
// response body (they would corrupt the JSON the browser parses). Errors are
// logged server-side instead of displayed.
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

// Capture any stray output (stray notices/warnings) so the JSON body stays clean.
ob_start();

require_once __DIR__ . '/apply_helpers.php';
require_once __DIR__ . '/resume_ai_helper.php';

// Scope a longer execution allowance to THIS endpoint only (Parts 2, 8). Local
// Qwen inference can exceed the default 120s web limit; the allowance is the
// cURL budget plus a small margin so cURL returns a controlled API_TIMEOUT
// before PHP would ever raise a fatal max-execution-time error. Other CPVIA
// pages keep their normal execution limits.
@set_time_limit(RESUME_AI_PHP_TIME_LIMIT);

// Wall-clock start for request timing logs.
$cpvia_ai_t0 = microtime(true);

// --- Session (shared with apply.php so the CSRF token matches) --------------
if (session_status() === PHP_SESSION_NONE) {
    $apply_sess_dir = __DIR__ . '/sessions';
    if (is_dir($apply_sess_dir) && is_writable($apply_sess_dir)) {
        session_save_path($apply_sess_dir);
    }
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    @session_start();
}

/** Emit a JSON response and stop. */
function cpvia_ai_json(int $http, array $payload): void
{
    // Discard anything already buffered (e.g. stray notices) so only JSON is sent.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        http_response_code($http);
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/** Friendly, candidate-facing message for an error code. */
function cpvia_ai_message(string $code): string
{
    switch ($code) {
        case 'EMPTY_UPLOAD':
            return 'Please attach a resume before analyzing.';
        case 'UNSUPPORTED_FILE_TYPE':
            return 'Only PDF, DOC or DOCX files can be analyzed.';
        case 'INVALID_FILE':
            return 'This file does not look like a valid resume. Please upload a valid PDF, DOC or DOCX.';
        case 'FILE_TOO_LARGE':
            return 'Your resume is too large to analyze. Please upload a file under 5 MB.';
        case 'EXTRACTION_FAILED':
            return 'We could not read any text from this resume. You can continue filling out your application manually.';
        case 'API_TIMEOUT':
            return 'Resume analysis took too long. You can continue filling out your application manually.';
        case 'AI_SERVICE_UNAVAILABLE':
            return 'Automatic resume reading is currently unavailable. You can continue filling out your application manually.';
        default:
            return 'We could not analyze your resume automatically. You can continue filling out your application manually.';
    }
}

/** Server-side log (no resume content, no personal data). */
function cpvia_ai_log(string $msg): void
{
    error_log('[resume_parse] ' . $msg);
}

// --- Method + CSRF ----------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    cpvia_ai_json(405, ['success' => false, 'error_code' => 'METHOD_NOT_ALLOWED', 'message' => 'Method not allowed.']);
}

$csrf = $_SESSION['apply_csrf'] ?? '';
$sent = (string) ($_POST['csrf_token'] ?? '');
if ($csrf === '' || !hash_equals($csrf, $sent)) {
    cpvia_ai_log('rejected: CSRF mismatch');
    cpvia_ai_json(403, ['success' => false, 'error_code' => 'CSRF_INVALID',
        'message' => 'Your session expired. Please refresh the page and try again.']);
}

// --- Upload presence --------------------------------------------------------
$file = $_FILES['resume'] ?? null;
$uploadErr = $file['error'] ?? UPLOAD_ERR_NO_FILE;
if (!$file || $uploadErr === UPLOAD_ERR_NO_FILE) {
    cpvia_ai_json(400, ['success' => false, 'error_code' => 'EMPTY_UPLOAD', 'message' => cpvia_ai_message('EMPTY_UPLOAD')]);
}
if ($uploadErr !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
    cpvia_ai_json(400, ['success' => false, 'error_code' => 'INVALID_FILE', 'message' => cpvia_ai_message('INVALID_FILE')]);
}

// --- Local validation (fast reject before a ~2 min AI call) -----------------
$size = (int) ($file['size'] ?? 0);
if ($size <= 0) {
    cpvia_ai_json(400, ['success' => false, 'error_code' => 'EMPTY_UPLOAD', 'message' => cpvia_ai_message('EMPTY_UPLOAD')]);
}
if ($size > CPVIA_APPLY_MAX_UPLOAD) {
    cpvia_ai_json(413, ['success' => false, 'error_code' => 'FILE_TOO_LARGE', 'message' => cpvia_ai_message('FILE_TOO_LARGE')]);
}

$original = (string) ($file['name'] ?? 'resume');
$ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
$allowed = cpvia_apply_allowed_docs();
if (!array_key_exists($ext, $allowed)) {
    cpvia_ai_json(415, ['success' => false, 'error_code' => 'UNSUPPORTED_FILE_TYPE', 'message' => cpvia_ai_message('UNSUPPORTED_FILE_TYPE')]);
}

$mime = '';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = (string) finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
}
if ($mime !== '' && !in_array($mime, $allowed[$ext], true)) {
    cpvia_ai_json(415, ['success' => false, 'error_code' => 'INVALID_FILE', 'message' => cpvia_ai_message('INVALID_FILE')]);
}

// --- Forward a temporary copy to FastAPI ------------------------------------
cpvia_ai_log(sprintf('request started (ext=%s, size=%d, budget=%ds)', $ext, $size, RESUME_AI_TIMEOUT));
cpvia_ai_log('forwarding to Resume AI');
$res = cpvia_resume_ai_parse($file['tmp_name'], $original, $mime);
cpvia_ai_log(sprintf('Resume AI response received after %.1fs (status=%d ok=%s code=%s)',
    $res['duration_ms'] / 1000, $res['status'], $res['ok'] ? 'yes' : 'no', $res['error_code']));

if ($res['ok']) {
    cpvia_ai_log(sprintf('request completed after %.1fs', microtime(true) - $cpvia_ai_t0));
    cpvia_ai_json(200, [
        'success' => true,
        'data' => $res['data'],
        'meta' => $res['meta'],
    ]);
}

$code = $res['error_code'] ?: 'PROCESSING_FAILED';
cpvia_ai_log(sprintf('request failed after %.1fs (code=%s)', microtime(true) - $cpvia_ai_t0, $code));
$httpMap = [
    'UNSUPPORTED_FILE_TYPE' => 415, 'INVALID_FILE' => 400, 'FILE_TOO_LARGE' => 413,
    'EMPTY_UPLOAD' => 400, 'EXTRACTION_FAILED' => 422, 'API_TIMEOUT' => 504,
    'AI_SERVICE_UNAVAILABLE' => 503, 'INVALID_RESPONSE' => 502,
];
cpvia_ai_json($httpMap[$code] ?? 502, [
    'success' => false,
    'error_code' => $code,
    'message' => cpvia_ai_message($code),
]);
