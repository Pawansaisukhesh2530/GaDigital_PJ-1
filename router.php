<?php
$path = urldecode(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH));

/* ─────────────────────────────────────────────────────────────────────────────
 * Admin Slug Routing
 * ───────────────────────────────────────────────────────────────────────────── */

require_once __DIR__ . '/settings_helpers.php';

// Load slug from config file
$slug = @include __DIR__ . '/admin_slug.php';

// Validate slug; fall back to hardcoded default if invalid or missing
if (!is_string($slug) || !cpvia_validate_admin_slug($slug)) {
    $slug = CPVIA_FALLBACK_ADMIN_SLUG;
    error_log('CPVIA: Invalid admin slug, using fallback.');
}

// Block /admin paths — return a 404 indistinguishable from a normal missing-page response
if (stripos($path, '/admin') === 0) {
    header("HTTP/1.0 404 Not Found");
    echo "404 Not Found";
    return;
}

// Handle admin slug routing
$slugPrefix = '/' . $slug;
if (stripos($path, $slugPrefix) === 0 && (strlen($path) === strlen($slugPrefix) || $path[strlen($slugPrefix)] === '/')) {
    // Extract sub-path after the slug
    $subPath = substr($path, strlen($slugPrefix));
    $subPath = rtrim($subPath, '/');

    // No sub-path or just "/" → admin index
    if ($subPath === '' || $subPath === '/') {
        include __DIR__ . '/admin/index.php';
        return;
    }

    // Remove leading slash from sub-path for file resolution
    $subPath = ltrim($subPath, '/');

    // Strip .php extension if present (admin files use .php in their redirects)
    if (substr($subPath, -4) === '.php') {
        $subPath = substr($subPath, 0, -4);
    }

    // Check if the sub-path maps to a PHP file in admin/ (supports sub-directories)
    $targetFile = __DIR__ . '/admin/' . $subPath . '.php';

    // Security: prevent directory traversal
    $realTarget = realpath(dirname($targetFile));
    $adminDir = realpath(__DIR__ . '/admin');
    if ($realTarget !== false && $adminDir !== false && strpos($realTarget, $adminDir) === 0) {
        if (file_exists($targetFile) && !is_dir($targetFile)) {
            include $targetFile;
            return;
        }
    }

    // Check if sub-path is a static file (e.g., assets/admin.css)
    $staticFile = __DIR__ . '/admin/' . $subPath;
    $realStatic = realpath(dirname($staticFile));
    if ($realStatic !== false && $adminDir !== false && strpos($realStatic, $adminDir) === 0) {
        if (file_exists($staticFile) && !is_dir($staticFile)) {
            // Determine content type and serve the file directly
            $ext = strtolower(pathinfo($staticFile, PATHINFO_EXTENSION));
            $mimeTypes = [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2',
                'ttf' => 'font/ttf',
            ];
            $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';
            header('Content-Type: ' . $contentType);
            readfile($staticFile);
            return;
        }
    }

    // No match within admin — 404
    header("HTTP/1.0 404 Not Found");
    echo "404 Not Found";
    return;
}

/* ─────────────────────────────────────────────────────────────────────────────
 * Existing Public Routing (unchanged)
 * ───────────────────────────────────────────────────────────────────────────── */

if (file_exists(__DIR__ . $path) && !is_dir(__DIR__ . $path)) {
    return false; 
}

$path = rtrim($path, '/');

if ($path === '' || $path === '/') {
    include __DIR__ . '/index.php';
    return;
}

if (file_exists(__DIR__ . $path . '.php')) {
    include __DIR__ . $path . '.php';
    return;
}

header("HTTP/1.0 404 Not Found");
echo "404 Not Found";
?>
