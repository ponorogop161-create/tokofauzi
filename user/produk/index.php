
<?php
require_once __DIR__ . "/../../config/koneksi.php";
require_once __DIR__ . "/../../config/auth.php";

wajib_user();

$judul = "Katalog Produk";

$cari = trim($_GET['cari'] ?? '');
$idKategori = (int)($_GET['kategori'] ?? 0);
$cariLike = "%" . $cari . "%";

// Ambil kategori
$kategoriQuery = $conn->query("
    SELECT id_kategori, nama_kategori
    FROM tbl_kategori
    ORDER BY nama_kategori ASC
");

// Ambil produk
$stmt = $conn->prepare("
    SELECT
        p.id_produk,
        p.nama_produk,
        p.foto,
        p.harga,
        p.stok,
        k.nama_kategori
    FROM tbl_produk p
    LEFT JOIN tbl_kategori k
        ON p.id_kategori = k.id_kategori
    WHERE
        (? = 0 OR p.id_kategori = ?)
        AND (
            p.nama_produk LIKE ?
            OR k.nama_kategori LIKE ?
        )
    ORDER BY p.id_produk DESC
");

$stmt->bind_param(
    "iiss",
    $idKategori,
    $idKategori,
    $cariLike,
    $cariLike
);

$stmt->execute();
$produkQuery = $stmt->get_result();

require_once __DIR__ . "/../header.php";
?>

<style>
.katalog-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

.katalog-header h2 {
    margin: 0 0 7px;
    color: #172554;
    font-size: 25px;
}

.katalog-header p {
    margin: 0;
    color: #64748b;
    font-size: 14px;
}

.katalog-filter {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 25px;
    padding: 16px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 13px;
}

.katalog-filter input,
.katalog-filter select {
    min-height: 42px;
    padding: 0 13px;
    border: 1px solid #dbe2ea;
    border-radius: 8px;
    background: #fff;
    font-size: 14px;
    outline: none;
}

.katalog-filter input {
    flex: 1;
    min-width: 180px;
}

.katalog-filter select {
    min-width: 170px;
}

.katalog-filter button {
    padding: 0 20px;
    border: none;
    border-radius: 8px;
    background: #2563eb;
    color: white;
    font-weight: 600;
    cursor: pointer;
}

.katalog-filter button:hover {
    background: #1d4ed8;
}

.katalog-reset {
    display: flex;
    align-items: center;
    padding: 0 12px;
    color: #64748b;
    text-decoration: none;
    font-size: 13px;
}

.katalog-info {
    margin-bottom: 17px;
    color: #64748b;
    font-size: 14px;
}

.katalog-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 20px;
}

.katalog-card {
    overflow: hidden;
    background: white;
    border: 1px solid #e5eaf1;
    border-radius: 14px;
    transition: .2s;
}

.katalog-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(15, 23, 42, .08);
}

.katalog-foto {
    height: 190px;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.katalog-foto img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.katalog-placeholder {
    font-size: 55px;
    color: #94a3b8;
}

.katalog-card-body {
    padding: 16px;
}

.katalog-kategori {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 11px;
    font-weight: 600;
}

.katalog-card h3 {
    margin: 12px 0 8px;
    color: #172554;
    font-size: 16px;
    min-height: 40px;
}

.katalog-harga {
    margin: 0 0 8px;
    color: #2563eb;
    font-size: 18px;
    font-weight: 700;
}

.katalog-stok {
    margin-bottom: 14px;
    color: #64748b;
    font-size: 12px;
}

.katalog-detail {
    display: block;
    padding: 11px;
    border-radius: 8px;
    background: #2563eb;
    color: white;
    text-align: center;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.katalog-detail:hover {
    background: #1d4ed8;
}

.katalog-kosong {
    grid-column: 1 / -1;
    padding: 55px 20px;
    border: 1px dashed #cbd5e1;
    border-radius: 13px;
    background: white;
    text-align: center;
    color: #64748b;
}

@media (max-width: 1100px) {
    .katalog-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 800px) {
    .katalog-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 13px;
    }

    .katalog-foto {
        height: 155px;
    }
}

@media (max-width: 480px) {
    .katalog-grid {
        grid-template-columns: 1fr;
    }

    .katalog-filter {
        flex-direction: column;
    }

    .katalog-filter input,
    .katalog-filter select,
    .katalog-filter button {
        width: 100%;
        min-height: 42px;
        box-sizing: border-box;
    }

    .katalog-filter button {
        flex: none;
    }
}
</style>

<div class="katalog-header">
    <div>
        <h2>Katalog Produk</h2>
        <p>Temukan produk yang kamu inginkan di TokoKu.</p>
    </div>
</div>

<form method="GET" class="katalog-filter">
    <input
        type="text"
        name="cari"
        placeholder="Cari nama produk..."
        value="<?= e($cari) ?>"
    >

    <select name="kategori">
        <option value="0">Semua Kategori</option>

        <?php while ($kategori = $kategoriQuery->fetch_assoc()): ?>
            <option
                value="<?= (int)$kategori['id_kategori'] ?>"
                <?= $idKategori === (int)$kategori['id_kategori'] ? 'selected' : '' ?>
            >
                <?= e($kategori['nama_kategori']) ?>
            </option>
        <?php endwhile; ?>
    </select>

    <button type="submit">Cari Produk</button>

    <a href="index.php" class="katalog-reset">Reset</a>
</form>

<div class="katalog-info">
    Menampilkan <?= $produkQuery->num_rows ?> produk
</div>

<div class="katalog-grid">

    <?php if ($produkQuery->num_rows > 0): ?>

        <?php while ($produk = $produkQuery->fetch_assoc()): ?>

            <?php
            $foto = trim($produk['foto'] ?? '');
            $lokasiFoto = __DIR__ . "/../../assets/uploads/produk/" . basename($foto);
            $urlFoto = BASE_URL . "assets/uploads/produk/" . rawurlencode(basename($foto));
            ?>

            <div class="katalog-card">

                <div class="katalog-foto">
                    <?php if ($foto !== '' && is_file($lokasiFoto)): ?>
                        <img
                            src="<?= e($urlFoto) ?>"
                            alt="<?= e($produk['nama_produk']) ?>"
                        >
                    <?php else: ?>
                        <div class="katalog-placeholder">▣</div>
                    <?php endif; ?>
                </div>

                <div class="katalog-card-body">

                    <span class="katalog-kategori">
                        <?= e($produk['nama_kategori'] ?? 'Tanpa Kategori') ?>
                    </span>

                    <h3><?= e($produk['nama_produk']) ?></h3>

                    <p class="katalog-harga">
                        <?= rupiah($produk['harga']) ?>
                    </p>

                    <div class="katalog-stok">
                        Stok tersedia: <?= (int)$produk['stok'] ?>
                    </div>

                    <a
                        href="detail.php?id=<?= (int)$produk['id_produk'] ?>"
                        class="katalog-detail"
                    >
                        Lihat Detail →
                    </a>

                </div>
            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="katalog-kosong">
            <div style="font-size:45px; margin-bottom:12px;">🔎</div>
            <h3>Produk tidak ditemukan</h3>
            <p>Coba gunakan kata pencarian atau kategori lainnya.</p>
        </div>

    <?php endif; ?>

</div>

<?php
$stmt->close();
require_once __DIR__ . "/../includes/footer.php";
?>
