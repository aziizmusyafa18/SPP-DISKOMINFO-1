-- Migration: Create stafs table
USE spp_diskominfo;

CREATE TABLE IF NOT EXISTS stafs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    nama VARCHAR(100) NOT NULL,
    pangkat_golongan VARCHAR(100) NULL,
    nip VARCHAR(50) NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_staf_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
