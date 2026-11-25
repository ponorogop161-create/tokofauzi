
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";

wajib_admin();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: index.php?pesan=" . urlencode("ID produk tidak valid."));
    exit;
}

// =====================================
// AMBIL DATA PRODUK
// =====================================

$stmt = $conn->prepare("
    SELECT *
    FROM tbl_produk
    WHERE id_produk = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

$data = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$data) {
    header("Location: index.php?pesan=" . urlencode("Produk tidak ditemukan."));
    exit;
}

// =====================================
// DATA AWAL
// =====================================

$error = '';

$nama = $data['nama_produk'];
$id_kategori = $data['id_kategori'];
$harga = $data['harga'];
$stok = $data['stok'];
$barcode = $data['barcode'] ?? '';

// =====================================
// FOLDER FOTO
// =====================================

$folderFoto = dirname(__DIR__, 2)
    . DIRECTORY_SEPARATOR
    . "assets"
    . DIRECTORY_SEPARATOR
    . "uploads"
    . DIRECTORY_SEPARATOR
    . "produk"
    . DIRECTORY_SEPARATOR;

if (!is_dir($folderFoto)) {
    if (!mkdir($folderFoto, 0775, true) && !is_dir($folderFoto)) {
        die("Folder upload tidak dapat dibuat: " . $folderFoto);
    }
}

if (!is_writable($folderFoto)) {
    die("Folder upload tidak memiliki izin tulis: " . $folderFoto);
}

