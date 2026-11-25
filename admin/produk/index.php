
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";

wajib_admin();

$judul = "Data Produk";
$cari = trim($_GET['cari'] ?? '');

// =====================================
// PENGATURAN PAGINATION
// =====================================

$batas = 10;
$halaman = max(1, (int)($_GET['halaman'] ?? 1));

// =====================================
// HITUNG TOTAL PRODUK
// =====================================

$sqlCount = "
    SELECT COUNT(*) AS total
    FROM tbl_produk p
    LEFT JOIN tbl_kategori k
        ON p.id_kategori = k.id_kategori
";

if ($cari !== '') {
    $sqlCount .= "
        WHERE p.nama_produk LIKE ?
        OR k.nama_kategori LIKE ?
        OR p.barcode LIKE ?
    ";
}

$stmtCount = $conn->prepare($sqlCount);

if ($cari !== '') {
    $keyword = "%{$cari}%";
    $stmtCount->bind_param("sss", $keyword, $keyword, $keyword);
}

$stmtCount->execute();
$totalProduk = (int)$stmtCount->get_result()->fetch_assoc()['total'];
$stmtCount->close();

$totalHalaman = max(1, (int)ceil($totalProduk / $batas));

if ($halaman > $totalHalaman) {
    $halaman = $totalHalaman;
}

$offset = ($halaman - 1) * $batas;

// =====================================
// QUERY PRODUK
// =====================================

$sql = "
    SELECT
        p.id_produk,
        p.nama_produk,
        p.foto,
        p.harga,
        p.stok,
        p.barcode,
        k.nama_kategori
    FROM tbl_produk p
    LEFT JOIN tbl_kategori k
        ON p.id_kategori = k.id_kategori
";

if ($cari !== '') {
    $sql .= "
        WHERE p.nama_produk LIKE ?
        OR k.nama_kategori LIKE ?
        OR p.barcode LIKE ?
    ";
}

$sql .= " ORDER BY p.id_produk DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);

if ($cari !== '') {
    $keyword = "%{$cari}%";
    $stmt->bind_param("sssii", $keyword, $keyword, $keyword, $batas, $offset);
} else {
    $stmt->bind_param("ii", $batas, $offset);
}

$stmt->execute();
$produk = $stmt->get_result();

require_once "../header.php";
?>

<div class="page-header">

    <div>
        <h1>Data Produk</h1>
        <p class="muted">
            Kelola seluruh produk yang tersedia di TokoKu.
        </p>
    </div>

    <a href="tambah.php" class="btn btn-primary">
        + Tambah Produk
    </a>

</div>

<?php if (isset($_GET['pesan'])): ?>

    <div class="alert alert-success">
        <?= e($_GET['pesan']) ?>
    </div>

<?php endif; ?>

