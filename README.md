# 🛒 TokoKu – Sistem Informasi Penjualan Toko

**TokoKu** merupakan aplikasi sistem informasi penjualan toko berbasis website yang dibangun menggunakan PHP Native dan MySQL/MariaDB. Aplikasi ini membantu pengelolaan produk, pelanggan, transaksi penjualan, pembayaran, serta laporan penjualan secara digital.

---

## 📌 1. Informasi Proyek

| Informasi | Keterangan |
|---|---|
| Nama Aplikasi | TokoKu |
| Jenis Aplikasi | Sistem Informasi Penjualan Toko |
| Platform | Website |
| Bahasa Pemrograman | PHP Native |
| Database | MySQL / MariaDB |
| Web Server | Apache (XAMPP) |
| Tahun | 2026 |

## 🛠️ 2. Teknologi yang Digunakan

- 🐘 PHP Native 8.2
- 🗄️ MySQL / MariaDB
- 🎨 HTML5 dan CSS3
- ⚡ JavaScript
- 📦 Bootstrap (jika digunakan pada halaman terkait)
- 🚀 XAMPP
- 💻 Visual Studio Code

## ✨ 3. Fitur Aplikasi

### 👨‍💼 Admin
- 📊 Dashboard admin.
- 📦 Mengelola data produk.
- 🏷️ Mengelola kategori produk.
- 👥 Mengelola data pelanggan.
- 🏢 Mengelola data cabang toko.
- 🔐 Mengelola akun pengguna.
- 💳 Mengelola kartu RFID pengguna.
- 🧾 Melihat dan mengelola transaksi.
- 🔎 Memverifikasi pembayaran QRIS.
- 📈 Melihat laporan penjualan.
- 🖨️ Mencetak laporan penjualan.
- 📋 Melihat aktivitas sistem.

### 🧑‍💻 Kasir
- 📊 Dashboard kasir.
- 🛍️ Melakukan transaksi penjualan.
- 👥 Memilih pelanggan.
- 📦 Memilih produk dan jumlah pembelian.
- 💰 Menghitung total belanja.
- 💵 Menghitung uang diterima dan kembalian.
- 🧾 Mencetak struk transaksi seperti minimarket.
- 📚 Melihat riwayat transaksi.
- 📈 Melihat laporan penjualan pribadi.
- 🔍 Memfilter transaksi berdasarkan tanggal dan pencarian.

### 🛒 Pembeli (User)
- 🏠 Dashboard pembeli.
- 🔎 Melihat katalog produk.
- 👟 Melihat detail produk.
- 🛍️ Menambahkan produk ke keranjang.
- ✏️ Mengubah jumlah produk.
- 🗑️ Menghapus produk dari keranjang.
- 📦 Melakukan checkout.
- 💳 Memilih metode pembayaran.
- 📋 Melihat daftar pesanan.
- 🔍 Melihat detail pesanan.
- 🖨️ Mencetak struk pesanan.

## 💳 4. Metode Pembayaran

TokoKu menyediakan tiga pilihan metode pembayaran:

| Ikon | Metode | Keterangan |
|---|---|---|
| 📱 | QRIS | Pembayaran menggunakan kode QR dan unggah bukti pembayaran untuk diverifikasi admin. |
| 🚚 | COD | Pembayaran dilakukan ketika barang diterima. |
| 🏪 | KASIR | Pembayaran langsung di kasir cabang toko. |

### 📌 Status Pembayaran

- ⏳ Belum Bayar
- 🔎 Menunggu Verifikasi
- ✅ Lunas

### 📦 Status Pesanan

- 🕒 Menunggu
- ⚙️ Diproses
- 🚚 Dikirim
- 🎉 Selesai
- ❌ Dibatalkan

## 📁 5. Struktur Folder

```text
tokoku/
│
├── admin/
│   ├── produk/
│   ├── kategori/
│   ├── pelanggan/
│   ├── cabang/
│   ├── pengguna/
│   ├── transaksi/
│   ├── laporan/
│   └── aktivitas/
│
├── kasir/
│   ├── transaksi/
│   ├── laporan/
│   └── includes/
│
├── user/
│   ├── produk/
│   ├── keranjang/
│   ├── checkout/
│   ├── pesanan/
│   └── includes/
│
├── config/
│   ├── koneksi.php
│   └── auth.php
│
├── assets/
│   ├── css/
│   ├── js/
│   ├── images/
│   └── uploads/
│       ├── produk/
│       └── pembayaran/
│
├── includes/
│
├── login.php
├── login_rfid.php
├── logout.php
├── index.php
└── README.md
```

## ⚙️ 6. Persyaratan Sistem

Sebelum menjalankan aplikasi, pastikan perangkat memiliki:

- 🖥️ Sistem operasi Windows.
- 🚀 XAMPP.
- 🐘 PHP versi 8.2 atau lebih baru.
- 🗄️ MySQL atau MariaDB.
- 🌐 Browser Google Chrome atau browser lainnya.
- 💻 Visual Studio Code (opsional).

## 🚀 7. Cara Instalasi

