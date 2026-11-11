
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";
wajib_admin();

$cari = trim($_GET['cari'] ?? '');

$sql = "SELECT * FROM tbl_pelanggan";
if ($cari !== '') {
    $sql .= " WHERE nama_pelanggan LIKE ? OR kota LIKE ?";
}

$sql .= " ORDER BY id_pelanggan DESC";

$stmt = mysqli_prepare($conn, $sql);

if ($cari !== '') {
    $keyword = "%$cari%";
    mysqli_stmt_bind_param($stmt, "ss", $keyword, $keyword);
}

mysqli_stmt_execute($stmt);
$data = mysqli_stmt_get_result($stmt);

$total = mysqli_num_rows($data);

require_once "../header.php";

?>

<div class="content">
    <div class="page-header">
        <div>
            <h1>Data Pelanggan</h1>
            <p>Kelola seluruh data pelanggan TokoKu.</p>
        </div>
        <a href="tambah.php" class="btn btn-primary">+ Tambah Pelanggan</a>
    </div>

    <?php if (isset($_GET['pesan'])): ?>
        <div class="alert alert-success">
            <?= e($_GET['pesan']) ?>
        </div>
    <?php endif; ?>

    <div class="content-card">
        <div class="card-header">
            <div>
                <h3>Daftar Pelanggan</h3>
                <p>Total <?= $total ?> pelanggan</p>
            </div>

            <form method="GET" class="search-form">
                <input type="text" name="cari"
                    placeholder="Cari nama atau kota..."
                    value="<?= e($cari) ?>">
                <button type="submit" class="btn btn-primary">Cari</button>
                <a href="index.php" class="btn btn-secondary">Reset</a>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Pelanggan</th>
                        <th>Kota</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($total > 0): ?>
                    <?php $no = 1; ?>
                    <?php while ($row = mysqli_fetch_assoc($data)): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= e($row['nama_pelanggan']) ?></td>
                            <td><?= e($row['kota']) ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a class="btn btn-warning btn-sm"
                                       href="edit.php?id=<?= (int)$row['id_pelanggan'] ?>">
                                        Edit
                                    </a>

                                    <form method="POST" action="hapus.php"
                                          onsubmit="return confirm('Yakin ingin menghapus pelanggan ini?')">
                                        <input type="hidden" name="id"
                                               value="<?= (int)$row['id_pelanggan'] ?>">
                                        <button class="btn btn-danger btn-sm"
                                                type="submit">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center">
                            Data pelanggan tidak ditemukan.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once "../../includes/footer.php"; ?>
