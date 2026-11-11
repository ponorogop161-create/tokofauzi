
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";
wajib_admin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_cabang'] ?? '');
    $kota = trim($_POST['kota'] ?? '');

    if ($nama === '' || $kota === '') {
        $error = "Semua kolom wajib diisi.";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO tbl_cabang (nama_cabang, kota) VALUES (?, ?)"
        );
        mysqli_stmt_bind_param($stmt, "ss", $nama, $kota);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: index.php?pesan=" . urlencode("Cabang berhasil ditambahkan."));
            exit;
        }

        $error = "Gagal menambahkan cabang.";
    }
}

require_once "../header.php";
require_once "../sidebar.php";
?>

<div class="content">
    <div class="page-header">
        <div>
            <h1>Tambah Cabang</h1>
            <p>Tambahkan lokasi cabang baru.</p>
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
                       value="<?= e($_POST['nama_cabang'] ?? '') ?>"
                       placeholder="Contoh: TokoKu Ponorogo" required>
            </div>

            <div class="form-group">
                <label>Kota</label>
                <input type="text" name="kota"
                       value="<?= e($_POST['kota'] ?? '') ?>"
                       placeholder="Masukkan kota" required>
            </div>

            <button class="btn btn-primary" type="submit">Simpan Cabang</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php require_once "../../includes/footer.php"; ?>
