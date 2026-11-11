
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";
wajib_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$id = (int)($_POST['id'] ?? 0);

$stmt = mysqli_prepare(
    $conn,
    "SELECT id_pelanggan FROM tbl_pelanggan WHERE id_pelanggan = ?"
);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

if (!mysqli_stmt_get_result($stmt)->num_rows) {
    header("Location: index.php?pesan=" . urlencode("Pelanggan tidak ditemukan."));
    exit;
}

$cek = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total FROM tbl_transaksi WHERE id_pelanggan = ?"
);
mysqli_stmt_bind_param($cek, "i", $id);
mysqli_stmt_execute($cek);
$total = mysqli_fetch_assoc(mysqli_stmt_get_result($cek))['total'];

if ($total > 0) {
    header("Location: index.php?pesan=" . urlencode("Pelanggan tidak dapat dihapus karena memiliki riwayat transaksi."));
    exit;
}

$hapus = mysqli_prepare(
    $conn,
    "DELETE FROM tbl_pelanggan WHERE id_pelanggan = ?"
);
mysqli_stmt_bind_param($hapus, "i", $id);

$pesan = mysqli_stmt_execute($hapus)
    ? "Pelanggan berhasil dihapus."
    : "Pelanggan gagal dihapus.";

header("Location: index.php?pesan=" . urlencode($pesan));
exit;
