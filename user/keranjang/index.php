
<?php
require_once __DIR__ . "/../../config/koneksi.php";
require_once __DIR__ . "/../../config/auth.php";

wajib_user();

if (!isset($_SESSION['keranjang']) || !is_array($_SESSION['keranjang'])) {
    $_SESSION['keranjang'] = [];
}

// Proses perubahan keranjang
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'update') {
        $idProduk = (int)($_POST['id_produk'] ?? 0);
        $jumlah = (int)($_POST['jumlah'] ?? 0);

        if ($idProduk > 0 && $jumlah > 0) {
            $stmt = $conn->prepare("
                SELECT stok FROM tbl_produk
                WHERE id_produk = ?
            ");
            $stmt->bind_param("i", $idProduk);
            $stmt->execute();
            $dataStok = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($dataStok && $jumlah <= (int)$dataStok['stok']) {
                $_SESSION['keranjang'][$idProduk] = $jumlah;
                header("Location: index.php?pesan=diubah");
                exit;
            } else {
                header("Location: index.php?pesan=stok");
                exit;
            }
        }

        header("Location: index.php?pesan=jumlah");
        exit;
    }

    if ($aksi === 'hapus') {
        $idProduk = (int)($_POST['id_produk'] ?? 0);

        unset($_SESSION['keranjang'][$idProduk]);

        header("Location: index.php?pesan=dihapus");
        exit;
    }

    if ($aksi === 'kosongkan') {
        $_SESSION['keranjang'] = [];

        header("Location: index.php?pesan=dikosongkan");
        exit;
    }
}

// Ambil seluruh data produk untuk mencocokkan isi keranjang
$produkMap = [];

$queryProduk = $conn->query("
    SELECT id_produk, nama_produk, foto, harga, stok
    FROM tbl_produk
");

while ($row = $queryProduk->fetch_assoc()) {
    $produkMap[(int)$row['id_produk']] = $row;
}

// Siapkan data keranjang
$daftarKeranjang = [];
$totalBelanja = 0;
$totalItem = 0;

foreach ($_SESSION['keranjang'] as $id => $jumlah) {
    $id = (int)$id;
    $jumlah = (int)$jumlah;

    if ($jumlah <= 0) {
        continue;
    }

    if (!isset($produkMap[$id])) {
        $daftarKeranjang[] = [
            'id_produk' => $id,
            'nama_produk' => 'Produk tidak ditemukan',
            'foto' => '',
            'harga' => 0,
            'stok' => 0,
            'jumlah' => $jumlah,
            'subtotal' => 0,
            'tersedia' => false
        ];
        continue;
    }

    $produk = $produkMap[$id];
    $harga = (float)$produk['harga'];
    $stok = (int)$produk['stok'];
    $subtotal = $harga * $jumlah;

    $produk['jumlah'] = $jumlah;
    $produk['subtotal'] = $subtotal;
    $produk['tersedia'] = $stok >= $jumlah && $stok > 0;

    $daftarKeranjang[] = $produk;

    $totalBelanja += $subtotal;
    $totalItem += $jumlah;
}

$judul = "Keranjang Belanja";

require_once __DIR__ . "/../header.php";
?>

<style>
.cart-heading {
    margin-bottom: 24px;
}

.cart-heading h2 {
    margin: 0 0 7px;
    color: #172554;
    font-size: 25px;
}

.cart-heading p {
    margin: 0;
    color: #64748b;
    font-size: 14px;
}

.cart-alert {
    padding: 13px 16px;
    margin-bottom: 18px;
    border-radius: 9px;
    font-size: 13px;
}

.cart-success {
    background: #dcfce7;
    color: #166534;
}

.cart-error {
    background: #fee2e2;
    color: #b91c1c;
}

.cart-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 310px;
    gap: 22px;
    align-items: start;
}

.cart-panel,
.cart-summary {
    background: white;
    border: 1px solid #e5eaf1;
    border-radius: 14px;
    overflow: hidden;
}

.cart-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 18px 20px;
    border-bottom: 1px solid #e5eaf1;
}

.cart-panel-header h3 {
    margin: 0;
    color: #172554;
    font-size: 16px;
}

.cart-count {
    color: #64748b;
    font-size: 12px;
}

.cart-item {
    display: grid;
    grid-template-columns: 90px minmax(0, 1fr) 125px;
    gap: 16px;
    align-items: center;
    padding: 18px 20px;
    border-bottom: 1px solid #edf0f4;
}