### 1️⃣ Menjalankan XAMPP

1. Buka XAMPP Control Panel.
2. Jalankan Apache.
3. Jalankan MySQL.

### 2️⃣ Menempatkan Folder Proyek

Letakkan folder proyek `tokoku` di direktori:

```text
D:\xampp\htdocs\tokoku
```

### 3️⃣ Menyiapkan Database

1. Buka browser.
2. Masuk ke phpMyAdmin:

   http://localhost/phpmyadmin

3. Gunakan database `db_tokoku` yang sudah dibuat sebelumnya.
4. Jika database belum tersedia, buat database tersebut dan impor file SQL proyek.
5. Pastikan tabel-tabel yang diperlukan sudah tersedia.

> ⚠️ Gunakan database proyek yang sudah ada. Tidak perlu membuat database baru jika `db_tokoku` sudah tersedia.

### 4️⃣ Konfigurasi Database

Buka file:

```text
config/koneksi.php
```

Sesuaikan konfigurasi database:

```php
<?php

$host = "localhost";
$username = "root";
$password = "";
$database = "db_tokoku";

$conn = mysqli_connect(
    $host,
    $username,
    $password,
    $database
);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");
```

### 5️⃣ Membuka Website

Akses alamat berikut melalui browser:

**http://localhost/tokoku/**

Website akan menampilkan halaman login TokoKu.

## 🔐 8. Hak Akses Pengguna

TokoKu memiliki tiga jenis hak akses:

| Role | Hak Akses |
|---|---|
| 👨‍💼 Admin | Mengelola data, pengguna, transaksi, pembayaran, dan laporan. |
| 🧑‍💻 Kasir | Melakukan transaksi penjualan, melihat riwayat, dan laporan penjualan. |
| 🛒 User | Melihat produk, mengelola keranjang, checkout, dan melihat pesanan. |

Setiap pengguna diarahkan ke dashboard sesuai dengan role akun setelah berhasil login.

## 🗄️ 9. Database

**Nama Database:** `db_tokoku`

Tabel utama yang digunakan:

| No. | Nama Tabel | Keterangan |
|---:|---|---|
| 1 | `tbl_users` | Data akun pengguna |
| 2 | `tbl_produk` | Data produk |
| 3 | `tbl_kategori` | Data kategori |
| 4 | `tbl_pelanggan` | Data pelanggan |
| 5 | `tbl_cabang` | Data cabang toko |
| 6 | `tbl_transaksi` | Data transaksi |
| 7 | `tbl_detail_transaksi` | Detail produk transaksi |
| 8 | `tbl_log` | Aktivitas sistem |
| 9 | `log_pelanggan` | Riwayat perubahan pelanggan |

Database juga menggunakan objek pendukung:

- 👁️ View
- ⚙️ Stored Procedure
- 🧮 Function
- 🔔 Trigger

## 🖼️ 10. Penyimpanan File

| Jenis File | Lokasi Penyimpanan |
|---|---|
| 📸 Foto produk | `assets/uploads/produk/` |
| 🧾 Bukti pembayaran | `assets/uploads/pembayaran/` |
| 📱 Gambar QRIS | `assets/images/` |

Pastikan folder penyimpanan tersedia dan dapat ditulisi oleh PHP.

## 🧪 11. Pengujian Aplikasi

Setelah instalasi selesai, lakukan pengujian berikut:

- [ ] 🚀 Jalankan Apache dan MySQL.
- [ ] 🔐 Login menggunakan akun Admin.
- [ ] 📊 Periksa dashboard admin.
- [ ] 📦 Tambah, ubah, dan hapus produk.
- [ ] 👥 Periksa data pelanggan dan kategori.
- [ ] 🏢 Periksa pengelolaan cabang.
- [ ] 🔐 Periksa pengelolaan akun pengguna.
- [ ] 🧑‍💻 Login menggunakan akun Kasir.
- [ ] 🛍️ Lakukan transaksi penjualan.
- [ ] 🧾 Periksa dan cetak struk transaksi.
- [ ] 📚 Periksa riwayat transaksi kasir.
- [ ] 📈 Periksa laporan penjualan kasir.
- [ ] 🛒 Login menggunakan akun User.
- [ ] 🔎 Periksa katalog produk.
- [ ] 🛍️ Coba fitur keranjang.
- [ ] 💳 Lakukan checkout dengan metode pembayaran.
- [ ] 📱 Uji pembayaran QRIS dan verifikasi admin.
- [ ] 📋 Periksa detail dan riwayat pesanan.
- [ ] 🚚 Uji perubahan status pesanan.
- [ ] 📈 Periksa laporan penjualan admin.

## 📞 12. Penutup

TokoKu dikembangkan sebagai aplikasi sistem informasi penjualan toko yang membantu proses pengelolaan data dan transaksi menjadi lebih mudah, terstruktur, dan efisien.

---

<div align="center">

### 🛒 TOKOKU

**Sistem Informasi Penjualan Toko**

*Modern • Responsive • User Friendly*

© 2026 TokoKu. All Rights Reserved.

</div>
