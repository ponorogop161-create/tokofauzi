
<?php
require_once __DIR__ . "/../../config/koneksi.php";
require_once __DIR__ . "/../../config/auth.php";

wajib_user();

$idProduk = (int)($_GET['id'] ?? 0);

if ($idProduk <= 0) {
    header("Location: index.php");
    exit;
}

// Ambil data produk
$stmt = $conn->prepare("
    SELECT
        p.id_produk,
        p.nama_produk,
        p.foto,
        p.harga,
        p.stok,
        p.id_kategori,
        k.nama_kategori
    FROM tbl_produk p
    LEFT JOIN tbl_kategori k
        ON p.id_kategori = k.id_kategori
    WHERE p.id_produk = ?
    LIMIT 1
");

$stmt->bind_param("i", $idProduk);
$stmt->execute();

$produk = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$produk) {
    header("Location: index.php");
    exit;
}

$error = "";

// Proses tambah ke keranjang
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $jumlah = (int)($_POST['jumlah'] ?? 1);

    if ($jumlah < 1) {
        $error = "Jumlah produk minimal 1.";
    } elseif ($jumlah > (int)$produk['stok']) {
        $error = "Jumlah melebihi stok yang tersedia.";
    } else {

        if (!isset($_SESSION['keranjang']) || !is_array($_SESSION['keranjang'])) {
            $_SESSION['keranjang'] = [];
        }

        $jumlahLama = (int)($_SESSION['keranjang'][$idProduk] ?? 0);
        $jumlahBaru = $jumlahLama + $jumlah;

        if ($jumlahBaru > (int)$produk['stok']) {
            $error = "Jumlah di keranjang melebihi stok yang tersedia.";
        } else {
            $_SESSION['keranjang'][$idProduk] = $jumlahBaru;

            header("Location: detail.php?id=" . $idProduk . "&berhasil=1");
            exit;
        }
    }
}

$foto = trim($produk['foto'] ?? '');
$lokasiFoto = __DIR__ . "/../../assets/uploads/produk/" . basename($foto);
$urlFoto = BASE_URL . "assets/uploads/produk/" . rawurlencode(basename($foto));

$judul = "Detail Produk";

require_once __DIR__ . "/../header.php";
?>

<style>
.detail-breadcrumb {
    margin-bottom: 22px;
    color: #64748b;
    font-size: 13px;
}

.detail-breadcrumb a {
    color: #2563eb;
    text-decoration: none;
}

.detail-container {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 30px;
    background: #fff;
    border: 1px solid #e5eaf1;
    border-radius: 16px;
    padding: 25px;
}

.detail-image {
    min-height: 380px;
    background: #f1f5f9;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.detail-image img {
    width: 100%;
    height: 380px;
    object-fit: contain;
}

.detail-image-placeholder {
    font-size: 90px;
    color: #94a3b8;
}

.detail-info {
    padding: 12px 5px;
}

.detail-category {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 12px;
    font-weight: 600;
}

.detail-info h1 {
    margin: 18px 0 12px;
    color: #172554;
    font-size: 29px;
}

.detail-price {
    margin: 15px 0;
    color: #2563eb;
    font-size: 27px;
    font-weight: 800;
}

.detail-stock {
    margin-bottom: 22px;
    color: #64748b;
    font-size: 14px;
}

.detail-stock strong {
    color: #15803d;
}

.detail-divider {
    border: 0;
    border-top: 1px solid #e2e8f0;
    margin: 20px 0;
}

.detail-quantity {
    margin-bottom: 20px;
}

.detail-quantity label {
    display: block;
    margin-bottom: 9px;
    color: #334155;
    font-size: 14px;
    font-weight: 600;
}

.detail-quantity input {
    width: 110px;
    height: 43px;
    padding: 0 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 15px;
}

.detail-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.detail-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 45px;
    padding: 0 20px;
    border: 0;
    border-radius: 9px;
    background: #2563eb;
    color: #fff;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
}

