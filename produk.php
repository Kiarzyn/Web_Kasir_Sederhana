<?php
/**
 * produk.php
 * Halaman CRUD (Create, Read, Delete, dan Update sederhana) untuk data produk.
 */

require 'koneksi.php';
$pesan = "";
$error = "";

// ================== TAMBAH PRODUK ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'tambah') {
    $kode  = trim($_POST['kode_produk']);
    $nama  = trim($_POST['nama_produk']);
    $harga = (int) $_POST['harga'];
    $stok  = (int) $_POST['stok'];

    if ($kode === '' || $nama === '' || $harga < 0 || $stok < 0) {
        $error = "Data tidak boleh kosong dan harga/stok tidak boleh negatif.";
    } else {
        // Cek apakah kode produk sudah ada (prepared statement)
        $cek = $koneksi->prepare("SELECT id_produk FROM produk WHERE kode_produk = ?");
        $cek->bind_param("s", $kode);
        $cek->execute();
        $cek->store_result();

        if ($cek->num_rows > 0) {
            $error = "Kode produk '$kode' sudah digunakan. Gunakan kode lain.";
        } else {
            $stmt = $koneksi->prepare(
                "INSERT INTO produk (kode_produk, nama_produk, harga, stok) VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("ssii", $kode, $nama, $harga, $stok);
            if ($stmt->execute()) {
                $pesan = "Produk '$nama' berhasil ditambahkan.";
            } else {
                $error = "Gagal menambahkan produk: " . $koneksi->error;
            }
            $stmt->close();
        }
        $cek->close();
    }
}

// ================== EDIT PRODUK ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'edit') {
    $id    = (int) $_POST['id_produk'];
    $kode  = trim($_POST['kode_produk']);
    $nama  = trim($_POST['nama_produk']);
    $harga = (int) $_POST['harga'];
    $stok  = (int) $_POST['stok'];

    if ($kode === '' || $nama === '' || $harga < 0 || $stok < 0) {
        $error = "Data tidak boleh kosong dan harga/stok tidak boleh negatif.";
    } else {
        // Pastikan kode tidak dipakai produk lain
        $cek = $koneksi->prepare("SELECT id_produk FROM produk WHERE kode_produk = ? AND id_produk != ?");
        $cek->bind_param("si", $kode, $id);
        $cek->execute();
        $cek->store_result();

        if ($cek->num_rows > 0) {
            $error = "Kode produk '$kode' sudah digunakan produk lain.";
        } else {
            $stmt = $koneksi->prepare(
                "UPDATE produk SET kode_produk = ?, nama_produk = ?, harga = ?, stok = ? WHERE id_produk = ?"
            );
            $stmt->bind_param("ssiii", $kode, $nama, $harga, $stok, $id);
            if ($stmt->execute()) {
                $pesan = "Produk '$nama' berhasil diperbarui.";
            } else {
                $error = "Gagal memperbarui produk: " . $koneksi->error;
            }
            $stmt->close();
        }
        $cek->close();
    }
}

// ================== HAPUS PRODUK ==================
if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];

    $stmt = $koneksi->prepare("DELETE FROM produk WHERE id_produk = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $pesan = "Produk berhasil dihapus.";
    } else {
        // Kemungkinan gagal karena produk masih terpakai di riwayat transaksi (FK RESTRICT)
        $error = "Produk tidak bisa dihapus karena sudah pernah terjual (ada di riwayat transaksi).";
    }
    $stmt->close();

    header("Location: produk.php?" . ($pesan ? "pesan=" . urlencode($pesan) : "error=" . urlencode($error)));
    exit;
}

if (isset($_GET['pesan'])) $pesan = $_GET['pesan'];
if (isset($_GET['error'])) $error = $_GET['error'];

