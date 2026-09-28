-- =====================================================================
-- SISTEM RENTAL MOBIL - DATABASE SCHEMA
-- Database: MariaDB / MySQL
-- Jalankan file ini terlebih dahulu sebelum menjalankan aplikasi.
--
-- CATATAN: Aplikasi ini menggunakan LOGIN BYPASS (admin/admin123 di-hardcode
-- langsung di login.php, tanpa cek ke database), jadi tabel "users" TIDAK
-- diperlukan di versi ini.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS db_rental_mobil CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_rental_mobil;

-- Tabel Mobil (Data Utama Aset Kendaraan)
CREATE TABLE IF NOT EXISTS mobil (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plat_nomor VARCHAR(15) NOT NULL UNIQUE,
    merk_model VARCHAR(100) NOT NULL,
    tipe ENUM('City Car', 'MPV', 'SUV', 'Sedan', 'Pickup') NOT NULL DEFAULT 'MPV',
    tahun YEAR NULL,
    harga_per_hari DECIMAL(12,2) NOT NULL,
    status ENUM('Tersedia', 'Disewa', 'Maintenance') NOT NULL DEFAULT 'Tersedia',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabel Penyewa (Data Pendukung / Master Data Pelanggan)
CREATE TABLE IF NOT EXISTS penyewa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap VARCHAR(100) NOT NULL,
    no_ktp VARCHAR(20) NOT NULL UNIQUE,
    no_hp VARCHAR(20) NOT NULL,
    alamat TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabel Sewa (Core System - Transaksi Sewa per Mobil)
CREATE TABLE IF NOT EXISTS sewa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mobil_id INT NOT NULL,
    penyewa_id INT NOT NULL,
    tanggal_mulai DATE NOT NULL,
    tanggal_rencana_selesai DATE NOT NULL,
    tanggal_kembali DATE NULL,
    total_hari INT NULL,
    total_biaya DECIMAL(12,2) NULL,
    status ENUM('Berlangsung', 'Selesai') NOT NULL DEFAULT 'Berlangsung',
    catatan VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sewa_mobil FOREIGN KEY (mobil_id) REFERENCES mobil(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sewa_penyewa FOREIGN KEY (penyewa_id) REFERENCES penyewa(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Contoh data mobil
INSERT INTO mobil (plat_nomor, merk_model, tipe, tahun, harga_per_hari, status) VALUES
('B 1234 ABC', 'Toyota Avanza', 'MPV', 2022, 350000, 'Tersedia'),
('B 5678 DEF', 'Honda Brio', 'City Car', 2023, 300000, 'Tersedia'),
('B 9012 GHI', 'Toyota Fortuner', 'SUV', 2021, 800000, 'Tersedia'),
('B 3456 JKL', 'Toyota Innova', 'MPV', 2022, 450000, 'Tersedia');

-- Contoh data penyewa
INSERT INTO penyewa (nama_lengkap, no_ktp, no_hp, alamat) VALUES
('Andi Saputra', '3271010101900001', '081234567890', 'Jl. Melati No. 10, Jakarta'),
('Dewi Lestari', '3271020202920002', '081234567891', 'Jl. Kenanga No. 5, Bandung');
