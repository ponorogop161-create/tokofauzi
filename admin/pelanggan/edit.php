
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";
wajib_admin();

$id = (int)($_GET['id'] ?? 0);

$stmt = mysqli_prepare(
    $conn,
    "SELECT * FROM tbl_pelanggan WHERE id_pelanggan = ?"
);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pelanggan = mysqli_fetch_assoc($result);

if (!$pelanggan) {
    header("Location: index.php?pesan=" . urlencode("Pelanggan tidak ditemukan."));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_pelanggan'] ?? '');
    $kota = trim($_POST['kota'] ?? '');

    if ($nama === '' || $kota === '') {
        $error = "Semua kolom wajib diisi.";
    } else {
        $update = mysqli_prepare(
            $conn,
            "UPDATE tbl_pelanggan
             SET nama_pelanggan = ?, kota = ?
             WHERE id_pelanggan = ?"
        );

        mysqli_stmt_bind_param($update, "ssi", $nama, $kota, $id);

        if (mysqli_stmt_execute($update)) {
            header("Location: index.php?pesan=" . urlencode("Data pelanggan berhasil diperbarui."));
            exit;
        }

        $error = "Gagal memperbarui data pelanggan.";
    }
}

require_once "../header.php";
require_once "../sidebar.php";
?>

<div class="content">
    <div class="page-header">
        <div>
            <h1>Edit Pelanggan</h1>
            <p>Perbarui informasi pelanggan.</p>
        </div>
        <a href="index.php" class="btn btn-secondary">Kembali</a>
    </div>

    <div class="content-card form-card">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Nama Pelanggan</label>
                <input type="text" name="nama_pelanggan"
                       value="<?= e($_POST['nama_pelanggan'] ?? $pelanggan['nama_pelanggan']) ?>"
                       required>
            </div>

            <div class="form-group">
                <label>Kota</label>
                <input type="text" name="kota"
                       value="<?= e($_POST['kota'] ?? $pelanggan['kota']) ?>"
                       required>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php require_once "../../includes/footer.php"; ?>
