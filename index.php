<?php
/**
 * index.php
 * Halaman utama Kasir:
 * - Menampilkan daftar produk untuk dicari & ditambahkan ke keranjang
 * - Keranjang belanja disimpan di PHP SESSION (bertahan selama browser terbuka)
 * - Menghitung total, diskon, uang bayar, dan kembalian
 */

session_start();
require 'koneksi.php';

// Siapkan keranjang di session jika belum ada
if (!isset($_SESSION['keranjang'])) {
    $_SESSION['keranjang'] = []; // format: [ id_produk => ['nama'=>..,'harga'=>..,'jumlah'=>..,'stok'=>..] ]
}

$pesan = "";

// ================== PROSES AKSI KERANJANG (via form POST) ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {

    // --- Tambah produk ke keranjang ---
    if ($_POST['aksi'] === 'tambah') {
        $id_produk = (int) $_POST['id_produk'];

        // Ambil data produk dari database menggunakan prepared statement (aman dari SQL Injection)
        $stmt = $koneksi->prepare("SELECT id_produk, nama_produk, harga, stok FROM produk WHERE id_produk = ?");
        $stmt->bind_param("i", $id_produk);
        $stmt->execute();
        $hasil = $stmt->get_result();
        $produk = $hasil->fetch_assoc();
        $stmt->close();

        if ($produk) {
            if ($produk['stok'] <= 0) {
                $pesan = "Stok produk '" . $produk['nama_produk'] . "' habis!";
            } else {
                // Jika produk sudah ada di keranjang, tambah jumlahnya
                if (isset($_SESSION['keranjang'][$id_produk])) {
                    $jumlah_baru = $_SESSION['keranjang'][$id_produk]['jumlah'] + 1;
                    if ($jumlah_baru > $produk['stok']) {
                        $pesan = "Jumlah melebihi stok yang tersedia (" . $produk['stok'] . ")";
                    } else {
                        $_SESSION['keranjang'][$id_produk]['jumlah'] = $jumlah_baru;
                    }
                } else {
                    // Produk baru masuk ke keranjang
                    $_SESSION['keranjang'][$id_produk] = [
                        'nama'   => $produk['nama_produk'],
                        'harga'  => $produk['harga'],
                        'jumlah' => 1,
                        'stok'   => $produk['stok'],
                    ];
                }
            }
        }
    }

    // --- Ubah jumlah produk di keranjang ---
    if ($_POST['aksi'] === 'ubah_jumlah') {
        $id_produk = (int) $_POST['id_produk'];
        $jumlah = (int) $_POST['jumlah'];

        if (isset($_SESSION['keranjang'][$id_produk])) {
            $stok_tersedia = $_SESSION['keranjang'][$id_produk]['stok'];
            if ($jumlah < 1) $jumlah = 1;
            if ($jumlah > $stok_tersedia) {
                $jumlah = $stok_tersedia;
                $pesan = "Jumlah disesuaikan dengan stok maksimal (" . $stok_tersedia . ")";
            }
            $_SESSION['keranjang'][$id_produk]['jumlah'] = $jumlah;
        }
    }

    // --- Hapus produk dari keranjang ---
    if ($_POST['aksi'] === 'hapus') {
        $id_produk = (int) $_POST['id_produk'];
        unset($_SESSION['keranjang'][$id_produk]);
    }

    // --- Kosongkan seluruh keranjang ---
    if ($_POST['aksi'] === 'kosongkan') {
        $_SESSION['keranjang'] = [];
    }

    // Redirect agar tidak ada resubmit form ketika halaman di-refresh
    header("Location: index.php" . ($pesan ? "?pesan=" . urlencode($pesan) : ""));
    exit;
}

if (isset($_GET['pesan'])) {
    $pesan = $_GET['pesan'];
}

// Catatan: pencarian produk sekarang dilakukan secara LIVE lewat AJAX
// (lihat file cari_produk.php dan <script> di bagian bawah halaman ini),
// jadi tidak perlu lagi query pencarian di sini.

// ================== HITUNG TOTAL KERANJANG ==================
$subtotal = 0;
foreach ($_SESSION['keranjang'] as $item) {
    $subtotal += $item['harga'] * $item['jumlah'];
}

