
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";

wajib_admin();

$judul = "Data Produk";
$cari = trim($_GET['cari'] ?? '');

// Query produk
$sql = "
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
";

if ($cari !== '') {
    $sql .= "
        WHERE p.nama_produk LIKE ?
        OR k.nama_kategori LIKE ?
    ";
}

$sql .= " ORDER BY p.id_produk DESC";

$stmt = $conn->prepare($sql);

if ($cari !== '') {
    $keyword = "%{$cari}%";
    $stmt->bind_param("ss", $keyword, $keyword);
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
                Informasi produk, foto, kategori, harga, dan stok.
            </p>
        </div>
    </div>

    <form method="GET" class="search-form"
          style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;">

        <input
            type="text"
            name="cari"
            placeholder="Cari nama produk atau kategori..."
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

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Foto</th>
                    <th>Nama Produk</th>
                    <th>Kategori</th>
                    <th>Harga</th>
                    <th>Stok</th>
                    <th>Status Stok</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($produk->num_rows > 0): ?>
                    <?php $no = 1; ?>

                    <?php while ($row = $produk->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>

                            <td>
                                <?php
                                $fotoValid = !empty($row['foto'])
                                    && strpos($row['foto'], 'assets/uploads/produk/') === 0;
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

                            <td>
                                <strong>
                                    <?= e($row['nama_produk']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= e($row['nama_kategori'] ?? 'Tanpa Kategori') ?>
                            </td>

                            <td>
                                <?= rupiah($row['harga']) ?>
                            </td>

                            <td>
                                <?= number_format((int)$row['stok'], 0, ',', '.') ?>
                            </td>

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
                        <td colspan="8" style="text-align:center;padding:25px;">
                            Produk tidak ditemukan.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$stmt->close();
require_once "../../includes/footer.php";
?>
