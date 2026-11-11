
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";
wajib_admin();

$judul = "Data Transaksi";
$cari = trim($_GET['cari'] ?? '');

$sql = "SELECT
            t.id_transaksi,
            t.tanggal,
            t.total_belanja,
            p.nama_pelanggan,
            u.nama AS nama_pengguna,
            u.role
        FROM tbl_transaksi t
        LEFT JOIN tbl_pelanggan p
            ON p.id_pelanggan = t.id_pelanggan
        LEFT JOIN tbl_users u
            ON u.id_user = t.id_user";

if ($cari !== '') {
    $sql .= " WHERE CAST(t.id_transaksi AS CHAR) LIKE ?
              OR p.nama_pelanggan LIKE ?
              OR DATE_FORMAT(t.tanggal, '%Y-%m-%d') LIKE ?";
}

$sql .= " ORDER BY t.id_transaksi DESC";

$stmt = mysqli_prepare($conn, $sql);

if ($cari !== '') {
    $keyword = "%$cari%";
    mysqli_stmt_bind_param(
        $stmt, "sss", $keyword, $keyword, $keyword
    );
}

mysqli_stmt_execute($stmt);
$data = mysqli_stmt_get_result($stmt);

$totalTransaksi = mysqli_num_rows($data);
$totalPenjualan = 0;
$transaksi = [];

while ($row = mysqli_fetch_assoc($data)) {
    $transaksi[] = $row;
    $totalPenjualan += (float)$row['total_belanja'];
}

require_once "../header.php";
?>

<div class="page-header">
    <div>
        <h1>Data Transaksi</h1>
        <p>Pantau seluruh transaksi penjualan TokoKu.</p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <h3>Total Transaksi</h3>
        <strong><?= number_format($totalTransaksi) ?></strong>
    </div>

    <div class="stat-card">
        <h3>Total Penjualan</h3>
        <strong><?= rupiah($totalPenjualan) ?></strong>
    </div>
</div>

<div class="content-card">
    <div class="card-header">
        <div>
            <h3>Riwayat Transaksi</h3>
            <p>Seluruh transaksi yang tersimpan dalam database.</p>
        </div>

        <form method="GET" class="search-form">
            <input type="text" name="cari"
                   placeholder="Cari ID, pelanggan, tanggal..."
                   value="<?= e($cari) ?>">
            <button type="submit" class="btn btn-primary">Cari</button>
            <a href="index.php" class="btn btn-secondary">Reset</a>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID Transaksi</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>Dilayani Oleh</th>
                    <th>Role</th>
                    <th>Total Belanja</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($totalTransaksi > 0): ?>
                <?php foreach ($transaksi as $row): ?>
                    <tr>
                        <td>#<?= (int)$row['id_transaksi'] ?></td>
                        <td><?= e($row['tanggal']) ?></td>
                        <td><?= e($row['nama_pelanggan'] ?? 'Umum') ?></td>
                        <td><?= e($row['nama_pengguna'] ?? 'Tidak tercatat') ?></td>
                        <td>
                            <span class="badge">
                                <?= e(ucfirst($row['role'] ?? '-')) ?>
                            </span>
                        </td>
                        <td><strong><?= rupiah($row['total_belanja']) ?></strong></td>
                        <td>
                            <a href="detail.php?id=<?= (int)$row['id_transaksi'] ?>"
                               class="btn btn-primary btn-sm">
                                Detail
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center">
                        Tidak ada transaksi yang ditemukan.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once "../../includes/footer.php"; ?>
