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
        $pin = $_POST['pin'] ?? '';

        if ($pin === '') {
            $error = 'PIN wajib diisi.';
        } else {
            // Kita ambil semua user dan cek hash PIN-nya
            // Karena tidak ada username, kita harus iterasi atau memiliki kolom khusus.
            // Untuk efisiensi, kita ambil semua user. Biasanya jumlah admin/staf tidak ribuan.
            $stmt = db()->query('SELECT id, password_hash FROM users');
            $users = $stmt->fetchAll();
            
            $authenticated_user = null;
            foreach ($users as $u) {
                if (password_verify($pin, $u['password_hash'])) {
                    $authenticated_user = $u;
                    break;
                }
            }

            if ($authenticated_user) {
                // Sukses: reset counter, regenerate session ID, set user.
                session_regenerate_id(true);
                $_SESSION['user_id']          = (int)$authenticated_user['id'];
                $_SESSION['login_attempts']   = 0;
                unset($_SESSION['login_lock_until']);

                $upd = db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
                $upd->execute([$authenticated_user['id']]);

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
                $error = 'PIN yang Anda masukkan salah.';
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
    <link rel="stylesheet" href="assets/admin.css?v=<?= @filemtime(__DIR__ . '/assets/admin.css') ?: time() ?>">
    <style>
        .pin-input {
            text-align: center;
            font-size: 2rem !important;
            letter-spacing: 1rem;
            padding: 15px !important;
        }
    </style>
</head>
<body class="auth-body">
    <main class="auth-card">
        <div class="auth-header">
            <img src="../public/logo.png" alt="Logo" class="auth-logo">
            <h1>Login Admin</h1>
            <p class="auth-subtitle">Masukkan PIN Keamanan</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" autocomplete="off">
            <?= csrf_field() ?>

            <label for="pin" style="text-align: center; display: block;">PIN AKSES</label>
            <input type="password" id="pin" name="pin" class="pin-input" 
                   inputmode="numeric" pattern="[0-9]*" maxlength="6" 
                   required autofocus placeholder="••••••">

            <button type="submit" class="btn-primary">Masuk</button>
        </form>

        <p class="auth-footer">
            <a href="../public/index.html">&larr; Kembali ke halaman publik</a>
        </p>
    </main>
</body>
</html>