// ================== DATA RIWAYAT TRANSAKSI (dari MySQL, bukan localStorage) ==================
// Statistik ringkas
$statTrx = $koneksi->query("SELECT COUNT(*) AS jumlah FROM transaksi")->fetch_assoc()['jumlah'];
$statPendapatan = $koneksi->query("SELECT COALESCE(SUM(total_bayar),0) AS total FROM transaksi")->fetch_assoc()['total'];
$statHariIni = $koneksi->query("SELECT COUNT(*) AS jumlah FROM transaksi WHERE DATE(waktu_transaksi) = CURDATE()")->fetch_assoc()['jumlah'];

// Daftar transaksi terakhir (maksimal 50 transaksi terbaru), lengkap dengan rincian barangnya
$riwayat_transaksi = [];
$hasil_trx = $koneksi->query("SELECT * FROM transaksi ORDER BY waktu_transaksi DESC LIMIT 50");
while ($trx = $hasil_trx->fetch_assoc()) {
    $stmt_item = $koneksi->prepare("SELECT nama_produk, jumlah FROM detail_transaksi WHERE id_transaksi = ?");
    $stmt_item->bind_param("i", $trx['id_transaksi']);
    $stmt_item->execute();
    $hasil_item = $stmt_item->get_result();
    $daftar_item = [];
    while ($item = $hasil_item->fetch_assoc()) {
        $daftar_item[] = $item['nama_produk'] . ' x' . $item['jumlah'];
    }
    $stmt_item->close();
    $trx['daftar_item'] = implode(', ', $daftar_item);
    $riwayat_transaksi[] = $trx;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kasir - Sistem POS PHP & MySQL</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header class="topbar">
    <h1>Web Kasir Sederhana</h1>
    <nav>
        <button type="button" class="btn btn-nav tab-btn active" data-tab="kasir">Kasir</button>
        <button type="button" class="btn btn-nav tab-btn" data-tab="riwayat">Riwayat Transaksi</button>
        <a href="produk.php" class="btn btn-nav">Manajemen Produk</a>
    </nav>
</header>

<main class="container">

    <?php if ($pesan): ?>
        <div class="alert"><?= htmlspecialchars($pesan) ?></div>
    <?php endif; ?>

    <!-- ===================== TAB: KASIR ===================== -->
    <div class="tab-page active" id="tab-kasir">
    <div class="grid-kasir">

        <!-- ============ KOLOM KIRI: PENCARIAN & KERANJANG ============ -->
        <div>
            <div class="card">
                <div class="search-form">
                    <input type="text" id="inputCariProduk" placeholder="Ketik nama atau kode produk untuk mencari..." autocomplete="off">
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama Produk</th>
                                <th>Harga</th>
                                <th>Stok</th>
                                <th></th>
                            </tr>
                        </thead>
                        <!-- Tabel ini sengaja dikosongkan di awal (server-side).
                             Isinya baru akan diisi oleh JavaScript (lihat <script> di bawah)
                             saat pengguna mulai mengetik di kolom pencarian. -->
                        <tbody id="hasilPencarianProduk">
                            <tr><td colspan="5" class="empty">Ketik di kolom pencarian untuk menampilkan produk...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="flex-between">
                    <h3>Keranjang Belanja</h3>
                    <?php if (count($_SESSION['keranjang']) > 0): ?>
                        <form method="post" action="index.php" onsubmit="return confirm('Kosongkan keranjang?');">
                            <input type="hidden" name="aksi" value="kosongkan">
                            <button type="submit" class="btn btn-outline btn-sm">Bersihkan</button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Harga</th>
                                <th style="width:110px;">Jumlah</th>
                                <th>Subtotal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (count($_SESSION['keranjang']) === 0): ?>
                            <tr><td colspan="5" class="empty">Keranjang masih kosong</td></tr>
                        <?php else: ?>
                            <?php foreach ($_SESSION['keranjang'] as $id_produk => $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['nama']) ?></td>
                                    <td>Rp <?= number_format($item['harga'], 0, ',', '.') ?></td>
                                    <td>
                                        <form method="post" action="index.php" class="inline-form">
                                            <input type="hidden" name="aksi" value="ubah_jumlah">
                                            <input type="hidden" name="id_produk" value="<?= (int) $id_produk ?>">
                                            <input type="number" name="jumlah" min="1"
                                                   max="<?= (int) $item['stok'] ?>"
                                                   value="<?= (int) $item['jumlah'] ?>"
                                                   class="qty-input"
                                                   onchange="this.form.submit()">
                                        </form>
                                    </td>
                                    <td>Rp <?= number_format($item['harga'] * $item['jumlah'], 0, ',', '.') ?></td>
                                    <td>
                                        <form method="post" action="index.php">
                                            <input type="hidden" name="aksi" value="hapus">
                                            <input type="hidden" name="id_produk" value="<?= (int) $id_produk ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">✕</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ============ KOLOM KANAN: PEMBAYARAN ============ -->
        <div>
            <div class="card">
                <h3>Ringkasan Pembayaran</h3>

                <form method="post" action="proses_bayar.php" id="formBayar">
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span id="labelSubtotal">Rp <?= number_format($subtotal, 0, ',', '.') ?></span>
                    </div>
                    <input type="hidden" name="subtotal" id="inputSubtotal" value="<?= (int) $subtotal ?>">

                    <div class="form-row">
                        <label>Diskon</label>
                        <div class="form-inline">
                            <div>
                                <input type="number" name="diskon_nilai" id="inputDiskon" min="0" value="0">
                            </div>
                            <div style="max-width:100px;">
                                <select name="diskon_tipe" id="selectDiskonTipe">
                                    <option value="persen">%</option>
                                    <option value="rupiah">Rp</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="summary-row total">
                        <span>Total</span>
                        <span id="labelTotal">Rp <?= number_format($subtotal, 0, ',', '.') ?></span>
                    </div>

                    <div class="form-row">
                        <label>Uang Diterima</label>
                        <input type="number" name="uang_diterima" id="inputBayar" min="0" placeholder="0" required>
                    </div>

                    <div class="summary-row kembali">
                        <span>Kembalian</span>
                        <span id="labelKembali">Rp 0</span>
                    </div>

                    <button type="submit" class="btn btn-success btn-block"
                        <?= count($_SESSION['keranjang']) === 0 ? 'disabled' : '' ?>>
                        Bayar & Cetak Nota
                    </button>
                </form>
            </div>
        </div>

    </div>
    </div>
    <!-- ===================== /TAB: KASIR ===================== -->

    <!-- ===================== TAB: RIWAYAT TRANSAKSI ===================== -->
    <div class="tab-page" id="tab-riwayat">
        <div class="stat-cards">
            <div class="stat-card">
                <div class="label">Total Transaksi</div>
                <div class="value"><?= (int) $statTrx ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Total Pendapatan</div>
                <div class="value">Rp <?= number_format($statPendapatan, 0, ',', '.') ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Transaksi Hari Ini</div>
                <div class="value"><?= (int) $statHariIni ?></div>
            </div>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">Riwayat Transaksi</h3>

            <?php if (count($riwayat_transaksi) === 0): ?>
                <div class="empty">Belum ada transaksi</div>
            <?php else: ?>
                <?php foreach ($riwayat_transaksi as $trx): ?>
                    <div class="history-item">
                        <div class="history-head">
                            <span>
                                <?= date("d/m/Y H:i", strtotime($trx['waktu_transaksi'])) ?>
                                <small style="color:var(--muted);font-weight:400;"> &middot; <?= htmlspecialchars($trx['kode_transaksi']) ?></small>
                            </span>
                            <span>Rp <?= number_format($trx['total_bayar'], 0, ',', '.') ?></span>
                        </div>
                        <div class="history-detail"><?= htmlspecialchars($trx['daftar_item']) ?></div>
                        <div style="margin-top:6px;">
                            <a href="cetak.php?id=<?= (int) $trx['id_transaksi'] ?>" class="btn btn-outline btn-sm" target="_blank">Lihat / Cetak Ulang Nota</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <!-- ===================== /TAB: RIWAYAT TRANSAKSI ===================== -->

</main>

<script>
// Perhitungan total & kembalian dilakukan langsung di JavaScript (real-time)
// agar tampilan responsif tanpa perlu reload halaman.
// Perhitungan final & penyimpanan tetap divalidasi ulang di proses_bayar.php (sisi server).

const subtotal = <?= (int) $subtotal ?>;
const inputDiskon = document.getElementById('inputDiskon');
const selectDiskonTipe = document.getElementById('selectDiskonTipe');
const inputBayar = document.getElementById('inputBayar');
const labelTotal = document.getElementById('labelTotal');
const labelKembali = document.getElementById('labelKembali');

function formatRupiah(angka) {
    angka = Math.max(0, Math.round(angka));
    return "Rp " + angka.toLocaleString('id-ID');
}

function hitungUlang() {
    let diskonNilai = parseFloat(inputDiskon.value) || 0;
    if (diskonNilai < 0) diskonNilai = 0;

    // Hitung nominal diskon dalam Rupiah, tergantung tipe yang dipilih (% atau Rp)
    let diskonRupiah = selectDiskonTipe.value === 'persen'
        ? subtotal * (diskonNilai / 100)
        : diskonNilai;

    if (diskonRupiah > subtotal) diskonRupiah = subtotal;

    const total = subtotal - diskonRupiah;
    const bayar = parseFloat(inputBayar.value) || 0;
    const kembali = bayar - total;

    labelTotal.textContent = formatRupiah(total);
    labelKembali.textContent = formatRupiah(kembali > 0 ? kembali : 0);
}

inputDiskon.addEventListener('input', hitungUlang);
selectDiskonTipe.addEventListener('change', hitungUlang);
inputBayar.addEventListener('input', hitungUlang);

// ================== LIVE SEARCH PRODUK (AJAX) ==================
// Saat pengguna mengetik di kolom pencarian, kirim request ke cari_produk.php
// tanpa reload halaman, lalu tampilkan hasilnya di dalam tabel.

const inputCari = document.getElementById('inputCariProduk');
const tabelHasil = document.getElementById('hasilPencarianProduk');
let timerDebounce = null;

function formatRupiahProduk(angka) {
    return "Rp " + Math.round(angka).toLocaleString('id-ID');
}

function escapeHtmlJS(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function tampilkanHasil(daftarProduk) {
    if (daftarProduk.length === 0) {
        const kataKunciKosong = inputCari.value.trim() === '';
        tabelHasil.innerHTML = `<tr><td colspan="5" class="empty">${
            kataKunciKosong ? 'Ketik di kolom pencarian untuk menampilkan produk...' : 'Produk tidak ditemukan'
        }</td></tr>`;
        return;
    }

    tabelHasil.innerHTML = daftarProduk.map(p => `
        <tr>
            <td>${escapeHtmlJS(p.kode_produk)}</td>
            <td>${escapeHtmlJS(p.nama_produk)}</td>
            <td>${formatRupiahProduk(p.harga)}</td>
            <td>${p.stok}</td>
            <td>
                <form method="post" action="index.php">
                    <input type="hidden" name="aksi" value="tambah">
                    <input type="hidden" name="id_produk" value="${p.id_produk}">
                    <button type="submit" class="btn btn-primary btn-sm" ${p.stok <= 0 ? 'disabled' : ''}>
                        ${p.stok <= 0 ? 'Habis' : '+ Tambah'}
                    </button>
                </form>
            </td>
        </tr>
    `).join('');
}

async function cariProduk(kataKunci) {
    try {
        const respon = await fetch('cari_produk.php?q=' + encodeURIComponent(kataKunci));
        const data = await respon.json();
        tampilkanHasil(data);
    } catch (err) {
        tabelHasil.innerHTML = '<tr><td colspan="5" class="empty">Terjadi kesalahan saat mencari produk</td></tr>';
    }
}

inputCari.addEventListener('input', () => {
    const kataKunci = inputCari.value.trim();

    // Debounce: tunggu 300ms setelah pengguna berhenti mengetik,
    // supaya tidak mengirim request ke server di setiap ketukan huruf.
    clearTimeout(timerDebounce);

    if (kataKunci === '') {
        tampilkanHasil([]); // kosongkan tabel lagi kalau input dihapus semua
        return;
    }

    timerDebounce = setTimeout(() => {
        cariProduk(kataKunci);
    }, 300);
});

// ================== SWITCH TAB (Kasir / Riwayat Transaksi) ==================
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-page').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
    });
});
</script>

</body>
</html>
