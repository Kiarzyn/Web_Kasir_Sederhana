-- =========================================================
-- DATABASE: kasir_db
-- Sistem Kasir Sederhana (PHP Native + MySQL/XAMPP)
-- =========================================================

CREATE DATABASE IF NOT EXISTS kasir_db;
USE kasir_db;

CREATE TABLE IF NOT EXISTS produk (
    id_produk INT AUTO_INCREMENT PRIMARY KEY,
    kode_produk VARCHAR(20) NOT NULL UNIQUE,
    nama_produk VARCHAR(100) NOT NULL,
    harga INT NOT NULL DEFAULT 0,
    stok INT NOT NULL DEFAULT 0,
    dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transaksi (
    id_transaksi INT AUTO_INCREMENT PRIMARY KEY,
    kode_transaksi VARCHAR(30) NOT NULL UNIQUE,
    total_belanja INT NOT NULL DEFAULT 0,
    diskon INT NOT NULL DEFAULT 0,
    total_bayar INT NOT NULL DEFAULT 0,
    uang_diterima INT NOT NULL DEFAULT 0,
    kembalian INT NOT NULL DEFAULT 0,
    waktu_transaksi TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS detail_transaksi (
    id_detail INT AUTO_INCREMENT PRIMARY KEY,
    id_transaksi INT NOT NULL,
    id_produk INT NOT NULL,
    nama_produk VARCHAR(100) NOT NULL,
    harga_satuan INT NOT NULL,
    jumlah INT NOT NULL,
    subtotal INT NOT NULL,
    CONSTRAINT fk_detail_transaksi
        FOREIGN KEY (id_transaksi) REFERENCES transaksi(id_transaksi)
        ON DELETE CASCADE,
    CONSTRAINT fk_detail_produk
        FOREIGN KEY (id_produk) REFERENCES produk(id_produk)
        ON DELETE RESTRICT
) ENGINE=InnoDB;


INSERT INTO produk (kode_produk, nama_produk, harga, stok) VALUES
('P001', 'Indomie Goreng', 3500, 50),
('P002', 'Aqua Botol 600ml', 4000, 40),
('P003', 'Teh Pucuk 350ml', 5000, 30),
('P004', 'Roti Tawar', 15000, 15),
('P005', 'Kopi Kapal Api Sachet', 1500, 100),
('P006', 'Beras 5kg', 65000, 10),
('P007', 'Minyak Goreng 1L', 18000, 20),
('P008', 'Gula Pasir 1kg', 14000, 25),
('P009', 'Sabun Mandi', 3000, 20),
('P010', 'Rokok Sampoerna', 29000, 15);
