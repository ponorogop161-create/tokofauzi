
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";

wajib_admin();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_produk'])) {
    $_SESSION['csrf_produk'] = bin2hex(random_bytes(32));
}

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: index.php?pesan=" . urlencode("ID produk tidak valid."));
    exit;
}

// Ambil data produk
$stmt = $conn->prepare(
    "SELECT id_produk, nama_produk, foto
     FROM tbl_produk
     WHERE id_produk = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$produk = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$produk) {
    header("Location: index.php?pesan=" . urlencode("Produk tidak ditemukan."));
    exit;
}

$error = '';

// Proses penghapusan setelah konfirmasi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_produk'], $token)) {
        $error = "Permintaan tidak valid. Silakan coba kembali.";
    } else {

        // Periksa apakah produk pernah digunakan dalam transaksi
        $cek = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM tbl_detail_transaksi
             WHERE id_produk = ?"
        );
        $cek->bind_param("i", $id);
        $cek->execute();
        $jumlahTransaksi = (int)$cek->get_result()
            ->fetch_assoc()['total'];
        $cek->close();

        if ($jumlahTransaksi > 0) {
            $error = "Produk tidak dapat dihapus karena sudah digunakan dalam transaksi.";
        } else {

            $hapus = $conn->prepare(
                "DELETE FROM tbl_produk WHERE id_produk = ?"
            );
            $hapus->bind_param("i", $id);

            if ($hapus->execute() && $hapus->affected_rows > 0) {

                // Hapus foto setelah data produk berhasil dihapus
                if (
                    !empty($produk['foto']) &&
                    strpos($produk['foto'], 'assets/uploads/produk/') === 0
                ) {
                    $folderFoto = dirname(__DIR__, 2)
                        . DIRECTORY_SEPARATOR
                        . "assets"
                        . DIRECTORY_SEPARATOR
                        . "uploads"
                        . DIRECTORY_SEPARATOR
                        . "produk"
                        . DIRECTORY_SEPARATOR;

                    $pathFoto = $folderFoto . basename($produk['foto']);

                    if (is_file($pathFoto)) {
                        unlink($pathFoto);
                    }
                }

                if (function_exists('catat_log')) {
                    catat_log(
                        $conn,
                        "Hapus Produk",
                        "Admin menghapus produk ID " . $id .
                        ": " . $produk['nama_produk']
                    );
                }

                $hapus->close();

                unset($_SESSION['csrf_produk']);

                header(
                    "Location: index.php?pesan=" .
                    urlencode("Produk dan foto berhasil dihapus.")
                );
                exit;

            } else {
                $error = "Gagal menghapus produk: " . $hapus->error;
            }

            $hapus->close();
        }
    }
}

$judul = "Konfirmasi Hapus Produk";
require_once "../header.php";
?>

<div class="page-header">
    <div>
        <h1>Hapus Produk</h1>
        <p class="muted">Konfirmasi penghapusan data produk.</p>
    </div>

    <a href="index.php" class="btn btn-secondary">Kembali</a>
</div>

<div class="panel">
    <div class="panel-heading">
        <div>
            <h3>Konfirmasi Penghapusan</h3>
            <p class="muted">Periksa kembali produk yang akan dihapus.</p>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="form-group">
        <label>Nama Produk</label>
        <p><strong><?= e($produk['nama_produk']) ?></strong></p>
    </div>

    <?php if (!empty($produk['foto'])): ?>
        <div class="form-group">
            <label>Foto Produk</label>
            <img
                src="<?= BASE_URL . e($produk['foto']) ?>"
                alt="<?= e($produk['nama_produk']) ?>"
                class="product-preview"
            >
        </div>
    <?php endif; ?>

    <p>
        Apakah kamu yakin ingin menghapus produk ini?
        Foto produk juga akan dihapus dari folder penyimpanan.
    </p>

    <form method="POST">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= e($_SESSION['csrf_produk']) ?>"
        >

        <div class="form-actions">
            <button type="submit" class="btn btn-danger">
                Ya, Hapus Produk
            </button>

            <a href="index.php" class="btn btn-secondary">
                Batal
            </a>
        </div>
    </form>
</div>

<?php require_once "../../includes/footer.php"; ?>