.cart-item:last-child {
    border-bottom: none;
}

.cart-item-image {
    width: 90px;
    height: 90px;
    background: #f1f5f9;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.cart-item-image img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.cart-placeholder {
    color: #94a3b8;
    font-size: 35px;
}

.cart-item-info h4 {
    margin: 0 0 7px;
    color: #172554;
    font-size: 15px;
}

.cart-item-price {
    margin-bottom: 9px;
    color: #2563eb;
    font-weight: 700;
    font-size: 14px;
}

.cart-stock {
    color: #64748b;
    font-size: 12px;
}

.cart-item-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 9px;
}

.cart-item-actions input {
    width: 75px;
    height: 36px;
    padding: 0 8px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    text-align: center;
}

.cart-update-button,
.cart-delete-button {
    border: none;
    background: none;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
}

.cart-update-button {
    color: #2563eb;
}

.cart-delete-button {
    color: #dc2626;
}

.cart-warning {
    margin-top: 9px;
    color: #b91c1c;
    font-size: 12px;
}

.cart-summary {
    padding: 20px;
}

.cart-summary h3 {
    margin: 0 0 18px;
    color: #172554;
    font-size: 17px;
}

.cart-summary-row {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 14px;
    color: #64748b;
    font-size: 13px;
}

.cart-summary-total {
    padding-top: 16px;
    margin-top: 16px;
    border-top: 1px solid #e2e8f0;
    color: #172554;
    font-size: 15px;
    font-weight: 700;
}

.cart-total-price {
    color: #2563eb;
    font-size: 19px;
}

