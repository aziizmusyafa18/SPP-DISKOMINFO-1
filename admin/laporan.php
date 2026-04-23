<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../includes/csrf.php';

$user = current_user();
if (!$user) {
    header('Location: logout.php');
    exit;
}

// Filter & pagination
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
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
    <nav class="admin-navbar">
        <div class="brand">
            <img src="../public/logo.png" alt="Logo">
            <span>SPP DISKOMINFO — Admin</span>
        </div>
        <div class="user-area">
            <a href="dashboard.php" class="btn-logout" style="text-decoration:none;">← Dashboard</a>
            <span>Halo, <strong><?= htmlspecialchars($user['nama_lengkap'], ENT_QUOTES, 'UTF-8') ?></strong></span>
            <form method="POST" action="logout.php" style="margin:0;">
                <?= csrf_field() ?>
                <button type="submit" class="btn-logout">Logout</button>
            </form>
        </div>
    </nav>

    <main class="dashboard-container">
        <section class="dashboard-greeting">
            <h2>Monitoring Laporan</h2>
            <p>Daftar laporan yang telah di-export ke PDF dan tersimpan di database.</p>
        </section>

        <section class="stat-row">
            <div class="stat-box">
                <span class="stat-label">Total Laporan</span>
                <span class="stat-value"><?= (int) $stats['total'] ?></span>
            </div>
            <div class="stat-box">
                <span class="stat-label">Hari Ini</span>
                <span class="stat-value"><?= (int) $stats['today'] ?></span>
            </div>
            <div class="stat-box">
                <span class="stat-label">7 Hari Terakhir</span>
                <span class="stat-value"><?= (int) $stats['week'] ?></span>
            </div>
        </section>

        <form method="GET" class="filter-bar">
            <input type="text" name="q" placeholder="Cari perihal, kegiatan, atau kepada..."
                   value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">
            <input type="date" name="from" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>" title="Dari tanggal">
            <input type="date" name="to" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>" title="Sampai tanggal">
            <button type="submit" class="btn-primary btn-inline">Filter</button>
            <a href="laporan.php" class="btn-reset">Reset</a>
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
                    return 'laporan.php?' . http_build_query([
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
    </main>
</body>
</html>
