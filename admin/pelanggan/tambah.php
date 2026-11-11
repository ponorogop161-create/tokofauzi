
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";
wajib_admin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_pelanggan'] ?? '');
    $kota = trim($_POST['kota'] ?? '');

    if ($nama === '' || $kota === '') {
        $error = "Nama pelanggan dan kota wajib diisi.";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO tbl_pelanggan (nama_pelanggan, kota) VALUES (?, ?)"
        );

        mysqli_stmt_bind_param($stmt, "ss", $nama, $kota);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: index.php?pesan=" . urlencode("Pelanggan berhasil ditambahkan."));
            exit;
        }

        $error = "Gagal menambahkan pelanggan: " . mysqli_error($conn);
    }
}

require_once "../header.php";
require_once "../sidebar.php";
?>

<div class="content">
    <div class="page-header">
        <div>
            <h1>Tambah Pelanggan</h1>
            <p>Masukkan informasi pelanggan baru.</p>
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
                       value="<?= e($_POST['nama_pelanggan'] ?? '') ?>"
                       placeholder="Masukkan nama pelanggan" required>
            </div>

            <div class="form-group">
                <label>Kota</label>
                <input type="text" name="kota"
                       value="<?= e($_POST['kota'] ?? '') ?>"
                       placeholder="Masukkan kota" required>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Pelanggan</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php require_once "../../includes/footer.php"; ?>
