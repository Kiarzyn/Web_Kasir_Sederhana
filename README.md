# Panduan Instalasi — Kasir POS (PHP Native + MySQL/XAMPP)

## Struktur File

```
kasir_php/
├── database.sql       -> struktur & data awal database
├── koneksi.php         -> koneksi ke MySQL
├── index.php           -> halaman kasir (keranjang, hitung total)
├── produk.php           -> CRUD data produk
├── proses_bayar.php     -> logika simpan transaksi & kurangi stok
├── cetak.php             -> nota cetak thermal
└── style.css              -> tampilan (dipakai index.php & produk.php)
```

## Langkah 1 — Install & Jalankan XAMPP

1. Download XAMPP di https://www.apachefriends.org (jika belum ada).
2. Buka **XAMPP Control Panel**.
3. Klik **Start** pada modul **Apache** dan **MySQL**. Pastikan keduanya berwarna hijau.

## Langkah 2 — Salin Folder Proyek ke htdocs

1. Cari folder instalasi XAMPP Anda, biasanya di:
   - Windows: `C:\xampp\htdocs\`
   - macOS: `/Applications/XAMPP/htdocs/`
   - Linux: `/opt/lampp/htdocs/`
2. Buat folder baru bernama **`kasir_php`** di dalam `htdocs`.
3. Salin **semua 7 file** (`database.sql`, `koneksi.php`, `index.php`, `produk.php`, `proses_bayar.php`, `cetak.php`, `style.css`) ke dalam folder `htdocs/kasir_php/` tersebut.

Hasil akhirnya harus seperti ini:
```
C:\xampp\htdocs\kasir_php\database.sql
C:\xampp\htdocs\kasir_php\koneksi.php
C:\xampp\htdocs\kasir_php\index.php
C:\xampp\htdocs\kasir_php\produk.php
C:\xampp\htdocs\kasir_php\proses_bayar.php
C:\xampp\htdocs\kasir_php\cetak.php
C:\xampp\htdocs\kasir_php\style.css
```

## Langkah 3 — Membuat Database lewat phpMyAdmin

1. Buka browser, akses **http://localhost/phpmyadmin**
2. Klik menu **Import** di bagian atas.
3. Klik **Choose File** / **Pilih Berkas**, lalu pilih file **`database.sql`** dari folder proyek Anda.
4. Klik tombol **Go / Kirim** di bagian bawah halaman.
5. Tunggu sampai muncul pesan sukses berwarna hijau. Database bernama **`kasir_db`** beserta 3 tabel (`produk`, `transaksi`, `detail_transaksi`) dan 10 data produk sampel akan otomatis terbuat.

> **Alternatif cara manual (tanpa import file):**
> 1. Di phpMyAdmin, klik **New/Baru** di sidebar kiri.
> 2. Ketik nama database `kasir_db`, klik **Create/Buat**.
> 3. Klik tab **SQL**, lalu copy-paste seluruh isi file `database.sql`, klik **Go**.

## Langkah 4 — Cek Pengaturan Koneksi

Buka file `koneksi.php`. Untuk instalasi XAMPP standar (default), pengaturan berikut **biasanya sudah benar dan tidak perlu diubah**:

```php
$host     = "localhost";
$username = "root";
$password = "";
$database = "kasir_db";
```

Jika Anda pernah mengganti password MySQL root sebelumnya, sesuaikan nilai `$password`.

## Langkah 5 — Jalankan Aplikasi

Buka browser, akses:

```
http://localhost/kasir_php/index.php
```

Aplikasi kasir siap digunakan! Anda bisa:
- Mencari & menambahkan produk ke keranjang di halaman **Kasir**
- Mengelola data produk di halaman **Manajemen Produk** (`http://localhost/kasir_php/produk.php`)
- Melakukan pembayaran dan mencetak nota otomatis lewat `window.print()`

## Cara Kerja Singkat (Untuk Belajar)

| File | Fungsi |
|---|---|
| `koneksi.php` | Membuka koneksi `mysqli` ke database, dipakai (`require`) oleh semua file lain |
| `index.php` | Menampilkan produk, mengelola keranjang lewat `$_SESSION`, hitung total (real-time via JS, tapi divalidasi ulang di server) |
| `produk.php` | CRUD produk: tambah (`INSERT`), lihat (`SELECT`), edit (`UPDATE`), hapus (`DELETE`) — semua pakai *prepared statement* |
| `proses_bayar.php` | Validasi ulang total di server, simpan ke `transaksi` & `detail_transaksi`, kurangi `stok`, semuanya dibungkus `begin_transaction()`/`commit()`/`rollback()` agar data konsisten |
| `cetak.php` | Ambil data transaksi dari database berdasarkan `id`, tampilkan sebagai nota siap `window.print()` |

## Keamanan yang Sudah Diterapkan (Dasar)

- **Prepared Statements** (`$koneksi->prepare()` + `bind_param()`) di semua query yang melibatkan input pengguna → mencegah **SQL Injection**.
- **`htmlspecialchars()`** saat menampilkan data ke HTML → mencegah **XSS** dasar.
- Validasi tipe data (`(int)`, `(float)` cast) untuk input angka.
- Perhitungan total transaksi **dihitung ulang di server** (`proses_bayar.php`), bukan hanya percaya nilai dari form/JavaScript, supaya harga tidak bisa dimanipulasi pengguna.
- Transaksi database (`begin_transaction`, `commit`, `rollback`) memastikan stok & catatan transaksi tidak "nyangkut" setengah jalan bila terjadi error.

## Troubleshooting

| Masalah | Solusi |
|---|---|
| "Koneksi ke database gagal" | Pastikan modul **MySQL** di XAMPP Control Panel sudah **Start** (hijau) |
| Halaman blank/putih | Aktifkan error PHP: buka `php.ini` di XAMPP, set `display_errors = On`, restart Apache |
| Data produk tidak muncul | Pastikan `database.sql` berhasil di-import dan database bernama persis `kasir_db` |
| Port 80 sudah dipakai aplikasi lain | Ganti port Apache di XAMPP Control Panel > Config > `httpd.conf`, atau tutup aplikasi lain (Skype, IIS, dll) yang memakai port 80 |
