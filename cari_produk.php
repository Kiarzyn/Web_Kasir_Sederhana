<?php
/**
 * cari_produk.php
 * Endpoint AJAX (dipanggil oleh JavaScript di index.php) untuk pencarian produk secara live.
 * Menerima parameter GET 'q' (kata kunci), mengembalikan data produk dalam format JSON.
 *
 * Jika 'q' kosong, sengaja mengembalikan array kosong -> supaya tabel produk
 * di halaman kasir TIDAK menampilkan apa-apa sebelum pengguna mulai mengetik.
 */

require 'koneksi.php';

header('Content-Type: application/json');

$kata_kunci = isset($_GET['q']) ? trim($_GET['q']) : '';

// Jika kata kunci kosong, langsung kembalikan array kosong (tidak query ke database)
if ($kata_kunci === '') {
    echo json_encode([]);
    exit;
}

// Gunakan prepared statement + wildcard LIKE dengan aman (mencegah SQL Injection)
$stmt = $koneksi->prepare(
    "SELECT id_produk, kode_produk, nama_produk, harga, stok
     FROM produk
     WHERE nama_produk LIKE CONCAT('%', ?, '%')
        OR kode_produk LIKE CONCAT('%', ?, '%')
     ORDER BY nama_produk ASC
     LIMIT 20"
);
$stmt->bind_param("ss", $kata_kunci, $kata_kunci);
$stmt->execute();
$hasil = $stmt->get_result();

$daftar = [];
while ($row = $hasil->fetch_assoc()) {
    $daftar[] = [
        'id_produk'   => (int) $row['id_produk'],
        'kode_produk' => $row['kode_produk'],
        'nama_produk' => $row['nama_produk'],
        'harga'       => (int) $row['harga'],
        'stok'        => (int) $row['stok'],
    ];
}
$stmt->close();

echo json_encode($daftar);
