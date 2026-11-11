
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";
wajib_admin();

$judul = "Data Kategori";

$data = $conn->query("
    SELECT
        k.id_kategori,
        k.nama_kategori,
        COUNT(p.id_produk) AS jumlah_produk
    FROM tbl_kategori k
    LEFT JOIN tbl_produk p
        ON k.id_kategori = p.id_kategori
    GROUP BY k.id_kategori, k.nama_kategori
    ORDER BY k.id_kategori DESC
");

require_once "../header.php";
?>

<div class="page-header">
    <div>
        <h1>Data Kategori</h1>
        <p>Kelola dan pantau kategori produk TokoKu.</p>
    </div>

    <a href="tambah.php" class="btn btn-primary">
        + Tambah Kategori
    </a>
</div>

<?php if (isset($_GET['pesan'])): ?>
    <div class="alert alert-success">
        <?= e($_GET['pesan']) ?>
    </div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-info">
            <span>Total Kategori</span>
            <h3><?= number_format($data->num_rows) ?></h3>
        </div>
        <div class="stat-icon">📁</div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span>Total Produk</span>
            <h3>
                <?php
                $totalProduk = $conn->query("
                    SELECT COUNT(*) AS total FROM tbl_produk
                ")->fetch_assoc();

                echo number_format($totalProduk['total']);
                ?>
            </h3>
        </div>
        <div class="stat-icon">📦</div>
    </div>
</div>

<div class="panel" style="margin-top:25px;">

    <div class="card-header">
        <div>
            <h3>Daftar Kategori</h3>
            <p>Daftar seluruh kategori produk yang tersedia.</p>
        </div>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Nama Kategori</th>
                    <th>Jumlah Produk</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($data->num_rows > 0): ?>
                    <?php $no = 1; ?>

                    <?php while ($row = $data->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>

                            <td>
                                <strong>
                                    <?= e($row['nama_kategori']) ?>
                                </strong>
                            </td>

                            <td>
                                <span class="badge">
                                    <?= number_format($row['jumlah_produk']) ?>
                                    Produk
                                </span>
                            </td>

                            <td>
                                <div class="action-buttons">

                                    <a
                                        href="edit.php?id=<?= (int)$row['id_kategori'] ?>"
                                        class="btn btn-sm btn-warning"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        action="hapus.php"
                                        method="POST"
                                        onsubmit="return confirm('Yakin ingin menghapus kategori ini?')"
                                    >
                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$row['id_kategori'] ?>"
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
                        <td colspan="4" style="text-align:center;padding:25px;">
                            Belum ada kategori produk.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<?php
require_once "../../includes/footer.php";
?>
