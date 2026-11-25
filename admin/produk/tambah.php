
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";

wajib_admin();

$error = '';
$nama = '';
$id_kategori = '';
$harga = '';
$stok = '0';
$barcode = '';

$folderFoto = __DIR__ . "/../../assets/uploads/produk/";

if (!is_dir($folderFoto)) {
    mkdir($folderFoto, 0755, true);
}

// =====================================
// PROSES TAMBAH PRODUK
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nama = trim($_POST['nama_produk'] ?? '');
    $id_kategori = (int)($_POST['id_kategori'] ?? 0);
    $harga = (float)($_POST['harga'] ?? 0);
    $stok = (int)($_POST['stok'] ?? 0);
    $barcode = trim($_POST['barcode'] ?? '');

    // =====================================
    // VALIDASI DATA
    // =====================================

    if (
        $nama === '' ||
        $id_kategori <= 0 ||
        $harga <= 0 ||
        $stok < 0 ||
        $barcode === ''
    ) {

        $error = "Lengkapi semua data produk dengan benar.";

    } elseif (!preg_match('/^[A-Za-z0-9]{8,40}$/', $barcode)) {

        $error = "Barcode harus terdiri dari 8 sampai 40 karakter huruf dan angka.";

    } else {

        // =====================================
        // CEK KATEGORI
        // =====================================

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

            // =====================================
            // CEK BARCODE DUPLIKAT
            // =====================================

            $cekBarcode = $conn->prepare("
                SELECT id_produk
                FROM tbl_produk
                WHERE barcode = ?
                LIMIT 1
            ");

            $cekBarcode->bind_param("s", $barcode);
            $cekBarcode->execute();

            $barcodeAda = $cekBarcode->get_result()->num_rows > 0;

            $cekBarcode->close();

            if ($barcodeAda) {

                $error = "Barcode sudah digunakan oleh produk lain.";

            } elseif (
                !isset($_FILES['foto']) ||
                $_FILES['foto']['error'] !== UPLOAD_ERR_OK
            ) {

                $error = "Silakan pilih foto produk terlebih dahulu.";

            } elseif ($_FILES['foto']['size'] > 2 * 1024 * 1024) {

                $error = "Ukuran foto maksimal 2 MB.";

            } else {

                // =====================================
                // VALIDASI FORMAT FOTO
                // =====================================

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

                    // =====================================
                    // UPLOAD FOTO
                    // =====================================

                    $namaFile = bin2hex(random_bytes(16))
                        . "." . $formatFoto[$mime];

                    $tujuan = $folderFoto . $namaFile;

                    $lokasiFoto = "assets/uploads/produk/" . $namaFile;

                    if (
                        move_uploaded_file(
                            $_FILES['foto']['tmp_name'],
                            $tujuan
                        )
                    ) {

                        // =====================================
                        // SIMPAN PRODUK
                        // =====================================

                        $stmt = $conn->prepare("
                            INSERT INTO tbl_produk
                            (
                                nama_produk,
                                foto,
                                id_kategori,
                                harga,
                                stok,
                                barcode
                            )
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");

                        $stmt->bind_param(
                            "ssidis",
                            $nama,
                            $lokasiFoto,
                            $id_kategori,
                            $harga,
                            $stok,
                            $barcode
                        );

                        if ($stmt->execute()) {

                            // =====================================
                            // CATAT LOG AKTIVITAS
                            // =====================================

                            if (function_exists('catat_log')) {

                                catat_log(
                                    $conn,
                                    "Tambah Produk",
                                    "Admin menambahkan produk: "
                                    . $nama
                                    . " dengan barcode: "
                                    . $barcode
                                );

                            }

                            $stmt->close();

                            header(
                                "Location: index.php?pesan="
                                . urlencode("Produk berhasil ditambahkan.")
                            );

                            exit;

                        } else {

                            // Hapus foto jika gagal menyimpan
                            if (is_file($tujuan)) {
                                unlink($tujuan);
                            }

                            if ($stmt->errno === 1062) {

                                $error = "Barcode sudah digunakan oleh produk lain.";

                            } else {

                                $error = "Gagal menyimpan produk: " . $stmt->error;

                            }

                            $stmt->close();

                        }

                    } else {

                        $error = "Foto gagal diunggah. Periksa izin folder penyimpanan.";

                    }

                }

            }

        }

    }

}

// =====================================
// AMBIL DATA KATEGORI
// =====================================

$kategori = $conn->query("
    SELECT id_kategori, nama_kategori
    FROM tbl_kategori
    ORDER BY nama_kategori ASC
");

$judul = "Tambah Produk";

require_once "../header.php";
?>

<div class="page-header">

    <div>
        <h1>Tambah Produk</h1>
        <p class="muted">
            Masukkan informasi, barcode, dan foto produk baru.
        </p>
    </div>

    <a href="index.php" class="btn btn-secondary">
        Kembali
    </a>

</div>

<div class="panel">

    <div class="panel-heading">

        <div>
            <h3>Formulir Produk</h3>
            <p class="muted">
                Lengkapi informasi produk berikut.
            </p>
        </div>

    </div>

    <?php if ($error !== ''): ?>

        <div class="alert alert-danger">
            <?= e($error) ?>
        </div>

    <?php endif; ?>

    <?php if ($kategori->num_rows === 0): ?>

        <div class="alert alert-danger">
            Belum ada kategori. Tambahkan kategori terlebih dahulu.
        </div>

        <a
            href="<?= BASE_URL ?>admin/kategori/tambah.php"
            class="btn btn-primary"
        >
            Tambah Kategori
        </a>

    <?php else: ?>

        <form method="POST" enctype="multipart/form-data">

            <!-- NAMA PRODUK -->

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
                    placeholder="Masukkan nama produk"
                >

            </div>

            <!-- KATEGORI -->

            <div class="form-group">

                <label for="id_kategori">
                    Kategori
                </label>

                <select
                    id="id_kategori"
                    name="id_kategori"
                    required
                >

                    <option value="">
                        Pilih Kategori
                    </option>

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

            <!-- HARGA PRODUK -->

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
                    placeholder="Masukkan harga produk"
                >

            </div>

            <!-- STOK PRODUK -->

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

            <!-- BARCODE PRODUK -->

            <div class="form-group">

                <label for="barcode">
                    Barcode Produk
                </label>

                <input
                    type="text"
                    id="barcode"
                    name="barcode"
                    maxlength="40"
                    pattern="[A-Za-z0-9]{8,40}"
                    required
                    value="<?= e($barcode) ?>"
                    placeholder="Masukkan kode barcode produk"
                    title="Barcode harus terdiri dari 8 sampai 40 karakter huruf dan angka."
                >

                <small class="muted">
                    Barcode dapat berupa huruf dan angka.
                    Minimal 8 karakter dan maksimal 40 karakter.
                    Tidak boleh menggunakan spasi atau simbol khusus.
                </small>

            </div>

            <!-- FOTO PRODUK -->

            <div class="form-group">

                <label for="foto">
                    Foto Produk
                </label>

                <input
                    type="file"
                    id="foto"
                    name="foto"
                    accept=".jpg,.jpeg,.png,.webp"
                    required
                >

                <small class="muted">
                    Format JPG, PNG, WEBP. Maksimal 2 MB.
                </small>

            </div>

            <!-- TOMBOL -->

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Simpan Produk
                </button>

                <a
                    href="index.php"
                    class="btn btn-secondary"
                >
                    Batal
                </a>

            </div>

        </form>

    <?php endif; ?>

</div>

<?php require_once "../../includes/footer.php"; ?>
