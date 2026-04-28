-- Migrasi untuk menambahkan kolom tanggal_waktu_selesai pada tabel laporan
USE spp_diskominfo;

ALTER TABLE laporan 
ADD COLUMN tanggal_waktu_selesai DATETIME NOT NULL AFTER tanggal_waktu_rapat;
