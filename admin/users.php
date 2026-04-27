<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../includes/csrf.php';

$user = current_user();
if (!$user) {
    header('Location: logout.php');
    exit;
}

$success_msg = '';
$error_msg   = '';

// --- PROSES ACTION (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error_msg = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $nama      = trim($_POST['nama'] ?? '');
            $nip       = trim($_POST['nip'] ?? '');
            $pangkat   = trim($_POST['pangkat'] ?? '');
            $pin       = $_POST['pin'] ?? '';

            if (!$nama || !$pin) {
                $error_msg = 'Nama dan PIN wajib diisi.';
            } else {
                try {
                    db()->beginTransaction();
                    
                    // Gunakan NIP sebagai username internal agar unik, atau random jika NIP kosong
                    $internal_username = $nip ? $nip : 'user_' . bin2hex(random_bytes(4));

                    $stmt = db()->prepare("INSERT INTO users (username, password_hash, nama_lengkap) VALUES (?, ?, ?)");
                    $stmt->execute([
                        $internal_username,
                        password_hash($pin, PASSWORD_DEFAULT),
                        $nama
                    ]);
                    $userId = db()->lastInsertId();

                    $stmt = db()->prepare("INSERT INTO stafs (user_id, nama, nip, pangkat_golongan) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$userId, $nama, $nip, $pangkat]);

                    db()->commit();
                    $success_msg = 'Staf berhasil ditambahkan dengan PIN baru.';
                } catch (Throwable $e) {
                    db()->rollBack();
                    $error_msg = 'Error: ' . $e->getMessage();
                    if (strpos($error_msg, 'Duplicate entry') !== false) {
                        $error_msg = 'NIP sudah terdaftar.';
                    }
                }
            }
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            try {
                $staf = db()->prepare("SELECT user_id FROM stafs WHERE id = ?");
                $staf->execute([$id]);
                $s = $staf->fetch();
                if ($s && $s['user_id']) {
                    db()->prepare("DELETE FROM users WHERE id = ?")->execute([$s['user_id']]);
                } else {
                    db()->prepare("DELETE FROM stafs WHERE id = ?")->execute([$id]);
                }
                $success_msg = 'Data berhasil dihapus.';
            } catch (Throwable $e) {
                $error_msg = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// --- AMBIL DATA STAF ---
$stafs = db()->query("
    SELECT s.*, u.last_login 
    FROM stafs s 
    LEFT JOIN users u ON s.user_id = u.id 
    ORDER BY s.id DESC
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User - SPP DISKOMINFO</title>
    <link rel="stylesheet" href="assets/admin.css?v=<?= @filemtime(__DIR__ . '/assets/admin.css') ?: time() ?>">
</head>
<body>
    <div class="admin-layout">
        <div class="sidebar-overlay" id="sidebar-overlay"></div>

        <!-- SIDEBAR -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="../public/logo.png" alt="Logo">
                <span>DISKOMINFO</span>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                    <span>Monitoring</span>
                </a>
                <a href="../public/index.html" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                    <span>Buat Laporan</span>
                </a>
                <a href="users.php" class="nav-item active">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span>Kelola User</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <form method="POST" action="logout.php" style="margin:0;">
                    <?= csrf_field() ?>
                    <button type="submit" class="nav-item nav-item-btn">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="main-content" id="main-content">
            <header class="top-bar">
                <button class="toggle-btn" id="toggle-btn" aria-label="Toggle sidebar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <div class="top-bar-title">Kelola User & Staf</div>
                <div class="user-profile">
                    <?php
                        $namaUser = $user['nama_lengkap'] ?? 'Admin';
                        $initial  = strtoupper(mb_substr($namaUser, 0, 1, 'UTF-8'));
                    ?>
                    <div class="user-avatar" aria-hidden="true"><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="user-meta">
                        <span class="user-hello">Halo,</span>
                        <strong class="user-name"><?= htmlspecialchars($namaUser, ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                </div>
            </header>

            <div class="dashboard-container">
                <section class="dashboard-greeting">
                    <h2>Manajemen User</h2>
                    <p>Atur staf dan PIN keamanan untuk akses sistem.</p>
                </section>

                <?php if ($success_msg): ?>
                    <div class="alert" style="background:#d1fae5; color:#065f46; border-left:4px solid #10b981; padding:15px; border-radius:6px; margin-bottom:24px;">
                        <?= htmlspecialchars($success_msg) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                    <div class="alert" style="background:#fee2e2; color:#991b1b; border-left:4px solid #ef4444; padding:15px; border-radius:6px; margin-bottom:24px;">
                        <?= htmlspecialchars($error_msg) ?>
                    </div>
                <?php endif; ?>

                <section class="card" style="margin-bottom:32px; border-top:4px solid #003366;">
                    <h3 style="margin-bottom:20px; color:#003366;">Tambah User Baru</h3>
                    <form method="POST" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:20px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create">
                        
                        <div>
                            <label style="display:block; font-size:0.8rem; font-weight:600; color:#4b5563; margin-bottom:6px;">Nama Lengkap</label>
                            <input type="text" name="nama" required placeholder="Contoh: Budi Santoso" style="width:100%; padding:10px 14px; border:1px solid #d1d5db; border-radius:6px; font-size:0.9rem;">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.8rem; font-weight:600; color:#4b5563; margin-bottom:6px;">NIP</label>
                            <input type="text" name="nip" placeholder="Masukkan NIP (Opsional)" style="width:100%; padding:10px 14px; border:1px solid #d1d5db; border-radius:6px; font-size:0.9rem;">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.8rem; font-weight:600; color:#4b5563; margin-bottom:6px;">Pangkat / Golongan</label>
                            <input type="text" name="pangkat" placeholder="Contoh: Penata / IIIc" style="width:100%; padding:10px 14px; border:1px solid #d1d5db; border-radius:6px; font-size:0.9rem;">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.8rem; font-weight:600; color:#4b5563; margin-bottom:6px;">PIN Keamanan (6 Angka)</label>
                            <input type="password" name="pin" required maxlength="6" pattern="[0-9]*" inputmode="numeric" placeholder="Contoh: 123456" style="width:100%; padding:10px 14px; border:1px solid #d1d5db; border-radius:6px; font-size:0.9rem;">
                        </div>
                        <div style="display:flex; align-items:flex-end;">
                            <button type="submit" class="btn-primary" style="margin:0; height:42px; border-radius:6px;">Simpan User</button>
                        </div>
                    </form>
                </section>

                <div class="table-wrapper">
                    <table class="laporan-table">
                        <thead>
                            <tr>
                                <th>Nama / NIP</th>
                                <th>Pangkat/Gol</th>
                                <th>Login Terakhir</th>
                                <th style="text-align:right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$stafs): ?>
                                <tr><td colspan="4" class="empty-row">Belum ada data staf.</td></tr>
                            <?php else: ?>
                                <?php foreach ($stafs as $s): ?>
                                    <tr>
                                        <td>
                                            <div class="cell-title"><?= htmlspecialchars($s['nama'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="cell-sub">NIP: <?= htmlspecialchars($s['nip'] ?: '-', ENT_QUOTES, 'UTF-8') ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($s['pangkat_golongan'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span style="font-size:0.8rem; color:#6b7280;"><?= $s['last_login'] ?: 'Belum pernah' ?></span></td>
                                        <td style="text-align:right;">
                                            <form method="POST" onsubmit="return confirm('Hapus user ini?');" style="margin:0;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                                <button type="submit" class="btn-action" style="background:#fee2e2; color:#b91c1c; border:none; cursor:pointer; padding:6px 12px; border-radius:4px;">Hapus</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        const toggleBtn = document.getElementById('toggle-btn');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const overlay = document.getElementById('sidebar-overlay');
        const isMobile = () => window.matchMedia('(max-width: 768px)').matches;

        toggleBtn.addEventListener('click', () => {
            if (isMobile()) {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            }
        });

        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        });

        if (!isMobile() && localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        }

        window.addEventListener('resize', () => {
            if (!isMobile()) {
                sidebar.classList.remove('open');
                overlay.classList.remove('show');
            }
        });
    </script>
</body>
</html>
