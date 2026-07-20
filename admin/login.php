<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/session_config.php';

if (session_status() === PHP_SESSION_NONE) {
    $cookie_params = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookie_params['path'] ?: '/',
        'domain' => $cookie_params['domain'] ?? '',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$db_file = __DIR__ . '/cpvia_database.sqlite';
$error = '';
$email_value = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_token = $_POST['csrf_token'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $email_value = $email;

    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted_token)) {
        $error = 'Your session has expired. Please try again.';
    } elseif ($email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email or password.';
    } else {
        try {
            $pdo = cpvia_db($db_file);
            cpvia_ensure_admins_table($pdo);

            $stmt = $pdo->prepare('SELECT id, name, email, password_hash, status FROM admins WHERE email = ?');
            $stmt->execute([$email]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                $admin &&
                $admin['status'] === 'active' &&
                password_verify($password, $admin['password_hash'])
            ) {
                session_regenerate_id(true);

                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header('Location: index.php');
                exit;
            }

            $error = 'Invalid email or password.';
        } catch (Exception $e) {
            $error = 'Something went wrong. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPVIA Admin - Login</title>
    <link rel="icon" type="image/webp" href="../assets/images/cpvia-fav-icon.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin.css">
    <style>
        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at 15% 20%, #F4F2FF 0%, #FAFAFF 55%, #FFF6F0 100%);
            padding: 24px;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border-radius: 20px;
            border: 1px solid var(--border-soft);
            box-shadow: 0 20px 60px rgba(61, 26, 138, 0.12);
            padding: 2.75rem;
        }

        .login-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.75rem;
        }

        .login-brand img {
            height: 34px;
        }

        .login-card h1 {
            font-size: 1.5rem;
            color: var(--primary-blue);
            font-weight: 800;
            text-align: center;
            margin: 0 0 0.5rem 0;
        }

        .login-card .login-sub {
            text-align: center;
            color: var(--text-light);
            font-size: 0.92rem;
            margin: 0 0 2rem 0;
        }

        .login-card .form-group {
            margin-bottom: 1.4rem;
        }

        .login-card .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 700;
            color: var(--primary-blue);
            font-size: 0.85rem;
        }

        .login-card .form-group input {
            width: 100%;
            padding: 0.9rem 1rem;
            border: 1.5px solid var(--border-soft);
            border-radius: 12px;
            font-family: inherit;
            font-size: 0.95rem;
            background: #fdfdfd;
            transition: border-color 0.3s, box-shadow 0.3s, background 0.3s;
        }

        .login-card .form-group input:focus {
            outline: none;
            border-color: var(--primary-orange);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(255, 85, 0, 0.08);
        }

        .btn-login {
            width: 100%;
            background: var(--primary-orange);
            color: #fff;
            border: none;
            border-radius: 14px;
            padding: 1rem;
            font-weight: 700;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            cursor: pointer;
            transition: background 0.3s, transform 0.2s;
            margin-top: 0.5rem;
        }

        .btn-login:hover {
            background: var(--primary-orange-dark);
            transform: translateY(-1px);
        }

        .login-back-link {
            display: block;
            text-align: center;
            margin-top: 1.75rem;
            color: var(--text-light);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .login-back-link:hover {
            color: var(--primary-blue);
        }

        .login-error {
            background: #FDE8E8;
            color: #B91C1C;
            border: 1px solid #F5C2C2;
            border-radius: 12px;
            padding: 0.9rem 1.1rem;
            font-size: 0.88rem;
            margin-bottom: 1.4rem;
        }

        @media (max-width: 480px) {
            .login-card { padding: 2rem; border-radius: 16px; }
        }
    </style>
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <div class="login-brand">
                <img src="../assets/images/header-logo.png" alt="CPVIA Logo">
            </div>

            <h1>CPVIA Admin</h1>
            <p class="login-sub">Sign in to access the administration portal.</p>

            <?php if ($error): ?>
                <div class="login-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email_value); ?>" placeholder="admin@cpvia.com" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="btn-login">Login</button>
            </form>

            <a href="../" class="login-back-link">&larr; Back to Website</a>
        </div>
    </div>
</body>
</html>
