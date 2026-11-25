
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

wajib_kasir();

// =====================================
// FUNGSI REDIRECT
// =====================================

function kembaliKeTransaksi(string $pesan): void
{
    header("Location: index.php?pesan=" . urlencode($pesan));
    exit;
}

// =====================================
// VALIDASI REQUEST
// =====================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

// =====================================
// VALIDASI CSRF TOKEN
// =====================================

$token = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $token)
) {
    kembaliKeTransaksi("Permintaan tidak valid. Silakan ulangi.");
}

// =====================================
// AMBIL DATA FORM
// =====================================

$idUser = (int)($_SESSION['id_user'] ?? 0);

$idPelangganInput = $_POST['id_pelanggan'] ?? '';

$idPelanggan = $idPelangganInput === ''
    ? null
    : (int)$idPelangganInput;

$metodePembayaran = strtolower(
    trim($_POST['metode_pembayaran'] ?? 'tunai')
);

$uangDiterima = (float)($_POST['uang_diterima'] ?? 0);

$qrisKonfirmasi = $_POST['qris_konfirmasi'] ?? '';

$cart = json_decode($_POST['cart_json'] ?? '', true);

// =====================================
// VALIDASI DATA
// =====================================

if ($idUser <= 0) {
    kembaliKeTransaksi("Sesi kasir tidak valid. Silakan login kembali.");
}

if (!in_array($metodePembayaran, ['tunai', 'qris'], true)) {
    kembaliKeTransaksi("Metode pembayaran tidak valid.");
}

if (!is_array($cart) || count($cart) === 0 || count($cart) > 100) {
    kembaliKeTransaksi("Keranjang belanja kosong atau tidak valid.");
}

if (!is_finite($uangDiterima) || $uangDiterima < 0) {
    kembaliKeTransaksi("Nominal pembayaran tidak valid.");
}

// =====================================
// VALIDASI PELANGGAN
// =====================================

