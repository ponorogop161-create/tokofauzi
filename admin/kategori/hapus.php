
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";
wajib_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$id = (int)($_POST['id'] ?? 0);

$cek = $conn->prepare("SELECT id_produk FROM tbl_produk WHERE id_kategori = ? LIMIT 1");
$cek->bind_param("i", $id);
$cek->execute();

if ($cek->get_result()->num_rows > 0) {
    header("Location: index.php?pesan=" . urlencode("Kategori tidak dapat dihapus karena masih digunakan produk."));
    exit;
}

$stmt = $conn->prepare("DELETE FROM tbl_kategori WHERE id_kategori = ?");
$stmt->bind_param("i", $id);

$pesan = $stmt->execute() && $stmt->affected_rows > 0
    ? "Kategori berhasil dihapus."
    : "Kategori gagal dihapus atau tidak ditemukan.";

header("Location: index.php?pesan=" . urlencode($pesan));
exit;
