<?php
/**
 * cetak.php
 * Menampilkan nota belanja dalam format minimalis siap cetak
 * ke printer thermal (lebar kertas 58mm/80mm), menggunakan window.print() bawaan browser.
 */

session_start();
require 'koneksi.php';

// Ambil ID transaksi dari URL (?id=...)
$id_transaksi = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id_transaksi <= 0) {
    die("ID transaksi tidak valid.");
}

// --- Ambil data header transaksi ---
$stmt = $koneksi->prepare("SELECT * FROM transaksi WHERE id_transaksi = ?");
$stmt->bind_param("i", $id_transaksi);
$stmt->execute();
$transaksi = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$transaksi) {
    die("Data transaksi tidak ditemukan.");
}

// --- Ambil data detail/rincian barang ---
$stmt = $koneksi->prepare("SELECT * FROM detail_transaksi WHERE id_transaksi = ?");
$stmt->bind_param("i", $id_transaksi);
$stmt->execute();
$detail = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Nota - <?= htmlspecialchars($transaksi['kode_transaksi']) ?></title>
<style>
    * { box-sizing: border-box; }
    body {
        font-family: 'Courier New', Courier, monospace;
        font-size: 12px;
        color: #000;
        background: #eee;
        margin: 0;
        padding: 20px;
    }
    .struk {
        width: 280px;
        margin: 0 auto;
        background: #fff;
        padding: 12px;
    }
    .struk h2 { text-align:center; margin:0 0 2px; font-size:15px; }
    .struk p { margin:2px 0; text-align:center; }
    hr { border:none; border-top:1px dashed #000; margin:6px 0; }
    .item { margin-bottom:4px; }
    .item .nama { font-weight:bold; }
    .baris { display:flex; justify-content:space-between; }
    .total-baris { font-weight:bold; font-size:13px; }
    .aksi-cetak {
        max-width:280px;
        margin:14px auto 0;
        display:flex;
        gap:8px;
    }
    .aksi-cetak button, .aksi-cetak a {
        flex:1;
        text-align:center;
        padding:10px;
        border:none;
        border-radius:6px;
        cursor:pointer;
        text-decoration:none;
        font-family:Arial, sans-serif;
        font-size:13px;
        font-weight:600;
    }
    .btn-print { background:#2563eb; color:#fff; }
    .btn-kembali { background:#e2e8f0; color:#1e293b; }

    @media print {
        body { background:#fff; padding:0; }
        .aksi-cetak { display:none; }
        .struk { width:100%; padding:0; }
    }
</style>
</head>
<body>

<div class="struk">
    <h2>TOKO SAYA</h2>
    <p>Jl. Contoh Alamat No. 123</p>
    <p><?= date("d/m/Y H:i:s", strtotime($transaksi['waktu_transaksi'])) ?></p>
    <p>No: <?= htmlspecialchars($transaksi['kode_transaksi']) ?></p>
    <hr>

    <?php while ($item = $detail->fetch_assoc()): ?>
        <div class="item">
            <div class="nama"><?= htmlspecialchars($item['nama_produk']) ?></div>
            <div class="baris">
                <span><?= (int) $item['jumlah'] ?> x Rp <?= number_format($item['harga_satuan'], 0, ',', '.') ?></span>
                <span>Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></span>
            </div>
        </div>
    <?php endwhile; ?>

    <hr>
    <div class="baris">
        <span>Subtotal</span>
        <span>Rp <?= number_format($transaksi['total_belanja'], 0, ',', '.') ?></span>
    </div>
    <?php if ($transaksi['diskon'] > 0): ?>
        <div class="baris">
            <span>Diskon</span>
            <span>- Rp <?= number_format($transaksi['diskon'], 0, ',', '.') ?></span>
        </div>
    <?php endif; ?>
    <div class="baris total-baris">
        <span>TOTAL</span>
        <span>Rp <?= number_format($transaksi['total_bayar'], 0, ',', '.') ?></span>
    </div>
    <div class="baris">
        <span>Bayar</span>
        <span>Rp <?= number_format($transaksi['uang_diterima'], 0, ',', '.') ?></span>
    </div>
    <div class="baris">
        <span>Kembali</span>
        <span>Rp <?= number_format($transaksi['kembalian'], 0, ',', '.') ?></span>
    </div>

    <hr>
    <p>Terima kasih atas kunjungan Anda!</p>
</div>

<div class="aksi-cetak">
    <a href="index.php" class="btn-kembali">Kembali ke Kasir</a>
    <button class="btn-print" onclick="window.print()">Cetak Nota</button>
</div>

</body>
</html>
