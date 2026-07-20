<?php
$path = urldecode(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH));

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
