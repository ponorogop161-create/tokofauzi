
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";
wajib_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$id = (int)($_POST['id'] ?? 0);

$cek = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total FROM tbl_cabang WHERE id_cabang = ?"
);
mysqli_stmt_bind_param($cek, "i", $id);
mysqli_stmt_execute($cek);
$ada = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($cek))['total'];

if (!$ada) {
    header("Location: index.php?pesan=" . urlencode("Cabang tidak ditemukan."));
    exit;
}

$stmt = mysqli_prepare(
    $conn,
    "DELETE FROM tbl_cabang WHERE id_cabang = ?"
);
mysqli_stmt_bind_param($stmt, "i", $id);

if (mysqli_stmt_execute($stmt)) {
    $pesan = "Cabang berhasil dihapus.";
} else {
    $pesan = "Cabang tidak dapat dihapus. Periksa apakah masih digunakan oleh data lain.";
}

header("Location: index.php?pesan=" . urlencode($pesan));
exit;