.cart-checkout-button,
.cart-continue-button {
    display: block;
    width: 100%;
    box-sizing: border-box;
    padding: 13px;
    margin-top: 13px;
    border-radius: 9px;
    text-align: center;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.cart-checkout-button {
    background: #2563eb;
    color: white;
}

.cart-checkout-button:hover {
    background: #1d4ed8;
}

.cart-continue-button {
    background: #eff6ff;
    color: #2563eb;
}

.cart-clear-button {
    padding: 7px 11px;
    border: 1px solid #fecaca;
    border-radius: 7px;
    background: white;
    color: #dc2626;
    font-size: 12px;
    cursor: pointer;
}

.cart-empty {
    padding: 65px 20px;
    text-align: center;
}

.cart-empty-icon {
    margin-bottom: 15px;
    font-size: 60px;
}

.cart-empty h3 {
    margin: 0 0 8px;
    color: #172554;
}

.cart-empty p {
    margin: 0 0 20px;
    color: #64748b;
    font-size: 14px;
}

.cart-empty a {
    display: inline-block;
    padding: 12px 20px;
    border-radius: 8px;
    background: #2563eb;
    color: white;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

@media (max-width: 950px) {
    .cart-layout {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 600px) {
    .cart-item {
        grid-template-columns: 70px minmax(0, 1fr);
        gap: 12px;
        padding: 15px;
    }

    .cart-item-image {
        width: 70px;
        height: 70px;
    }

    .cart-item-actions {
        grid-column: 2;
        flex-direction: row;
        align-items: center;
        justify-content: flex-start;
        flex-wrap: wrap;
    }

    .cart-panel-header {
        padding: 15px;
    }
}
</style>

<div class="cart-heading">
    <h2>Keranjang Belanja</h2>
    <p>Periksa produk pilihanmu sebelum melanjutkan pembelian.</p>
</div>

<?php
$pesan = $_GET['pesan'] ?? '';

if ($pesan === 'diubah') {
    echo '<div class="cart-alert cart-success">Jumlah produk berhasil diperbarui.</div>';
} elseif ($pesan === 'dihapus') {
    echo '<div class="cart-alert cart-success">Produk berhasil dihapus dari keranjang.</div>';
} elseif ($pesan === 'dikosongkan') {
    echo '<div class="cart-alert cart-success">Keranjang berhasil dikosongkan.</div>';
} elseif ($pesan === 'stok') {
    echo '<div class="cart-alert cart-error">Jumlah melebihi stok yang tersedia. Periksa kembali jumlah produk.</div>';
} elseif ($pesan === 'jumlah') {
    echo '<div class="cart-alert cart-error">Jumlah produk tidak valid.</div>';
}
?>

<?php if (count($daftarKeranjang) > 0): ?>

    <div class="cart-layout">

        <div class="cart-panel">

            <div class="cart-panel-header">
                <div>
                    <h3>Produk Pilihan</h3>
                    <span class="cart-count">
                        <?= $totalItem ?> barang
                    </span>
                </div>

                <form method="POST" onsubmit="return confirm('Kosongkan seluruh keranjang?')">
                    <input type="hidden" name="aksi" value="kosongkan">
                    <button type="submit" class="cart-clear-button">
                        Kosongkan
                    </button>
                </form>
            </div>

            <?php foreach ($daftarKeranjang as $item): ?>

                <?php
                $foto = trim($item['foto'] ?? '');
                $lokasiFoto = __DIR__ . "/../../assets/uploads/produk/" . basename($foto);
                $urlFoto = BASE_URL . "assets/uploads/produk/" . rawurlencode(basename($foto));
                ?>

                <div class="cart-item">

                    <div class="cart-item-image">
                        <?php if ($foto !== '' && is_file($lokasiFoto)): ?>
                            <img src="<?= e($urlFoto) ?>" alt="<?= e($item['nama_produk']) ?>">
                        <?php else: ?>
                            <div class="cart-placeholder">▣</div>
                        <?php endif; ?>
                    </div>

                    <div class="cart-item-info">
                        <h4><?= e($item['nama_produk']) ?></h4>

                        <div class="cart-item-price">
                            <?= rupiah($item['harga']) ?>
                        </div>

                        <div class="cart-stock">
                            Stok: <?= (int)$item['stok'] ?>
                            <br>
                            Subtotal: <?= rupiah($item['subtotal']) ?>
                        </div>

                        <?php if (!$item['tersedia']): ?>
                            <div class="cart-warning">
                                Stok tidak mencukupi atau produk sudah habis.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="cart-item-actions">

                        <?php if ($item['tersedia']): ?>
                            <form method="POST">
                                <input type="hidden" name="aksi" value="update">
                                <input
                                    type="hidden"
                                    name="id_produk"
                                    value="<?= (int)$item['id_produk'] ?>"
                                >

                                <input
                                    type="number"
                                    name="jumlah"
                                    value="<?= (int)$item['jumlah'] ?>"
                                    min="1"
                                    max="<?= (int)$item['stok'] ?>"
                                    required
                                >

                                <button type="submit" class="cart-update-button">
                                    Perbarui
                                </button>
                            </form>
                        <?php endif; ?>

                        <form method="POST" onsubmit="return confirm('Hapus produk ini dari keranjang?')">
                            <input type="hidden" name="aksi" value="hapus">
                            <input
                                type="hidden"
                                name="id_produk"
                                value="<?= (int)$item['id_produk'] ?>"
                            >
                            <button type="submit" class="cart-delete-button">
                                Hapus
                            </button>
                        </form>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

        <div class="cart-summary">

            <h3>Ringkasan Belanja</h3>

            <div class="cart-summary-row">
                <span>Total Barang</span>
                <strong><?= $totalItem ?> barang</strong>
            </div>

            <div class="cart-summary-row">
                <span>Subtotal</span>
                <strong><?= rupiah($totalBelanja) ?></strong>
            </div>

            <div class="cart-summary-row cart-summary-total">
                <span>Total Belanja</span>
                <span class="cart-total-price">
                    <?= rupiah($totalBelanja) ?>
                </span>
            </div>

            <?php
            $bisaCheckout = true;

            foreach ($daftarKeranjang as $item) {
                if (!$item['tersedia']) {
                    $bisaCheckout = false;
                    break;
                }
            }
            ?>

            <?php if ($bisaCheckout): ?>
                <a href="<?= BASE_URL ?>user/pesanan/checkout.php" class="btn btn-primary">
    Lanjutkan Checkout
</a>
            <?php else: ?>
                <div class="cart-alert cart-error">
                    Perbaiki jumlah atau hapus produk yang stoknya tidak mencukupi sebelum melanjutkan.
                </div>
            <?php endif; ?>

            <a href="../produk/index.php" class="cart-continue-button">
                ← Lanjut Belanja
            </a>

        </div>

    </div>

<?php else: ?>

    <div class="cart-panel">
        <div class="cart-empty">
            <div class="cart-empty-icon">🛒</div>
            <h3>Keranjang Masih Kosong</h3>
            <p>Kamu belum menambahkan produk ke keranjang.</p>
            <a href="../produk/index.php">Mulai Belanja</a>
        </div>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
