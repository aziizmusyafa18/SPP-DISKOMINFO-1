-- =============================================================
-- SPP-DISKOMINFO - Database Schema
-- =============================================================
-- Jalankan di phpMyAdmin atau MySQL CLI:
--   mysql -u root -p < schema.sql
-- =============================================================

CREATE DATABASE IF NOT EXISTS spp_diskominfo
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE spp_diskominfo;

-- -------------------------------------------------------------
-- Tabel: users (admin accounts)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  nama_lengkap  VARCHAR(100) NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login    DATETIME     NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Seed admin awal
--   username : admin
--   password : admin123   <-- WAJIB diganti setelah login pertama
-- -------------------------------------------------------------
INSERT INTO users (username, password_hash, nama_lengkap)
VALUES (
  'admin',
  '$2y$10$zwRpdk7Y4LzM.etYPciaNeKswFjTPgAGb2XlnJuHE6hfRI2fX9pP2',
  'Administrator'
)
ON DUPLICATE KEY UPDATE username = username;

-- -------------------------------------------------------------
-- Tabel: laporan (riwayat laporan yang di-export ke PDF)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS laporan (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  nama_pelapor          VARCHAR(255) NOT NULL,
  kepada                VARCHAR(255) NOT NULL,
  perihal_surat         VARCHAR(255) NOT NULL,
  nama_kegiatan         VARCHAR(255) NOT NULL,
  tanggal_waktu_rapat   DATETIME     NOT NULL,
  tanggal_waktu_selesai DATETIME     NOT NULL,
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
