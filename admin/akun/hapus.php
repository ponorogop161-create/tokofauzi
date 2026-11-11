
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";
wajib_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    header("Location: index.php?pesan=" . urlencode("ID akun tidak valid."));
    exit;
}

if ($id === (int)$_SESSION['id_user']) {
    header("Location: index.php?pesan=" . urlencode("Anda tidak dapat menghapus akun sendiri."));
    exit;
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT role FROM tbl_users WHERE id_user = ?"
);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$akun = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$akun) {
    header("Location: index.php?pesan=" . urlencode("Akun tidak ditemukan."));
    exit;
}

if ($akun['role'] === 'admin') {
    $result = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total
         FROM tbl_users WHERE role = 'admin'"
    );
    $totalAdmin = (int)mysqli_fetch_assoc($result)['total'];

    if ($totalAdmin <= 1) {
        header("Location: index.php?pesan=" .
            urlencode("Admin terakhir tidak dapat dihapus."));
        exit;
    }
}

$hapus = mysqli_prepare(
    $conn,
    "DELETE FROM tbl_users WHERE id_user = ?"
);
mysqli_stmt_bind_param($hapus, "i", $id);

if (mysqli_stmt_execute($hapus)) {
    $pesan = "Akun berhasil dihapus.";
} else {
    $pesan = "Akun gagal dihapus.";
}

header("Location: index.php?pesan=" . urlencode($pesan));
exit;