if ($idPelanggan !== null) {

    $cekPelanggan = $conn->prepare("
        SELECT id_pelanggan
        FROM tbl_pelanggan
        WHERE id_pelanggan = ?
    ");

    $cekPelanggan->bind_param("i", $idPelanggan);
    $cekPelanggan->execute();

    $pelangganAda = $cekPelanggan->get_result()->num_rows > 0;

    $cekPelanggan->close();

    if (!$pelangganAda) {
        kembaliKeTransaksi("Pelanggan tidak ditemukan.");
    }
}

// =====================================
// PROSES TRANSAKSI
// =====================================

$transaksiDimulai = false;

try {

    $conn->begin_transaction();
    $transaksiDimulai = true;

    $jumlahProduk = [];
    $daftarProduk = [];
    $totalBelanja = 0;

    // =================================
    // VALIDASI KERANJANG
    // =================================

    foreach ($cart as $item) {

        $idProduk = (int)($item['id'] ?? 0);
        $jumlah = (int)($item['jumlah'] ?? 0);

        if ($idProduk <= 0 || $jumlah <= 0) {
            throw new Exception("Data produk tidak valid.");
        }

        $jumlahProduk[$idProduk] =
            ($jumlahProduk[$idProduk] ?? 0) + $jumlah;

        if ($jumlahProduk[$idProduk] > 10000) {
            throw new Exception("Jumlah produk tidak valid.");
        }
    }

    // =================================
    // CEK STOK DAN HARGA PRODUK
    // =================================

    $cekProduk = $conn->prepare("
        SELECT
            id_produk,
            nama_produk,
            harga,
            stok
        FROM tbl_produk
        WHERE id_produk = ?
        FOR UPDATE
    ");

    foreach ($jumlahProduk as $idProduk => $jumlah) {

        $cekProduk->bind_param("i", $idProduk);
        $cekProduk->execute();

        $produk = $cekProduk->get_result()->fetch_assoc();

        if (!$produk) {
            throw new Exception("Produk tidak ditemukan.");
        }

        if ((int)$produk['stok'] < $jumlah) {
            throw new Exception(
                "Stok " . $produk['nama_produk'] . " tidak mencukupi."
            );
        }

        $produk['jumlah'] = $jumlah;

        $produk['subtotal'] =
            (float)$produk['harga'] * $jumlah;

        $totalBelanja += $produk['subtotal'];

        $daftarProduk[] = $produk;
    }

    $cekProduk->close();

    if ($totalBelanja <= 0) {
        throw new Exception("Total transaksi tidak valid.");
    }

    // =================================
    // VALIDASI PEMBAYARAN
    // =================================

    if ($metodePembayaran === 'tunai') {

        if ($uangDiterima < $totalBelanja) {
            throw new Exception("Uang pembayaran belum mencukupi.");
        }

        $kembalian = $uangDiterima - $totalBelanja;

    } else {

        // QRIS wajib dikonfirmasi kasir
        if ($qrisKonfirmasi !== '1') {
            throw new Exception(
                "Konfirmasi pembayaran QRIS terlebih dahulu."
            );
        }

        // QRIS tidak menggunakan uang tunai atau kembalian
        $uangDiterima = $totalBelanja;
        $kembalian = 0;
    }

    $tanggal = date('Y-m-d');

    $statusPembayaran = 'lunas';

    // =================================
    // SIMPAN TRANSAKSI
    // =================================

    $insertTransaksi = $conn->prepare("
        INSERT INTO tbl_transaksi (
            tanggal,
            id_pelanggan,
            total_belanja,
            uang_diterima,
            kembalian,
            id_user,
            metode_pembayaran,
            status_pembayaran
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $insertTransaksi->bind_param(
        "sidddiss",
        $tanggal,
        $idPelanggan,
        $totalBelanja,
        $uangDiterima,
        $kembalian,
        $idUser,
        $metodePembayaran,
        $statusPembayaran
    );

    $insertTransaksi->execute();

    $idTransaksi = $conn->insert_id;

    $insertTransaksi->close();

    if ($idTransaksi <= 0) {
        throw new Exception("Gagal menyimpan transaksi.");
    }

    // =================================
    // SIMPAN DETAIL TRANSAKSI
    // =================================

    $insertDetail = $conn->prepare("
        INSERT INTO tbl_detail_transaksi (
            id_transaksi,
            id_produk,
            nama_produk,
            jumlah,
            harga,
            subtotal
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    // =================================
    // KURANGI STOK PRODUK
    // =================================

    $updateStok = $conn->prepare("
        UPDATE tbl_produk
        SET stok = stok - ?
        WHERE id_produk = ?
        AND stok >= ?
    ");

    foreach ($daftarProduk as $produk) {

        $idProduk = (int)$produk['id_produk'];
        $namaProduk = $produk['nama_produk'];
        $jumlah = (int)$produk['jumlah'];
        $harga = (float)$produk['harga'];
        $subtotal = (float)$produk['subtotal'];

        // Simpan detail
        $insertDetail->bind_param(
            "iisidd",
            $idTransaksi,
            $idProduk,
            $namaProduk,
            $jumlah,
            $harga,
            $subtotal
        );

        $insertDetail->execute();

        // Kurangi stok
        $updateStok->bind_param(
            "iii",
            $jumlah,
            $idProduk,
            $jumlah
        );

        $updateStok->execute();

        if ($updateStok->affected_rows !== 1) {
            throw new Exception(
                "Gagal memperbarui stok " . $namaProduk . "."
            );
        }
    }

    $insertDetail->close();
    $updateStok->close();

    // =================================
    // SIMPAN TRANSAKSI
    // =================================

    $conn->commit();
    $transaksiDimulai = false;

    // =================================
    // CATAT LOG
    // =================================

    if (function_exists('catat_log')) {

        catat_log(
            $conn,
            "Transaksi Kasir",
            "Kasir " . ($_SESSION['nama'] ?? '-') .
            " membuat transaksi #" . $idTransaksi .
            " dengan pembayaran " . strtoupper($metodePembayaran)
        );

    }

    // =================================
    // HAPUS TOKEN CSRF LAMA
    // =================================

    unset($_SESSION['csrf_token']);

    // =================================
    // REDIRECT KE DETAIL TRANSAKSI
    // =================================

    header("Location: detail.php?id=" . $idTransaksi);
    exit;

} catch (Throwable $e) {

    if ($transaksiDimulai) {
        $conn->rollback();
    }

    kembaliKeTransaksi($e->getMessage());
}
