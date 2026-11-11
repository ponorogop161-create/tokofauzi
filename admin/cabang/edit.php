
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";
wajib_admin();

$id = (int)($_GET['id'] ?? 0);

$stmt = mysqli_prepare(
    $conn,
    "SELECT * FROM tbl_cabang WHERE id_cabang = ?"
);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$cabang = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$cabang) {
    header("Location: index.php?pesan=" . urlencode("Cabang tidak ditemukan."));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_cabang'] ?? '');
    $kota = trim($_POST['kota'] ?? '');

    if ($nama === '' || $kota === '') {
        $error = "Semua kolom wajib diisi.";
    } else {
        $update = mysqli_prepare(
            $conn,
            "UPDATE tbl_cabang SET nama_cabang = ?, kota = ? WHERE id_cabang = ?"
        );
        mysqli_stmt_bind_param($update, "ssi", $nama, $kota, $id);

        if (mysqli_stmt_execute($update)) {
            header("Location: index.php?pesan=" . urlencode("Cabang berhasil diperbarui."));
            exit;
        }

        $error = "Gagal memperbarui cabang.";
    }
}

require_once "../header.php";
require_once "../sidebar.php";
?>

<div class="content">
    <div class="page-header">
        <div>
            <h1>Edit Cabang</h1>
            <p>Perbarui informasi cabang.</p>
        </div>
        <a href="index.php" class="btn btn-secondary">Kembali</a>
    </div>

    <div class="content-card form-card">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Nama Cabang</label>
                <input type="text" name="nama_cabang"
                       value="<?= e($_POST['nama_cabang'] ?? $cabang['nama_cabang']) ?>"
                       required>
            </div>

            <div class="form-group">
                <label>Kota</label>
                <input type="text" name="kota"
                       value="<?= e($_POST['kota'] ?? $cabang['kota']) ?>"
                       required>
            </div>

            <button class="btn btn-primary" type="submit">Simpan Perubahan</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php require_once "../../includes/footer.php"; ?>