// =====================================
// PROSES UPDATE PRODUK
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nama = trim($_POST['nama_produk'] ?? '');
    $id_kategori = (int)($_POST['id_kategori'] ?? 0);
    $harga = (float)($_POST['harga'] ?? 0);
    $stok = (int)($_POST['stok'] ?? 0);
    $barcode = trim($_POST['barcode'] ?? '');

    if (
        $nama === '' ||
        $id_kategori <= 0 ||
        $harga <= 0 ||
        $stok < 0 ||
        $barcode === ''
    ) {

        $error = "Periksa kembali data produk.";

    } elseif (!preg_match('/^[0-9]{8,20}$/', $barcode)) {

        $error = "Barcode harus berupa angka dengan panjang 8 sampai 20 digit.";

    } else {

        // Cek kategori
        $cek = $conn->prepare("
            SELECT id_kategori
            FROM tbl_kategori
            WHERE id_kategori = ?
        ");

        $cek->bind_param("i", $id_kategori);
        $cek->execute();

        $kategoriAda = $cek->get_result()->num_rows > 0;

        $cek->close();

        if (!$kategoriAda) {

            $error = "Kategori tidak ditemukan.";

        } else {

            // Cek barcode milik produk lain
            $cekBarcode = $conn->prepare("
                SELECT id_produk
                FROM tbl_produk
                WHERE barcode = ?
                AND id_produk != ?
                LIMIT 1
            ");

            $cekBarcode->bind_param("si", $barcode, $id);
            $cekBarcode->execute();

            $barcodeAda = $cekBarcode->get_result()->num_rows > 0;

            $cekBarcode->close();

            if ($barcodeAda) {

                $error = "Barcode sudah digunakan oleh produk lain.";

            } else {

                $fotoBaru = null;
                $pathFotoBaru = null;

                // =====================================
                // UPLOAD FOTO BARU
                // =====================================

                if (
                    isset($_FILES['foto']) &&
                    $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE
                ) {

                    if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {

                        $error = "Terjadi kesalahan saat mengunggah foto.";

                    } elseif ($_FILES['foto']['size'] > 2 * 1024 * 1024) {

                        $error = "Ukuran foto maksimal 2 MB.";

                    } else {

                        $finfo = new finfo(FILEINFO_MIME_TYPE);

                        $mime = $finfo->file(
                            $_FILES['foto']['tmp_name']
                        );

                        $formatFoto = [
                            'image/jpeg' => 'jpg',
                            'image/png'  => 'png',
                            'image/webp' => 'webp'
                        ];

                        if (!isset($formatFoto[$mime])) {

                            $error = "Format foto harus JPG, PNG, atau WEBP.";

                        } else {

                            $namaFile = bin2hex(random_bytes(16))
                                . "." . $formatFoto[$mime];

                            $tujuan = $folderFoto . $namaFile;

                            if (
                                move_uploaded_file(
                                    $_FILES['foto']['tmp_name'],
                                    $tujuan
                                )
                            ) {

                                $fotoBaru = "assets/uploads/produk/" . $namaFile;
                                $pathFotoBaru = $tujuan;

                            } else {

                                $error = "Foto gagal diunggah. Periksa izin folder dan konfigurasi PHP.";

                            }

                        }

                    }

                }

                // =====================================
                // UPDATE DATABASE
                // =====================================

                if ($error === '') {

                    if ($fotoBaru !== null) {

                        $update = $conn->prepare("
                            UPDATE tbl_produk
                            SET
                                nama_produk = ?,
                                id_kategori = ?,
                                harga = ?,
                                stok = ?,
                                foto = ?,
                                barcode = ?
                            WHERE id_produk = ?
                        ");

                        $update->bind_param(
                            "sidissi",
                            $nama,
                            $id_kategori,
                            $harga,
                            $stok,
                            $fotoBaru,
                            $barcode,
                            $id
                        );

                    } else {

                        $update = $conn->prepare("
                            UPDATE tbl_produk
                            SET
                                nama_produk = ?,
                                id_kategori = ?,
                                harga = ?,
                                stok = ?,
                                barcode = ?
                            WHERE id_produk = ?
                        ");

                        $update->bind_param(
                            "sidisi",
                            $nama,
                            $id_kategori,
                            $harga,
                            $stok,
                            $barcode,
                            $id
                        );

                    }

                    if ($update->execute()) {

                        // Hapus foto lama jika foto diganti
                        if (
                            $fotoBaru !== null &&
                            !empty($data['foto']) &&
                            strpos(
                                $data['foto'],
                                'assets/uploads/produk/'
                            ) === 0
                        ) {

                            $fotoLama = $folderFoto
                                . basename($data['foto']);

                            if (is_file($fotoLama)) {
                                unlink($fotoLama);
                            }

                        }

                        if (function_exists('catat_log')) {

                            catat_log(
                                $conn,
                                "Edit Produk",
                                "Admin memperbarui produk ID "
                                . $id
                                . ": "
                                . $nama
                                . " | Barcode: "
                                . $barcode
                            );

                        }

                        $update->close();

                        header(
                            "Location: index.php?pesan="
                            . urlencode("Produk berhasil diperbarui.")
                        );

                        exit;

                    } else {

                        if ($pathFotoBaru && is_file($pathFotoBaru)) {
                            unlink($pathFotoBaru);
                        }

                        if ($update->errno === 1062) {
                            $error = "Barcode sudah digunakan oleh produk lain.";
                        } else {
                            $error = "Gagal memperbarui produk: " . $update->error;
                        }

                        $update->close();

                    }

                }

            }

        }

    }

}

// =====================================
// DATA KATEGORI
// =====================================

$kategori = $conn->query("
    SELECT id_kategori, nama_kategori
    FROM tbl_kategori
    ORDER BY nama_kategori ASC
");

$judul = "Edit Produk";

require_once "../header.php";
?>

<div class="page-header">

    <div>
        <h1>Edit Produk</h1>
        <p class="muted">
            Perbarui informasi, barcode, dan foto produk.
        </p>
    </div>

    <a href="index.php" class="btn btn-secondary">
        Kembali
    </a>

</div>

<div class="panel">

    <div class="panel-heading">

        <div>
            <h3>Formulir Edit Produk</h3>
            <p class="muted">
                Ubah informasi produk sesuai kebutuhan.
            </p>
        </div>

    </div>

    <?php if ($error !== ''): ?>

        <div class="alert alert-danger">
            <?= e($error) ?>
        </div>

    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <div class="form-group">

            <label for="nama_produk">
                Nama Produk
            </label>

            <input
                type="text"
                id="nama_produk"
                name="nama_produk"
                maxlength="100"
                required
                value="<?= e($nama) ?>"
            >

        </div>

        <div class="form-group">

            <label for="id_kategori">
                Kategori
            </label>

            <select
                id="id_kategori"
                name="id_kategori"
                required
            >

                <?php while ($k = $kategori->fetch_assoc()): ?>

                    <option
                        value="<?= (int)$k['id_kategori'] ?>"
                        <?= ($id_kategori == $k['id_kategori']) ? 'selected' : '' ?>
                    >
                        <?= e($k['nama_kategori']) ?>
                    </option>

                <?php endwhile; ?>

            </select>

        </div>

        <div class="form-group">

            <label for="harga">
                Harga Produk (Rp)
            </label>

            <input
                type="number"
                id="harga"
                name="harga"
                min="1"
                step="0.01"
                required
                value="<?= e($harga) ?>"
            >

        </div>

        <div class="form-group">

            <label for="stok">
                Jumlah Stok
            </label>

            <input
                type="number"
                id="stok"
                name="stok"
                min="0"
                required
                value="<?= e($stok) ?>"
            >

        </div>

        <div class="form-group">

            <label for="barcode">
                Barcode Produk
            </label>

            <input
                type="text"
                id="barcode"
                name="barcode"
                maxlength="20"
                inputmode="numeric"
                pattern="[0-9]{8,20}"
                required
                value="<?= e($barcode) ?>"
                placeholder="Masukkan barcode produk"
            >

            <small class="muted">
                Masukkan angka barcode produk, 8 sampai 20 digit.
                Barcode tidak boleh sama dengan produk lain.
            </small>

        </div>

        <div class="form-group">

            <label>Foto Saat Ini</label>

            <?php if (!empty($data['foto'])): ?>

                <div>

                    <img
                        src="<?= BASE_URL . e($data['foto']) ?>"
                        alt="Foto <?= e($data['nama_produk']) ?>"
                        class="product-preview"
                    >

                </div>

            <?php else: ?>

                <p class="muted">
                    Produk belum memiliki foto.
                </p>

            <?php endif; ?>

        </div>

        <div class="form-group">

            <label for="foto">
                Ganti Foto Produk
            </label>

            <input
                type="file"
                id="foto"
                name="foto"
                accept=".jpg,.jpeg,.png,.webp"
            >

            <small class="muted">
                Kosongkan jika tidak ingin mengganti foto.
                Format JPG, PNG, WEBP. Maksimal 2 MB.
            </small>

        </div>

        <div class="form-actions">

            <button
                type="submit"
                class="btn btn-primary"
            >
                Simpan Perubahan
            </button>

            <a href="index.php" class="btn btn-secondary">
                Batal
            </a>

        </div>

    </form>

</div>

<?php require_once "../../includes/footer.php"; ?>
