<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../includes/csrf.php';

$user = current_user();
if (!$user) {
    header('Location: logout.php');
    exit;
}

// --- LOGIKA MONITORING (dari laporan.php) ---
$q        = trim($_GET['q'] ?? '');
$dateFrom = trim($_GET['from'] ?? '');
$dateTo   = trim($_GET['to'] ?? '');
$page     = max(1, (int) ($_GET['page'] ?? 1));
$perPage  = 15;
$offset   = ($page - 1) * $perPage;

$where  = [];
$params = [];

if ($q !== '') {
    $where[] = '(perihal_surat LIKE ? OR nama_kegiatan LIKE ? OR kepada LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($dateFrom !== '') {
    $where[] = 'created_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $where[] = 'created_at <= ?';
    $params[] = $dateTo . ' 23:59:59';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Hitung total
$countStmt = db()->prepare("SELECT COUNT(*) FROM laporan $whereSql");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

// Ambil data halaman ini
$sql = "SELECT l.id, l.kepada, l.perihal_surat, l.nama_kegiatan, l.tanggal_waktu_rapat,
               l.tempat_rapat, l.pimpinan_rapat, l.filename, l.file_size, l.created_at,
               u.nama_lengkap AS created_by_name
        FROM laporan l
        LEFT JOIN users u ON u.id = l.created_by
        $whereSql
        ORDER BY l.created_at DESC
        LIMIT $perPage OFFSET $offset";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Statistik singkat
$statStmt = db()->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS week
    FROM laporan
");
$stats = $statStmt->fetch() ?: ['total' => 0, 'today' => 0, 'week' => 0];

$formatSize = function ($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / (1024 * 1024), 2) . ' MB';
};

$formatDate = function ($datetime) {
    if (!$datetime) return '-';
    $ts = strtotime($datetime);
    $bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    return date('d', $ts) . ' ' . $bulan[(int) date('n', $ts) - 1] . ' ' . date('Y H:i', $ts);
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Laporan - SPP DISKOMINFO</title>
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
                <a href="dashboard.php" class="nav-item active">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                    <span>Monitoring</span>
                </a>
                <a href="../public/index.html" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                    <span>Buat Laporan</span>
                </a>
                <a href="users.php" class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span>Kelola User</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <form method="POST" action="logout.php" style="margin:0;">
                    <?= csrf_field() ?>
                    <button type="submit" class="nav-item nav-item-btn">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2=x`"9" y2="12"/></svg>
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
                <div class="top-bar-title">Monitoring Laporan</div>
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
                    <h2>Monitoring Laporan</h2>
                    <p>Daftar laporan yang telah di-export ke PDF dan tersimpan di database.</p>
                </section>

                <section class="stat-row">
                    <div class="stat-box stat-box--blue">
                        <div class="stat-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        </div>
                        <div class="stat-info">
                            <span class="stat-label">Total Laporan</span>
                            <span class="stat-value"><?= (int) $stats['total'] ?></span>
                        </div>
                    </div>
                    <div class="stat-box stat-box--green">
                        <div class="stat-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01M16 18h.01"/></svg>
                        </div>
                        <div class="stat-info">
                            <span class="stat-label">Hari Ini</span>
                            <span class="stat-value"><?= (int) $stats['today'] ?></span>
                        </div>
                    </div>
                    <div class="stat-box stat-box--orange">
                        <div class="stat-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                        </div>
                        <div class="stat-info">
                            <span class="stat-label">7 Hari Terakhir</span>
                            <span class="stat-value"><?= (int) $stats['week'] ?></span>
                        </div>
                    </div>
                </section>

                <form method="GET" class="filter-bar">
                    <input type="text" name="q" placeholder="Cari perihal, kegiatan, atau kepada..."
                           value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="date" name="from" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>" title="Dari tanggal">
                    <input type="date" name="to" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>" title="Sampai tanggal">
                    <button type="submit" class="btn-primary btn-inline">Filter</button>
                    <a href="dashboard.php" class="btn-reset">Reset</a>
                </form>
                
                <section class="table-wrapper">
                    <table class="laporan-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Perihal</th>
                                <th>Nama Kegiatan</th>
                                <th>Tanggal Rapat</th>
                                <th>Pimpinan</th>
                                <th>Dibuat</th>
                                <th>Ukuran</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$rows): ?>
                                <tr><td colspan="8" class="empty-row">Belum ada laporan yang tersimpan.</td></tr>
                            <?php else: ?>
                                <?php foreach ($rows as $i => $row): ?>
                                    <tr>
                                        <td><?= $offset + $i + 1 ?></td>
                                        <td>
                                            <div class="cell-title"><?= htmlspecialchars($row['perihal_surat'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="cell-sub">Kepada: <?= htmlspecialchars($row['kepada'], ENT_QUOTES, 'UTF-8') ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($row['nama_kegiatan'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= $formatDate($row['tanggal_waktu_rapat']) ?></td>
                                        <td><?= htmlspecialchars($row['pimpinan_rapat'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= $formatDate($row['created_at']) ?></td>
                                        <td><?= $formatSize((int) $row['file_size']) ?></td>
                                        <td class="cell-actions">
                                            <a href="download_laporan.php?id=<?= (int) $row['id'] ?>&mode=inline"
                                               target="_blank" class="btn-action btn-view">Lihat</a>
                                            <a href="download_laporan.php?id=<?= (int) $row['id'] ?>&mode=download"
                                               class="btn-action btn-download">Unduh</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </section>

                <?php if ($totalPages > 1): ?>
                    <nav class="pagination">
                        <?php
                        $buildUrl = function ($p) use ($q, $dateFrom, $dateTo) {
                            return 'dashboard.php?' . http_build_query([
                                'q' => $q, 'from' => $dateFrom, 'to' => $dateTo, 'page' => $p,
                            ]);
                        };
                        ?>
                        <?php if ($page > 1): ?>
                            <a href="<?= $buildUrl($page - 1) ?>">&laquo; Sebelumnya</a>
                        <?php endif; ?>
                        <span>Halaman <?= $page ?> dari <?= $totalPages ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a href="<?= $buildUrl($page + 1) ?>">Berikutnya &raquo;</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
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
