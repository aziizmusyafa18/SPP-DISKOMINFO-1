<?php
/**
 * Endpoint: simpan laporan hasil export PDF.
 * Menerima multipart/form-data: field metadata + file PDF.
 * Akses: terbuka (akan dibatasi ketika aktor aplikasi sudah ditentukan).
 */

require_once __DIR__ . '/../includes/session.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$required = [
    'kepada', 'perihalSurat', 'namaKegiatan', 'tanggalWaktuRapat',
    'tempatRapat', 'pimpinanRapat', 'pesertaRapat',
    'hasilPembahasan', 'kesimpulanSaranRTL',
];
foreach ($required as $field) {
    if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => "Field '$field' wajib diisi."]);
        exit;
    }
}

if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'File PDF tidak ditemukan atau gagal upload.']);
    exit;
}

$pdfFile = $_FILES['pdf'];
$maxSize = 20 * 1024 * 1024; // 20 MB
if ($pdfFile['size'] > $maxSize) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'error' => 'Ukuran PDF melebihi 20 MB.']);
    exit;
}

// Validasi signature PDF (%PDF-)
$handle = fopen($pdfFile['tmp_name'], 'rb');
$header = $handle ? fread($handle, 5) : '';
if ($handle) fclose($handle);
if ($header !== '%PDF-') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'File yang diunggah bukan PDF valid.']);
    exit;
}

$pdfBlob = file_get_contents($pdfFile['tmp_name']);
if ($pdfBlob === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Gagal membaca file PDF.']);
    exit;
}

// Normalisasi tanggal (datetime-local format: YYYY-MM-DDTHH:MM)
$tanggalInput = $_POST['tanggalWaktuRapat'];
$tanggalObj = DateTime::createFromFormat('Y-m-d\TH:i', $tanggalInput)
    ?: DateTime::createFromFormat('Y-m-d\TH:i:s', $tanggalInput);
if (!$tanggalObj) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Format tanggal tidak valid.']);
    exit;
}
$tanggalMysql = $tanggalObj->format('Y-m-d H:i:s');

$filename = $_POST['filename'] ?? ('Laporan_' . time() . '.pdf');
// Bersihkan nama file
$filename = preg_replace('/[^A-Za-z0-9._\- ]/', '_', $filename);
if (!str_ends_with(strtolower($filename), '.pdf')) {
    $filename .= '.pdf';
}

$createdBy = is_logged_in() ? ($_SESSION['user_id'] ?? null) : null;

try {
    $stmt = db()->prepare('
        INSERT INTO laporan (
            kepada, perihal_surat, nama_kegiatan, tanggal_waktu_rapat,
            tempat_rapat, pimpinan_rapat, peserta_rapat, hasil_pembahasan,
            kesimpulan_saran_rtl, filename, file_size, pdf_blob, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->bindValue(1,  $_POST['kepada']);
    $stmt->bindValue(2,  $_POST['perihalSurat']);
    $stmt->bindValue(3,  $_POST['namaKegiatan']);
    $stmt->bindValue(4,  $tanggalMysql);
    $stmt->bindValue(5,  $_POST['tempatRapat']);
    $stmt->bindValue(6,  $_POST['pimpinanRapat']);
    $stmt->bindValue(7,  $_POST['pesertaRapat']);
    $stmt->bindValue(8,  $_POST['hasilPembahasan']);
    $stmt->bindValue(9,  $_POST['kesimpulanSaranRTL']);
    $stmt->bindValue(10, $filename);
    $stmt->bindValue(11, $pdfFile['size'], PDO::PARAM_INT);
    $stmt->bindValue(12, $pdfBlob, PDO::PARAM_LOB);
    $stmt->bindValue(13, $createdBy, $createdBy === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode([
        'ok' => true,
        'id' => (int) db()->lastInsertId(),
        'filename' => $filename,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Gagal menyimpan laporan: ' . $e->getMessage()]);
}
