
<?php
require_once "../config/koneksi.php";
require_once "../config/auth.php";

wajib_user();

$idUser = (int)($_SESSION['id_user'] ?? 0);

// Jumlah produk tersedia
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM tbl_produk
    WHERE stok > 0
");
$totalProduk = (int)$result->fetch_assoc()['total'];

// Jumlah transaksi user
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM tbl_transaksi
    WHERE id_user = ?
");
$stmt->bind_param("i", $idUser);
$stmt->execute();
$totalPesanan = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Total belanja user
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(total_belanja), 0) AS total
    FROM tbl_transaksi
    WHERE id_user = ?
");
$stmt->bind_param("i", $idUser);
$stmt->execute();
$totalBelanja = (float)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Produk terbaru
$produk = $conn->query("
    SELECT id_produk, nama_produk, foto, harga, stok
    FROM tbl_produk
    WHERE stok > 0
    ORDER BY id_produk DESC
    LIMIT 4
");

$judul = "Dashboard Pembeli";
require_once "header.php";
?>

<div class="user-welcome">
    <div>
        <span class="user-welcome-label">SELAMAT DATANG DI TOKOKU</span>
        <h1>Halo, <?= e($_SESSION['nama'] ?? 'Pembeli') ?>!</h1>
        <p>Temukan produk favoritmu dan nikmati pengalaman belanja yang mudah.</p>

        <a href="<?= BASE_URL ?>user/produk/index.php" class="user-btn">
            Mulai Belanja →
        </a>
    </div>
    <div class="user-welcome-icon">🛍️</div>
</div>

<div class="user-stats">
    <div class="user-stat-card">
        <div class="user-stat-icon">▣</div>
        <div>
            <p>Produk Tersedia</p>
            <h2><?= $totalProduk ?></h2>
        </div>
    </div>

    <div class="user-stat-card">
        <div class="user-stat-icon">▤</div>
        <div>
            <p>Pesanan Saya</p>
            <h2><?= $totalPesanan ?></h2>
        </div>
    </div>

    <div class="user-stat-card">
        <div class="user-stat-icon">Rp</div>
        <div>
            <p>Total Belanja</p>
            <h2><?= rupiah($totalBelanja) ?></h2>
        </div>
    </div>
</div>

<div class="user-section-heading">
    <div>
        <h2>Produk Terbaru</h2>
        <p>Jelajahi produk yang tersedia di TokoKu.</p>
    </div>

    <a href="<?= BASE_URL ?>user/produk/index.php">Lihat Semua →</a>
</div>

<div class="user-product-grid">
    <?php if ($produk && $produk->num_rows > 0): ?>
        <?php while ($row = $produk->fetch_assoc()): ?>
            <div class="user-product-card">
                <div class="user-product-image">
    <?php if (!empty($row['foto']) && file_exists(__DIR__ . "/../" . $row['foto'])): ?>
        <img
            src="<?= BASE_URL . e($row['foto']) ?>"
            alt="<?= e($row['nama_produk']) ?>"
        >
    <?php else: ?>
        <div class="user-product-no-image">
            <span>🛍️</span>
            <small>Foto belum tersedia</small>
        </div>
    <?php endif; ?>
</div>

                <div class="user-product-info">
                    <h3><?= e($row['nama_produk']) ?></h3>
                    <strong><?= rupiah($row['harga']) ?></strong>
                    <p>Stok tersedia: <?= (int)$row['stok'] ?></p>

                    <a href="<?= BASE_URL ?>user/produk/detail.php?id=<?= (int)$row['id_produk'] ?>"
                       class="user-product-button">
                        Lihat Produk
                    </a>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="user-empty">
            Belum ada produk yang tersedia.
        </div>
    <?php endif; ?>
</div>

<style>
.user-welcome {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 32px;
    background: linear-gradient(120deg, #172554, #2563eb);
    border-radius: 16px;
    color: white;
    margin-bottom: 25px;
}

.user-welcome-label {
    font-size: 11px;
    letter-spacing: 1.5px;
    opacity: .8;
    font-weight: 700;
}

.user-welcome h1 {
    margin: 12px 0 8px;
    font-size: 27px;
}

.user-welcome p {
    margin-bottom: 22px;
    font-size: 13px;
    color: #dbeafe;
}

.user-welcome-icon {
    font-size: 75px;
}

.user-btn,
.user-product-button {
    display: inline-block;
    text-decoration: none;
    background: white;
    color: #1d4ed8;
    padding: 11px 17px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
}

.user-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 30px;
}

.user-stat-card {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 22px;
    background: white;
    border: 1px solid #e8edf4;
    border-radius: 13px;
}

.user-stat-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    flex-shrink: 0;
    border-radius: 12px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 20px;
}

.user-stat-card p {
    margin: 0 0 7px;
    color: #64748b;
    font-size: 12px;
}

.user-stat-card h2 {
    margin: 0;
    color: #172554;
    font-size: 21px;
}

.user-section-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin: 30px 0 18px;
}

.user-section-heading h2 {
    font-size: 19px;
    margin: 0 0 5px;
}

.user-section-heading p {
    font-size: 12px;
    color: #64748b;
    margin: 0;
}

.user-section-heading a {
    color: #2563eb;
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
}

.user-product-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
}

.user-product-card {
    background: white;
    border: 1px solid #e8edf4;
    border-radius: 13px;
    overflow: hidden;
}

.user-product-image {
    width: 100%;
    height: 160px;
    background: #f1f5f9;
    overflow: hidden;
}

.user-product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.user-product-no-image {
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    gap: 8px;
}

.user-product-no-image span {
    font-size: 42px;
}

.user-product-no-image small {
    font-size: 11px;
}

.user-product-info {
    padding: 15px;
}

.user-product-info h3 {
    font-size: 14px;
    margin: 0 0 10px;
}

.user-product-info strong {
    color: #2563eb;
    font-size: 14px;
}

.user-product-info p {
    color: #64748b;
    font-size: 11px;
    margin: 9px 0 14px;
}

.user-product-button {
    display: block;
    text-align: center;
    background: #2563eb;
    color: white;
}

.user-empty {
    background: white;
    padding: 30px;
    border-radius: 12px;
    color: #64748b;
    grid-column: 1 / -1;
}

@media (max-width: 1000px) {
    .user-product-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {
    .user-stats {
        grid-template-columns: 1fr;
    }

    .user-welcome {
        padding: 23px;
    }

    .user-welcome-icon {
        font-size: 45px;
    }

    .user-welcome h1 {
        font-size: 21px;
    }
}

@media (max-width: 450px) {
    .user-product-grid {
        grid-template-columns: 1fr;
    }

    .user-section-heading {
        align-items: flex-start;
    }
}
</style>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
