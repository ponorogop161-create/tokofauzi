
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";

wajib_kasir();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function kembaliKeTransaksi(string $pesan): void
{
    header("Location: index.php?pesan=" . urlencode($pesan));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$token = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_kasir']) ||
    !hash_equals($_SESSION['csrf_kasir'], $token)
) {
    kembaliKeTransaksi("Permintaan tidak valid. Silakan ulangi.");
}

$idUser = (int)($_SESSION['id_user'] ?? 0);
$idPelangganInput = $_POST['id_pelanggan'] ?? '';
$idPelanggan = $idPelangganInput === ''
    ? null
    : (int)$idPelangganInput;

$uangDiterima = (float)($_POST['uang_diterima'] ?? 0);
$cart = json_decode($_POST['cart_json'] ?? '', true);

if ($idUser <= 0) {
    kembaliKeTransaksi("Sesi kasir tidak valid. Silakan login kembali.");
}

if (!is_array($cart) || count($cart) === 0 || count($cart) > 100) {
    kembaliKeTransaksi("Keranjang belanja kosong atau tidak valid.");
}

if (!is_finite($uangDiterima) || $uangDiterima < 0) {
    kembaliKeTransaksi("Nominal pembayaran tidak valid.");
}

// Validasi pelanggan
if ($idPelanggan !== null) {
    $cekPelanggan = $conn->prepare(
        "SELECT id_pelanggan FROM tbl_pelanggan WHERE id_pelanggan = ?"
    );

    $cekPelanggan->bind_param("i", $idPelanggan);
    $cekPelanggan->execute();

    $pelangganAda = $cekPelanggan->get_result()->num_rows > 0;
    $cekPelanggan->close();

    if (!$pelangganAda) {
        kembaliKeTransaksi("Pelanggan tidak ditemukan.");
    }
}

try {
    $conn->begin_transaction();

    $jumlahProduk = [];
    $daftarProduk = [];
    $totalBelanja = 0;

    foreach ($cart as $item) {
        $idProduk = (int)($item['id'] ?? 0);
        $jumlah = (int)($item['jumlah'] ?? 0);

        if ($idProduk <= 0 || $jumlah <= 0) {
            throw new Exception("Data produk tidak valid.");
        }

        $jumlahProduk[$idProduk] = ($jumlahProduk[$idProduk] ?? 0) + $jumlah;

        if ($jumlahProduk[$idProduk] > 10000) {
            throw new Exception("Jumlah produk tidak valid.");
        }
    }

    // Kunci produk untuk mencegah stok terjual melebihi persediaan
    $cekProduk = $conn->prepare("
        SELECT id_produk, nama_produk, harga, stok
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
        $produk['subtotal'] = (float)$produk['harga'] * $jumlah;

        $totalBelanja += $produk['subtotal'];
        $daftarProduk[] = $produk;
    }

    $cekProduk->close();

    if ($totalBelanja <= 0) {
        throw new Exception("Total transaksi tidak valid.");
    }

    if ($uangDiterima < $totalBelanja) {
        throw new Exception("Uang pembayaran belum mencukupi.");
    }

    $kembalian = $uangDiterima - $totalBelanja;
    $tanggal = date('Y-m-d');

    // Simpan transaksi dan pembayaran
    $insertTransaksi = $conn->prepare("
        INSERT INTO tbl_transaksi
            (tanggal, id_pelanggan, total_belanja,
             uang_diterima, kembalian, id_user)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $insertTransaksi->bind_param(
        "sidddi",
        $tanggal,
        $idPelanggan,
        $totalBelanja,
        $uangDiterima,
        $kembalian,
        $idUser
    );

    $insertTransaksi->execute();
    $idTransaksi = $conn->insert_id;
    $insertTransaksi->close();

    // Simpan detail transaksi
    $insertDetail = $conn->prepare("
        INSERT INTO tbl_detail_transaksi
            (id_transaksi, id_produk, nama_produk, jumlah, harga, subtotal)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $updateStok = $conn->prepare("
        UPDATE tbl_produk
        SET stok = stok - ?
        WHERE id_produk = ? AND stok >= ?
    ");

    foreach ($daftarProduk as $produk) {
        $idProduk = (int)$produk['id_produk'];
        $namaProduk = $produk['nama_produk'];
        $jumlah = (int)$produk['jumlah'];
        $harga = (float)$produk['harga'];
        $subtotal = (float)$produk['subtotal'];

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

        $updateStok->bind_param("iii", $jumlah, $idProduk, $jumlah);
        $updateStok->execute();

        if ($updateStok->affected_rows !== 1) {
            throw new Exception("Gagal memperbarui stok " . $namaProduk . ".");
        }
    }

    $insertDetail->close();
    $updateStok->close();

    $conn->commit();

    if (function_exists('catat_log')) {
        catat_log(
            $conn,
            "Transaksi Kasir",
            "Kasir " . ($_SESSION['nama'] ?? '-') .
            " membuat transaksi #" . $idTransaksi
        );
    }

    unset($_SESSION['csrf_kasir']);

    header("Location: detail.php?id=" . $idTransaksi);
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    kembaliKeTransaksi($e->getMessage());
}
