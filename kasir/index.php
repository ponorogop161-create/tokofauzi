
<?php
require_once "../config/koneksi.php";
require_once "../config/auth.php";

wajib_kasir();

$idUser = (int)($_SESSION['id_user'] ?? 0);

// Transaksi hari ini
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM tbl_transaksi
    WHERE id_user = ? AND DATE(tanggal) = CURDATE()
");
$stmt->bind_param("i", $idUser);
$stmt->execute();
$totalTransaksi = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Omzet hari ini
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(total_belanja), 0) AS total
    FROM tbl_transaksi
    WHERE id_user = ? AND DATE(tanggal) = CURDATE()
");
$stmt->bind_param("i", $idUser);
$stmt->execute();
$omzetHariIni = (float)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Semua transaksi kasir
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM tbl_transaksi
    WHERE id_user = ?
");
$stmt->bind_param("i", $idUser);
$stmt->execute();
$totalSemua = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Transaksi terbaru
$stmt = $conn->prepare("
    SELECT
        t.id_transaksi,
        t.tanggal,
        t.total_belanja,
        p.nama_pelanggan
    FROM tbl_transaksi t
    LEFT JOIN tbl_pelanggan p
        ON t.id_pelanggan = p.id_pelanggan
    WHERE t.id_user = ?
    ORDER BY t.id_transaksi DESC
    LIMIT 5
");
$stmt->bind_param("i", $idUser);
$stmt->execute();
$transaksiTerbaru = $stmt->get_result();

$judul = "Dashboard Kasir";
require_once "header.php";
?>

<div class="kasir-page-header">
    <div>
        <h1>Dashboard Kasir</h1>
        <p>
            Selamat datang, <?= e($_SESSION['nama'] ?? 'Kasir') ?>.
            Kelola transaksi penjualan dari halaman ini.
        </p>
    </div>

    <a href="<?= BASE_URL ?>kasir/transaksi/index.php"
       class="kasir-btn">
        + Transaksi Baru
    </a>
</div>

<!-- STATISTIK -->
<div class="kasir-stats">

    <div class="kasir-stat-card">
        <div class="kasir-stat-icon">▤</div>
        <div>
            <p>Transaksi Hari Ini</p>
            <h2><?= $totalTransaksi ?></h2>
        </div>
    </div>

    <div class="kasir-stat-card">
        <div class="kasir-stat-icon">Rp</div>
        <div>
            <p>Omzet Hari Ini</p>
            <h2><?= rupiah($omzetHariIni) ?></h2>
        </div>
    </div>

    <div class="kasir-stat-card">
        <div class="kasir-stat-icon">▣</div>
        <div>
            <p>Total Transaksi</p>
            <h2><?= $totalSemua ?></h2>
        </div>
    </div>

</div>

<!-- TRANSAKSI TERBARU -->
<div class="kasir-panel">

    <div class="kasir-panel-header">
        <div>
            <h3>Transaksi Terbaru</h3>
            <p>Lima transaksi terakhir yang kamu lakukan.</p>
        </div>

        <a href="<?= BASE_URL ?>kasir/transaksi/riwayat.php"
           class="kasir-btn kasir-btn-secondary">
            Lihat Semua
        </a>
    </div>

    <div class="kasir-table-wrap">
        <table class="kasir-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>ID Transaksi</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>Total</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($transaksiTerbaru->num_rows > 0): ?>
                    <?php $no = 1; ?>

                    <?php while ($row = $transaksiTerbaru->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>

                            <td>
                                <strong>
                                    #<?= (int)$row['id_transaksi'] ?>
                                </strong>
                            </td>

                            <td>
                                <?= e(date(
                                    'd-m-Y H:i',
                                    strtotime($row['tanggal'])
                                )) ?>
                            </td>

                            <td>
                                <?= e($row['nama_pelanggan'] ?? 'Pelanggan Umum') ?>
                            </td>

                            <td>
                                <strong>
                                    <?= rupiah($row['total_belanja']) ?>
                                </strong>
                            </td>

                            <td>
                                <a class="kasir-btn"
                                   href="<?= BASE_URL ?>kasir/transaksi/detail.php?id=<?= (int)$row['id_transaksi'] ?>">
                                    Detail
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:30px;">
                            Belum ada transaksi.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<?php
$stmt->close();
require_once __DIR__ . "/includes/footer.php";
?>
