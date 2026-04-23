-- =============================================================
-- Migration: tabel laporan (riwayat export PDF)
-- Jalankan di DB yang sudah ada:
--   mysql -u root -p spp_diskominfo < migration_laporan.sql
-- =============================================================

USE spp_diskominfo;

CREATE TABLE IF NOT EXISTS laporan (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  kepada                VARCHAR(255) NOT NULL,
  perihal_surat         VARCHAR(255) NOT NULL,
  nama_kegiatan         VARCHAR(255) NOT NULL,
  tanggal_waktu_rapat   DATETIME     NOT NULL,
  tempat_rapat          VARCHAR(255) NOT NULL,
  pimpinan_rapat        VARCHAR(255) NOT NULL,
  peserta_rapat         TEXT         NOT NULL,
  hasil_pembahasan      TEXT         NOT NULL,
  kesimpulan_saran_rtl  TEXT         NOT NULL,
  filename              VARCHAR(255) NOT NULL,
  file_size             INT          NOT NULL,
  pdf_blob              LONGBLOB     NOT NULL,
  created_by            INT          NULL,
  created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created_at (created_at),
  INDEX idx_perihal (perihal_surat),
  CONSTRAINT fk_laporan_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
