<?php
/**
 * koneksi.php
 * File ini bertugas menyambungkan aplikasi PHP ke database MySQL
 * menggunakan ekstensi MySQLi (Object Oriented).
 *
 * Semua file lain (index.php, produk.php, dll) akan memanggil file ini
 * dengan cara: require 'koneksi.php';
 */

// --- Pengaturan koneksi (sesuaikan jika perlu) ---
$host     = "localhost";   // Alamat server database (default XAMPP: localhost)
$username = "root";        // Username default XAMPP
$password = "";            // Password default XAMPP (kosong)
$database = "kasir_db";    // Nama database yang sudah kita buat lewat database.sql

// --- Membuat koneksi ---
$koneksi = new mysqli($host, $username, $password, $database);

// --- Cek apakah koneksi berhasil ---
if ($koneksi->connect_error) {
    // Jika gagal konek, hentikan skrip dan tampilkan pesan error
    die("Koneksi ke database gagal: " . $koneksi->connect_error .
        "<br>Pastikan XAMPP (Apache & MySQL) sudah berjalan dan database 'kasir_db' sudah dibuat.");
}

// --- Atur karakter set agar teks (misal: nama produk) tidak rusak ---
$koneksi->set_charset("utf8mb4");

// Catatan untuk pemula:
// Variabel $koneksi inilah yang akan dipakai di file lain untuk
// menjalankan query, contoh: $koneksi->query("SELECT ...");
?>
