
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";

wajib_admin();

// Menghitung digit pemeriksa EAN-13
function buatEAN13(int $id): string
{
    // Prefix 200 untuk barcode internal toko
    $kode12 = "200" . str_pad((string)$id, 9, "0", STR_PAD_LEFT);

    $total = 0;

    for ($i = 0; $i < 12; $i++) {
        $angka = (int)$kode12[$i];
        $total += ($i % 2 === 0) ? $angka : $angka * 3;
    }

    $checkDigit = (10 - ($total % 10)) % 10;

    return $kode12 . $checkDigit;
}

// Ambil produk yang belum memiliki barcode
$result = $conn->query("
    SELECT id_produk
    FROM tbl_produk
    WHERE barcode IS NULL OR barcode = ''
    ORDER BY id_produk ASC
");

$berhasil = 0;

$stmt = $conn->prepare("
    UPDATE tbl_produk
    SET barcode = ?
    WHERE id_produk = ?
");

while ($row = $result->fetch_assoc()) {
    $idProduk = (int)$row['id_produk'];
    $barcode = buatEAN13($idProduk);

    $stmt->bind_param("si", $barcode, $idProduk);

    if ($stmt->execute()) {
        $berhasil++;
    }
}

$stmt->close();

$judul = "Generate Barcode Produk";
require_once "../header.php";
?>

<div class="panel">
    <h2>Generate Barcode Produk</h2>

    <p>
        Proses pembuatan barcode produk telah selesai.
    </p>

    <p>
        <strong><?= $berhasil ?></strong>
        barcode berhasil dibuat.
    </p>

    <a href="index.php" class="btn btn-primary">
        Kembali ke Data Produk
    </a>
</div>

<?php require_once __DIR__ . "/../../includes/footer.php"; ?>
