<?php
require_once __DIR__ . '/config/db.php';

try {
    $sql = "ALTER TABLE laporan ADD COLUMN tanggal_waktu_selesai DATETIME NOT NULL AFTER tanggal_waktu_rapat";
    db()->exec($sql);
    echo "Migration successful: Column 'tanggal_waktu_selesai' added.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
unlink(__FILE__);
