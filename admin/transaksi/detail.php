
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";
wajib_admin();

$judul = "Detail Transaksi";
$id = (int)($_GET['id'] ?? 0);

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        t.id_transaksi,
        t.tanggal,
        t.total_belanja,
        p.nama_pelanggan,
        p.kota,
        u.nama AS nama_pengguna,
        u.role
     FROM tbl_transaksi t
     LEFT JOIN tbl_pelanggan p
        ON p.id_pelanggan = t.id_pelanggan
     LEFT JOIN tbl_users u
        ON u.id_user = t.id_user
     WHERE t.id_transaksi = ?"
);

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$transaksi = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$transaksi) {
    header("Location: index.php?pesan=" .
        urlencode("Transaksi tidak ditemukan."));
    exit;
}

$stmtDetail = mysqli_prepare(
    $conn,
    "SELECT nama_produk, jumlah, harga, subtotal
     FROM tbl_detail_transaksi
     WHERE id_transaksi = ?
     ORDER BY id_detail ASC"
);

mysqli_stmt_bind_param($stmtDetail, "i", $id);
mysqli_stmt_execute($stmtDetail);
$detail = mysqli_stmt_get_result($stmtDetail);

require_once "../header.php";
?>

<div class="page-header">
    <div>
        <h1>Detail Transaksi #<?= $id ?></h1>
        <p>Informasi lengkap transaksi penjualan.</p>
    </div>

    <div class="action-buttons">
        <a href="cetak.php?id=<?= $id ?>"
           target="_blank" class="btn btn-success">
            Cetak Struk
        </a>

        <a href="index.php" class="btn btn-secondary">
            Kembali
        </a>
    </div>
</div>

<div class="content-card">
    <div class="card-header">
        <div>
            <h3>Informasi Transaksi</h3>
            <p>Nomor transaksi: #<?= $id ?></p>
        </div>
        <span class="badge">Transaksi Tersimpan</span>
    </div>

    <div class="detail-grid">
        <div>
            <label>Tanggal Transaksi</label>
            <strong><?= e($transaksi['tanggal']) ?></strong>
        </div>

        <div>
            <label>Nama Pelanggan</label>
            <strong><?= e($transaksi['nama_pelanggan'] ?? 'Umum') ?></strong>
        </div>

        <div>
            <label>Kota</label>
            <strong><?= e($transaksi['kota'] ?? '-') ?></strong>
        </div>

        <div>
            <label>Petugas / Pengguna</label>
            <strong><?= e($transaksi['nama_pengguna'] ?? 'Tidak tercatat') ?></strong>
        </div>

        <div>
            <label>Role</label>
            <strong><?= e(ucfirst($transaksi['role'] ?? '-')) ?></strong>
        </div>
    </div>
</div>

<div class="content-card">
    <div class="card-header">
        <div>
            <h3>Produk yang Dibeli</h3>
            <p>Rincian barang dalam transaksi ini.</p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Produk</th>
                    <th>Jumlah</th>
                    <th>Harga</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
            <?php $no = 1; ?>
            <?php $jumlahItem = 0; ?>

            <?php if (mysqli_num_rows($detail) > 0): ?>
                <?php while ($item = mysqli_fetch_assoc($detail)): ?>
                    <?php $jumlahItem += (int)$item['jumlah']; ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= e($item['nama_produk']) ?></td>
                        <td><?= (int)$item['jumlah'] ?></td>
                        <td><?= rupiah($item['harga']) ?></td>
                        <td><?= rupiah($item['subtotal']) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center">
                        Detail produk tidak ditemukan.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="transaction-total">
        <div>
            <span>Total Item</span>
            <strong><?= $jumlahItem ?> barang</strong>
        </div>
        <div>
            <span>Total Pembayaran</span>
            <strong><?= rupiah($transaksi['total_belanja']) ?></strong>
        </div>
    </div>
</div>

<?php require_once "../../includes/footer.php"; ?>
