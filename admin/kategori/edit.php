
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";

wajib_admin();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: index.php?pesan=" . urlencode("ID kategori tidak valid."));
    exit;
}

// Ambil data kategori
$stmt = $conn->prepare(
    "SELECT * FROM tbl_kategori WHERE id_kategori = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();

$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    header("Location: index.php?pesan=" . urlencode("Kategori tidak ditemukan."));
    exit;
}

$error = '';
$nama = $data['nama_kategori'];

// Proses update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_kategori'] ?? '');

    if ($nama === '') {
        $error = "Nama kategori wajib diisi.";
    } else {
        // Periksa nama kategori yang sama
        $cek = $conn->prepare(
            "SELECT id_kategori
             FROM tbl_kategori
             WHERE nama_kategori = ?
             AND id_kategori != ?"
        );

        $cek->bind_param("si", $nama, $id);
        $cek->execute();

        if ($cek->get_result()->num_rows > 0) {
            $error = "Nama kategori sudah digunakan.";
        } else {
            $update = $conn->prepare(
                "UPDATE tbl_kategori
                 SET nama_kategori = ?
                 WHERE id_kategori = ?"
            );

            $update->bind_param("si", $nama, $id);

            if ($update->execute()) {
                if (function_exists('catat_log')) {
                    catat_log(
                        $conn,
                        "Edit Kategori",
                        "Admin mengubah kategori ID " . $id .
                        " dari " . $data['nama_kategori'] .
                        " menjadi " . $nama
                    );
                }

                header(
                    "Location: index.php?pesan=" .
                    urlencode("Kategori berhasil diperbarui.")
                );
                exit;
            } else {
                $error = "Gagal memperbarui kategori: " . $update->error;
            }

            $update->close();
        }

        $cek->close();
    }
}

$judul = "Edit Kategori";
require_once "../header.php";
?>

<div class="page-header">
    <div>
        <h1>Edit Kategori</h1>
        <p class="muted">Perbarui nama kategori produk TokoKu.</p>
    </div>

    <a href="index.php" class="btn btn-secondary">
        Kembali
    </a>
</div>

<div class="panel">

    <div class="panel-heading">
        <div>
            <h3>Formulir Edit Kategori</h3>
            <p class="muted">
                Ubah nama kategori sesuai kebutuhan.
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
                placeholder="Masukkan nama kategori"
                required
                value="<?= e($nama) ?>"
            >
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                Simpan Perubahan
            </button>

            <a href="index.php" class="btn btn-secondary">
                Batal
            </a>
        </div>

    </form>

</div>

<?php require_once "../../includes/footer.php"; ?>
