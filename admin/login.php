<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/csrf.php';

// Kalau sudah login, langsung ke dashboard.
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

const MAX_ATTEMPTS = 5;
const LOCK_SECONDS = 60;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf_token'] ?? null);

    // Rate limit check
    $attempts  = $_SESSION['login_attempts']  ?? 0;
    $lockUntil = $_SESSION['login_lock_until'] ?? 0;

    if ($lockUntil > time()) {
        $error = 'Terlalu banyak percobaan. Coba lagi dalam ' . ($lockUntil - time()) . ' detik.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Username dan password wajib diisi.';
        } else {
            $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Sukses: reset counter, regenerate session ID, set user.
                session_regenerate_id(true);
                $_SESSION['user_id']          = (int)$user['id'];
                $_SESSION['login_attempts']   = 0;
                unset($_SESSION['login_lock_until']);

                $upd = db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
                $upd->execute([$user['id']]);

                header('Location: dashboard.php');
                exit;
            }

            // Gagal: naikkan counter.
            $attempts++;
            $_SESSION['login_attempts'] = $attempts;
            if ($attempts >= MAX_ATTEMPTS) {
                $_SESSION['login_lock_until'] = time() + LOCK_SECONDS;
                $_SESSION['login_attempts']   = 0;
                $error = 'Terlalu banyak percobaan. Coba lagi dalam ' . LOCK_SECONDS . ' detik.';
            } else {
                $error = 'Username atau password salah.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - SPP DISKOMINFO</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="auth-body">
    <main class="auth-card">
        <div class="auth-header">
            <img src="../public/logo.png" alt="Logo" class="auth-logo">
            <h1>Login Admin</h1>
            <p class="auth-subtitle">SPP DISKOMINFO</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" autocomplete="off" novalidate>
            <?= csrf_field() ?>

            <label for="username">Username</label>
            <input type="text" id="username" name="username" required autofocus
                   value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <button type="submit" class="btn-primary">Masuk</button>
        </form>

        <p class="auth-footer">
            <a href="../public/index.html">&larr; Kembali ke halaman publik</a>
        </p>
    </main>
</body>
</html>
