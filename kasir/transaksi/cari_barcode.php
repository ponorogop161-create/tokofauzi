
<?php

require_once __DIR__ . "/../../config/koneksi.php";
require_once __DIR__ . "/../../config/auth.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

wajib_kasir();

header("Content-Type: application/json; charset=UTF-8");

function responseJSON($success, $message, $product = null) {

    echo json_encode([
        "success" => $success,
        "message" => $message,
        "product" => $product
    ]);

    exit;
}

$barcode = trim($_GET['barcode'] ?? '');

if ($barcode === '') {
    responseJSON(false, "Barcode tidak boleh kosong.");
}

$stmt = $conn->prepare("
    SELECT
        id_produk,
        nama_produk,
        harga,
        stok,
        barcode
    FROM tbl_produk
    WHERE barcode = ?
    LIMIT 1
");

if (!$stmt) {
    responseJSON(false, "Gagal menyiapkan pencarian produk.");
}

$stmt->bind_param("s", $barcode);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    responseJSON(false, "Barcode tidak terdaftar di TokoKu.");

}

$product = $result->fetch_assoc();

$stmt->close();

if ((int)$product['stok'] <= 0) {
    responseJSON(false, "Stok produk habis.");
}

responseJSON(
    true,
    "Produk ditemukan.",
    [
        "id_produk" => (int)$product['id_produk'],
        "nama_produk" => $product['nama_produk'],
        "harga" => (float)$product['harga'],
        "stok" => (int)$product['stok'],
        "barcode" => $product['barcode']
    ]
);