// ================== AMBIL DATA PRODUK UNTUK DIEDIT (jika ada) ==================
$produk_edit = null;
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $stmt = $koneksi->prepare("SELECT * FROM produk WHERE id_produk = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $produk_edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ================== AMBIL SEMUA PRODUK (dengan pencarian) ==================
$kata_kunci = isset($_GET['cari']) ? trim($_GET['cari']) : '';

if ($kata_kunci !== '') {
    $stmt = $koneksi->prepare(
        "SELECT * FROM produk WHERE nama_produk LIKE CONCAT('%',?,'%') OR kode_produk LIKE CONCAT('%',?,'%') ORDER BY nama_produk ASC"
    );
    $stmt->bind_param("ss", $kata_kunci, $kata_kunci);
    $stmt->execute();
    $semua_produk = $stmt->get_result();
} else {
    $semua_produk = $koneksi->query("SELECT * FROM produk ORDER BY nama_produk ASC");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manajemen Produk - Kasir POS</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header class="topbar">
    <h1>Web Kasir Sederhana</h1>
    <nav>
        <a href="index.php" class="btn btn-nav">Kasir</a>
        <a href="produk.php" class="btn btn-nav active">Manajemen Produk</a>
    </nav>
</header>

<main class="container">

    <?php if ($pesan): ?><div class="alert alert-sukses"><?= htmlspecialchars($pesan) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="grid-produk">

        <!-- ============ FORM TAMBAH / EDIT PRODUK ============ -->
        <div class="card">
            <h3><?= $produk_edit ? 'Edit Produk' : 'Tambah Produk Baru' ?></h3>

            <form method="post" action="produk.php">
                <input type="hidden" name="aksi" value="<?= $produk_edit ? 'edit' : 'tambah' ?>">
                <?php if ($produk_edit): ?>
                    <input type="hidden" name="id_produk" value="<?= (int) $produk_edit['id_produk'] ?>">
                <?php endif; ?>

                <div class="form-row">
                    <label>Kode Produk</label>
                    <input type="text" name="kode_produk" required maxlength="20"
                           value="<?= $produk_edit ? htmlspecialchars($produk_edit['kode_produk']) : '' ?>"
                           placeholder="Contoh: P011">
                </div>

                <div class="form-row">
                    <label>Nama Produk</label>
                    <input type="text" name="nama_produk" required maxlength="100"
                           value="<?= $produk_edit ? htmlspecialchars($produk_edit['nama_produk']) : '' ?>"
                           placeholder="Contoh: Susu Kotak">
                </div>

                <div class="form-inline">
                    <div class="form-row">
                        <label>Harga (Rp)</label>
                        <input type="number" name="harga" required min="0"
                               value="<?= $produk_edit ? (int) $produk_edit['harga'] : '' ?>">
                    </div>
                    <div class="form-row">
                        <label>Stok</label>
                        <input type="number" name="stok" required min="0"
                               value="<?= $produk_edit ? (int) $produk_edit['stok'] : '' ?>">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-block">
                        <?= $produk_edit ? 'Simpan Perubahan' : 'Tambah Produk' ?>
                    </button>
                    <?php if ($produk_edit): ?>
                        <a href="produk.php" class="btn btn-outline btn-block">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- ============ DAFTAR PRODUK ============ -->
        <div class="card">
            <div class="flex-between">
                <h3>Daftar Produk</h3>
            </div>

            <form method="get" action="produk.php" class="search-form">
                <input type="text" name="cari" placeholder="Cari produk..." value="<?= htmlspecialchars($kata_kunci) ?>">
                <button type="submit" class="btn btn-outline">Cari</button>
                <?php if ($kata_kunci !== ''): ?><a href="produk.php" class="btn btn-outline">Reset</a><?php endif; ?>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Harga</th>
                            <th>Stok</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($semua_produk->num_rows === 0): ?>
                        <tr><td colspan="5" class="empty">Belum ada produk</td></tr>
                    <?php else: ?>
                        <?php while ($row = $semua_produk->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['kode_produk']) ?></td>
                                <td><?= htmlspecialchars($row['nama_produk']) ?></td>
                                <td>Rp <?= number_format($row['harga'], 0, ',', '.') ?></td>
                                <td><?= (int) $row['stok'] ?></td>
                                <td class="aksi-cell">
                                    <a href="produk.php?edit=<?= (int) $row['id_produk'] ?>" class="btn btn-outline btn-sm">Edit</a>
                                    <a href="produk.php?hapus=<?= (int) $row['id_produk'] ?>"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Yakin ingin menghapus produk ini?');">Hapus</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

</body>
</html>