<div class="panel">

    <div class="card-header">
        <div>
            <h3>Daftar Produk</h3>
            <p class="muted">
                Informasi produk, foto, kategori, barcode, harga, dan stok.
            </p>
        </div>
    </div>

    <!-- PENCARIAN PRODUK -->

    <form
        method="GET"
        class="search-form"
        style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;"
    >

        <input
            type="text"
            name="cari"
            placeholder="Cari nama, kategori, atau barcode..."
            value="<?= e($cari) ?>"
            style="flex:1;min-width:200px;"
        >

        <button type="submit" class="btn btn-primary">
            Cari
        </button>

        <a href="index.php" class="btn btn-secondary">
            Reset
        </a>

    </form>

    <!-- INFORMASI JUMLAH PRODUK -->

    <p class="muted" style="margin-bottom:15px;">
        Menampilkan
        <strong><?= $totalProduk > 0 ? $offset + 1 : 0 ?></strong>
        -
        <strong><?= min($offset + $batas, $totalProduk) ?></strong>
        dari
        <strong><?= number_format($totalProduk, 0, ',', '.') ?></strong>
        produk.
    </p>

    <!-- TABEL PRODUK -->

    <div class="table-responsive">

        <table>

            <thead>
                <tr>
                    <th>No.</th>
                    <th>Foto</th>
                    <th>Nama Produk</th>
                    <th>Kategori</th>
                    <th>Barcode</th>
                    <th>Harga</th>
                    <th>Stok</th>
                    <th>Status Stok</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>

                <?php if ($produk->num_rows > 0): ?>

                    <?php $no = $offset + 1; ?>

                    <?php while ($row = $produk->fetch_assoc()): ?>

                        <tr>

                            <td><?= $no++ ?></td>

                            <!-- FOTO -->

                            <td>

                                <?php
                                $fotoValid = !empty($row['foto'])
                                    && strpos(
                                        $row['foto'],
                                        'assets/uploads/produk/'
                                    ) === 0;
                                ?>

                                <?php if ($fotoValid): ?>

                                    <img
                                        src="<?= BASE_URL . e($row['foto']) ?>"
                                        alt="<?= e($row['nama_produk']) ?>"
                                        class="product-image"
                                        loading="lazy"
                                    >

                                <?php else: ?>

                                    <div class="product-image-empty">
                                        No Foto
                                    </div>

                                <?php endif; ?>

                            </td>

                            <!-- NAMA PRODUK -->

                            <td>
                                <strong>
                                    <?= e($row['nama_produk']) ?>
                                </strong>
                            </td>

                            <!-- KATEGORI -->

                            <td>
                                <?= e($row['nama_kategori'] ?? 'Tanpa Kategori') ?>
                            </td>

                            <!-- BARCODE -->

                            <td>

                                <?php if (!empty($row['barcode'])): ?>

                                    <code>
                                        <?= e($row['barcode']) ?>
                                    </code>

                                <?php else: ?>

                                    <span class="muted">
                                        Belum ada
                                    </span>

                                <?php endif; ?>

                            </td>

                            <!-- HARGA -->

                            <td>
                                <?= rupiah($row['harga']) ?>
                            </td>

                            <!-- STOK -->

                            <td>
                                <?= number_format(
                                    (int)$row['stok'],
                                    0,
                                    ',',
                                    '.'
                                ) ?>
                            </td>

                            <!-- STATUS STOK -->

                            <td>

                                <?php if ((int)$row['stok'] <= 5): ?>

                                    <span class="badge badge-danger">
                                        Stok Menipis
                                    </span>

                                <?php else: ?>

                                    <span class="badge badge-success">
                                        Tersedia
                                    </span>

                                <?php endif; ?>

                            </td>

                            <!-- AKSI -->

                            <td>

                                <div class="action-buttons">

                                    <a
                                        href="edit.php?id=<?= (int)$row['id_produk'] ?>"
                                        class="btn btn-sm btn-warning"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        action="hapus.php"
                                        method="POST"
                                        onsubmit="return confirm('Yakin ingin menghapus produk ini?')"
                                    >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$row['id_produk'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-danger"
                                        >
                                            Hapus
                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="9" style="text-align:center;padding:25px;">
                            Produk tidak ditemukan.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

    <!-- PAGINATION -->

    <?php if ($totalHalaman > 1): ?>

        <div class="pagination-container">

            <div class="pagination-info">
                Halaman <?= $halaman ?> dari <?= $totalHalaman ?>
            </div>

            <div class="pagination">

                <?php
                $queryCari = $cari !== ''
                    ? '&cari=' . urlencode($cari)
                    : '';
                ?>

                <!-- SEBELUMNYA -->

                <?php if ($halaman > 1): ?>

                    <a
                        href="?halaman=<?= $halaman - 1 ?><?= $queryCari ?>"
                        class="page-link"
                    >
                        &laquo; Sebelumnya
                    </a>

                <?php else: ?>

                    <span class="page-link disabled">
                        &laquo; Sebelumnya
                    </span>

                <?php endif; ?>

                <!-- NOMOR HALAMAN -->

                <?php
                $awal = max(1, $halaman - 2);
                $akhir = min($totalHalaman, $halaman + 2);
                ?>

                <?php if ($awal > 1): ?>

                    <a href="?halaman=1<?= $queryCari ?>" class="page-link">
                        1
                    </a>

                    <?php if ($awal > 2): ?>
                        <span class="page-link disabled">...</span>
                    <?php endif; ?>

                <?php endif; ?>

                <?php for ($i = $awal; $i <= $akhir; $i++): ?>

                    <a
                        href="?halaman=<?= $i ?><?= $queryCari ?>"
                        class="page-link <?= $i === $halaman ? 'active' : '' ?>"
                    >
                        <?= $i ?>
                    </a>

                <?php endfor; ?>

                <?php if ($akhir < $totalHalaman): ?>

                    <?php if ($akhir < $totalHalaman - 1): ?>
                        <span class="page-link disabled">...</span>
                    <?php endif; ?>

                    <a
                        href="?halaman=<?= $totalHalaman ?><?= $queryCari ?>"
                        class="page-link"
                    >
                        <?= $totalHalaman ?>
                    </a>

                <?php endif; ?>

                <!-- SELANJUTNYA -->

                <?php if ($halaman < $totalHalaman): ?>

                    <a
                        href="?halaman=<?= $halaman + 1 ?><?= $queryCari ?>"
                        class="page-link"
                    >
                        Selanjutnya &raquo;
                    </a>

                <?php else: ?>

                    <span class="page-link disabled">
                        Selanjutnya &raquo;
                    </span>

                <?php endif; ?>

            </div>

        </div>

    <?php endif; ?>

</div>

<style>
.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 22px;
}

.pagination-info {
    color: #64748b;
    font-size: 13px;
}

.pagination {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 5px;
}

.page-link {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    min-width: 36px;
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    color: #334155;
    background: #fff;
    text-decoration: none;
    font-size: 13px;
}

.page-link:hover {
    background: #f1f5f9;
}

.page-link.active {
    color: #fff;
    background: #2563eb;
    border-color: #2563eb;
    font-weight: bold;
}

.page-link.disabled {
    color: #94a3b8;
    background: #f8fafc;
    cursor: not-allowed;
    pointer-events: none;
}

@media (max-width: 600px) {
    .pagination-container {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>

<?php
$stmt->close();
require_once "../../includes/footer.php";
?>
