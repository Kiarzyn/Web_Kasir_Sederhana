<?php
/**
 * proses_bayar.php
 * Menerima data dari form pembayaran di index.php, lalu:
 * 1. Validasi ulang di sisi server (jangan pernah percaya data dari client/JS begitu saja)
 * 2. Menyimpan data ke tabel transaksi & detail_transaksi
 * 3. Mengurangi stok produk di tabel produk
 * 4. Mengarahkan ke cetak.php untuk mencetak nota
 *
 * Semua proses ini dibungkus dalam TRANSACTION MySQL, artinya:
 * jika salah satu langkah gagal, SEMUA perubahan akan dibatalkan (rollback),
 * sehingga data tidak menjadi setengah-setengah / tidak konsisten.
 */

session_start();
require 'koneksi.php';

// Pastikan diakses lewat form (POST) dan keranjang tidak kosong
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['keranjang'])) {
    header("Location: index.php?pesan=" . urlencode("Tidak ada transaksi untuk diproses."));
    exit;
}

// ================== AMBIL & VALIDASI INPUT ==================
$diskon_nilai = isset($_POST['diskon_nilai']) ? (float) $_POST['diskon_nilai'] : 0;
$diskon_tipe  = isset($_POST['diskon_tipe']) && $_POST['diskon_tipe'] === 'persen' ? 'persen' : 'rupiah';
$uang_diterima = isset($_POST['uang_diterima']) ? (int) $_POST['uang_diterima'] : 0;

if ($diskon_nilai < 0) $diskon_nilai = 0;
if ($uang_diterima < 0) $uang_diterima = 0;

// PENTING: subtotal dihitung ULANG dari data keranjang di session (bukan dari input form),
// supaya harga tidak bisa dimanipulasi dari sisi client.
$subtotal = 0;
foreach ($_SESSION['keranjang'] as $item) {
    $subtotal += $item['harga'] * $item['jumlah'];
}

// Hitung nominal diskon dalam Rupiah, tergantung tipe yang dipilih (% atau Rp).
// Perhitungan ini SENGAJA diulang di server (bukan cuma percaya hasil hitung JavaScript),
// supaya nominal diskon tidak bisa dimanipulasi dari sisi client.
if ($diskon_tipe === 'persen') {
    if ($diskon_nilai > 100) $diskon_nilai = 100; // diskon persen maksimal 100%
    $diskon_input = (int) round($subtotal * ($diskon_nilai / 100));
} else {
    $diskon_input = (int) round($diskon_nilai);
}

if ($diskon_input > $subtotal) {
    $diskon_input = $subtotal; // diskon tidak boleh melebihi subtotal
}

$total_bayar = $subtotal - $diskon_input;

if ($uang_diterima < $total_bayar) {
    header("Location: index.php?pesan=" . urlencode("Uang diterima kurang dari total belanja."));
    exit;
}

$kembalian = $uang_diterima - $total_bayar;

// Buat kode transaksi unik, misalnya: TRX-20260714-153045
$kode_transaksi = "TRX-" . date("Ymd-His");

// ================== MULAI TRANSACTION DATABASE ==================
$koneksi->begin_transaction();

try {
    // --- 1. Cek ulang stok setiap produk (mencegah race condition sederhana) ---
    foreach ($_SESSION['keranjang'] as $id_produk => $item) {
        $stmt = $koneksi->prepare("SELECT stok, nama_produk FROM produk WHERE id_produk = ? FOR UPDATE");
        $stmt->bind_param("i", $id_produk);
        $stmt->execute();
        $hasil = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$hasil) {
            throw new Exception("Produk '" . $item['nama'] . "' tidak ditemukan di database.");
        }
        if ($hasil['stok'] < $item['jumlah']) {
            throw new Exception("Stok '" . $hasil['nama_produk'] . "' tidak mencukupi (sisa " . $hasil['stok'] . ").");
        }
    }

    // --- 2. Simpan data transaksi (tabel transaksi) ---
    $stmt = $koneksi->prepare(
        "INSERT INTO transaksi (kode_transaksi, total_belanja, diskon, total_bayar, uang_diterima, kembalian)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("siiiii", $kode_transaksi, $subtotal, $diskon_input, $total_bayar, $uang_diterima, $kembalian);
    $stmt->execute();
    $id_transaksi = $koneksi->insert_id; // ambil ID transaksi yang baru saja dibuat
    $stmt->close();

    // --- 3. Simpan detail transaksi & kurangi stok produk ---
    $stmt_detail = $koneksi->prepare(
        "INSERT INTO detail_transaksi (id_transaksi, id_produk, nama_produk, harga_satuan, jumlah, subtotal)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt_stok = $koneksi->prepare("UPDATE produk SET stok = stok - ? WHERE id_produk = ?");

    foreach ($_SESSION['keranjang'] as $id_produk => $item) {
        $subtotal_item = $item['harga'] * $item['jumlah'];

        $stmt_detail->bind_param(
            "iisiii",
            $id_transaksi,
            $id_produk,
            $item['nama'],
            $item['harga'],
            $item['jumlah'],
            $subtotal_item
        );
        $stmt_detail->execute();

        $stmt_stok->bind_param("ii", $item['jumlah'], $id_produk);
        $stmt_stok->execute();
    }
    $stmt_detail->close();
    $stmt_stok->close();

    // Jika semua langkah di atas berhasil tanpa error, simpan permanen perubahannya
    $koneksi->commit();

    // Simpan ID transaksi ke session sementara, untuk dipakai oleh halaman cetak.php
    $_SESSION['id_transaksi_terakhir'] = $id_transaksi;

    // Kosongkan keranjang karena transaksi sudah selesai
    $_SESSION['keranjang'] = [];

    // Arahkan ke halaman cetak nota
    header("Location: cetak.php?id=" . $id_transaksi);
    exit;

} catch (Exception $e) {
    // Jika terjadi error di salah satu langkah, batalkan SEMUA perubahan
    $koneksi->rollback();
    header("Location: index.php?pesan=" . urlencode("Transaksi gagal: " . $e->getMessage()));
    exit;
}
