<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../includes/csrf.php';

$user = current_user();
if (!$user) {
    // Edge case: session valid tapi user sudah dihapus dari DB.
    header('Location: logout.php');
    exit;
}

// Hitung total laporan untuk ditampilkan di card
$totalLaporan = 0;
try {
    $totalLaporan = (int) db()->query('SELECT COUNT(*) FROM laporan')->fetchColumn();
} catch (Throwable $e) {
    // Tabel belum di-migrate, biarkan 0.
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - SPP DISKOMINFO</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
    <nav class="admin-navbar">
        <div class="brand">
            <img src="../public/logo.png" alt="Logo">
            <span>SPP DISKOMINFO — Admin</span>
        </div>
        <div class="user-area">
            <span>Halo, <strong><?= htmlspecialchars($user['nama_lengkap'], ENT_QUOTES, 'UTF-8') ?></strong></span>
            <form method="POST" action="logout.php" style="margin:0;">
                <?= csrf_field() ?>
                <button type="submit" class="btn-logout">Logout</button>
            </form>
        </div>
    </nav>

    <main class="dashboard-container">
        <section class="dashboard-greeting">
            <h2>Selamat Datang</h2>
            <p>
                Login terakhir:
                <?= $user['last_login'] ? htmlspecialchars($user['last_login'], ENT_QUOTES, 'UTF-8') : 'Pertama kali login' ?>
            </p>
        </section>

        <section class="card-grid">
            <article class="card">
                <h3>Laporan Tersimpan</h3>
                <p>Monitoring <strong><?= $totalLaporan ?></strong> laporan yang sudah di-export ke PDF.</p>
                <a href="laporan.php" class="badge-soon" style="background:#e3f2fd;color:#003366;text-decoration:none;">Buka Monitoring &rarr;</a>
            </article>

            <article class="card">
                <h3>Kelola Admin</h3>
                <p>Tambah, edit, atau nonaktifkan akun admin lain.</p>
                <span class="badge-soon">Segera hadir</span>
            </article>

            <article class="card">
                <h3>Buat Laporan Baru</h3>
                <p>Buka generator laporan SPP untuk membuat dokumen baru.</p>
                <a href="../public/index.html" class="badge-soon" style="background:#e3f2fd;color:#003366;text-decoration:none;">Buka Generator &rarr;</a>
            </article>
        </section>
    </main>
</body>
</html>
