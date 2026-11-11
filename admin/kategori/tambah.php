
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";

wajib_admin();

$error = '';
$nama = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_kategori'] ?? '');

    if ($nama === '') {
        $error = "Nama kategori wajib diisi.";
    } else {
        // Periksa nama kategori duplikat
        $cek = $conn->prepare(
            "SELECT id_kategori
             FROM tbl_kategori
             WHERE nama_kategori = ?"
        );

        $cek->bind_param("s", $nama);
        $cek->execute();

        if ($cek->get_result()->num_rows > 0) {
            $error = "Kategori tersebut sudah tersedia.";
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO tbl_kategori (nama_kategori)
                 VALUES (?)"
            );

            $stmt->bind_param("s", $nama);

            if ($stmt->execute()) {
                if (function_exists('catat_log')) {
                    catat_log(
                        $conn,
                        "Tambah Kategori",
                        "Admin menambahkan kategori: " . $nama
                    );
                }

                header(
                    "Location: index.php?pesan=" .
                    urlencode("Kategori berhasil ditambahkan.")
                );
                exit;
            } else {
                $error = "Gagal menambahkan kategori: " . $stmt->error;
            }

            $stmt->close();
        }

        $cek->close();
    }
}

$judul = "Tambah Kategori";
require_once "../header.php";
?>

<div class="page-header">
    <div>
        <h1>Tambah Kategori</h1>
        <p class="muted">Tambahkan kategori produk baru ke TokoKu.</p>
    </div>

    <a href="index.php" class="btn btn-secondary">
        Kembali
    </a>
</div>

<div class="panel">

    <div class="panel-heading">
        <div>
            <h3>Formulir Kategori</h3>
            <p class="muted">
                Masukkan nama kategori yang ingin ditambahkan.
            </p>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <div class="form-group">
            <label for="nama_kategori">Nama Kategori</label>

            <input
                type="text"
                id="nama_kategori"
                name="nama_kategori"
                maxlength="100"
                placeholder="Contoh: Sepatu"
                required
                value="<?= e($nama) ?>"
            >
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                Simpan Kategori
            </button>

            <a href="index.php" class="btn btn-secondary">
                Batal
            </a>
        </div>

    </form>

</div>

<?php require_once "../../includes/footer.php"; ?>
