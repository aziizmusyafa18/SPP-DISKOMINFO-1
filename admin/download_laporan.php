<?php
/**
 * Stream PDF laporan ke browser (hanya admin yang login).
 */
require_once __DIR__ . '/auth_check.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('ID tidak valid.');
}

$mode = $_GET['mode'] ?? 'inline'; // 'inline' atau 'download'
$disposition = $mode === 'download' ? 'attachment' : 'inline';

$stmt = db()->prepare('SELECT filename, file_size, pdf_blob FROM laporan WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    exit('Laporan tidak ditemukan.');
}

header('Content-Type: application/pdf');
header('Content-Length: ' . $row['file_size']);
header('Content-Disposition: ' . $disposition . '; filename="' . $row['filename'] . '"');
header('Cache-Control: private, max-age=0');
echo $row['pdf_blob'];