.detail-button:hover {
    background: #1d4ed8;
}

.detail-button-secondary {
    background: #e2e8f0;
    color: #334155;
}

.detail-button-secondary:hover {
    background: #cbd5e1;
    color: #172554;
}

.detail-alert {
    padding: 13px 15px;
    margin-bottom: 18px;
    border-radius: 9px;
    font-size: 13px;
}

.detail-alert-success {
    background: #dcfce7;
    color: #166534;
}

.detail-alert-error {
    background: #fee2e2;
    color: #b91c1c;
}

.detail-description {
    margin-top: 25px;
    padding: 20px;
    background: #fff;
    border: 1px solid #e5eaf1;
    border-radius: 13px;
}

.detail-description h3 {
    margin: 0 0 10px;
    color: #172554;
}

.detail-description p {
    margin: 0;
    color: #64748b;
    line-height: 1.7;
    font-size: 14px;
}

@media (max-width: 850px) {
    .detail-container {
        grid-template-columns: 1fr;
    }

    .detail-image {
        min-height: 280px;
    }

    .detail-image img {
        height: 280px;
    }
}

@media (max-width: 480px) {
    .detail-container {
        padding: 15px;
    }

    .detail-info h1 {
        font-size: 23px;
    }

    .detail-price {
        font-size: 23px;
    }

    .detail-actions {
        flex-direction: column;
    }

    .detail-button {
        width: 100%;
        box-sizing: border-box;
    }
}
</style>

<div class="detail-breadcrumb">
    <a href="../index.php">Dashboard</a>
    /
    <a href="index.php">Katalog Produk</a>
    /
    Detail Produk
</div>

<?php if (isset($_GET['berhasil'])): ?>
    <div class="detail-alert detail-alert-success">
        Produk berhasil ditambahkan ke keranjang!
        <a href="../keranjang/index.php">Lihat Keranjang</a>
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="detail-alert detail-alert-error">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<div class="detail-container">

    <div class="detail-image">
        <?php if ($foto !== '' && is_file($lokasiFoto)): ?>
            <img src="<?= e($urlFoto) ?>" alt="<?= e($produk['nama_produk']) ?>">
        <?php else: ?>
            <div class="detail-image-placeholder">▣</div>
        <?php endif; ?>
    </div>

    <div class="detail-info">

        <span class="detail-category">
            <?= e($produk['nama_kategori'] ?? 'Tanpa Kategori') ?>
        </span>

        <h1><?= e($produk['nama_produk']) ?></h1>

        <div class="detail-price">
            <?= rupiah($produk['harga']) ?>
        </div>

        <div class="detail-stock">
            Stok tersedia:
            <strong><?= (int)$produk['stok'] ?> produk</strong>
        </div>

        <hr class="detail-divider">

        <?php if ((int)$produk['stok'] > 0): ?>

            <form method="POST">

                <div class="detail-quantity">
                    <label for="jumlah">Jumlah Pembelian</label>

                    <input
                        type="number"
                        id="jumlah"
                        name="jumlah"
                        value="1"
                        min="1"
                        max="<?= (int)$produk['stok'] ?>"
                        required
                    >
                </div>

                <div class="detail-actions">
                    <button type="submit" class="detail-button">
                        🛒 Tambah ke Keranjang
                    </button>

                    <a href="index.php" class="detail-button detail-button-secondary">
                        Kembali
                    </a>
                </div>

            </form>

        <?php else: ?>

            <div class="detail-alert detail-alert-error">
                Maaf, produk ini sedang habis.
            </div>

            <a href="index.php" class="detail-button detail-button-secondary">
                Kembali ke Katalog
            </a>

        <?php endif; ?>

    </div>
</div>

<div class="detail-description">
    <h3>Informasi Produk</h3>
    <p>
        <?= e($produk['nama_produk']) ?> merupakan salah satu produk
        yang tersedia di TokoKu. Harga dan stok produk mengikuti
        data yang tercatat pada sistem.
    </p>
</div>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
