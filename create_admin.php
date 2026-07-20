<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This script can only be run from the command line.\n";
    exit(1);
}

require_once __DIR__ . '/db.php';

function prompt(string $label, bool $hidden = false): string
{
    echo $label;
    if ($hidden && stripos(PHP_OS, 'WIN') !== 0) {
        system('stty -echo');
        $value = trim((string) fgets(STDIN));
        system('stty echo');
        echo "\n";
        return $value;
    }
    return trim((string) fgets(STDIN));
}

$argv_name = $argv[1] ?? null;
$argv_email = $argv[2] ?? null;
$argv_password = $argv[3] ?? null;

$name = $argv_name !== null ? trim($argv_name) : prompt('Admin full name: ');
$email = $argv_email !== null ? trim($argv_email) : prompt('Admin email: ');
$password = $argv_password !== null ? $argv_password : prompt('Admin password (min 8 characters): ', true);

if ($name === '' || $email === '' || $password === '') {
    fwrite(STDERR, "Error: name, email, and password are all required.\n");
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Error: '$email' is not a valid email address.\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "Error: password must be at least 8 characters long.\n");
    exit(1);
}

$db_file = __DIR__ . '/admin/cpvia_database.sqlite';
$admin_dir = dirname($db_file);
if (!file_exists($admin_dir)) {
    mkdir($admin_dir, 0777, true);
}

try {
    $pdo = cpvia_db($db_file);
    cpvia_ensure_admins_table($pdo);

    // Explicit duplicate check (in addition to the UNIQUE constraint) so
    // we can give a clear error message instead of a raw DB exception.
    $check = $pdo->prepare('SELECT COUNT(*) FROM admins WHERE email = ?');
    $check->execute([$email]);
    if ((int) $check->fetchColumn() > 0) {
        fwrite(STDERR, "Error: an admin with the email '$email' already exists.\n");
        exit(1);
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $insert = $pdo->prepare('INSERT INTO admins (name, email, password_hash, status) VALUES (?, ?, ?, ?)');
    $insert->execute([$name, $email, $password_hash, 'active']);

    echo "Admin account created successfully.\n";
    echo "  Name:  $name\n";
    echo "  Email: $email\n";
    echo "You can now log in at /admin/login.php\n";
} catch (Exception $e) {
    fwrite(STDERR, "Error: could not create admin account (" . $e->getMessage() . ")\n");
    exit(1);
}
