
<?php
require_once "../config/koneksi.php";
require_once "../config/auth.php";
wajib_admin();

// Statistik
$produk = (int)$conn->query(
    "SELECT COUNT(*) AS total FROM tbl_produk"
)->fetch_assoc()['total'];

$pelanggan = (int)$conn->query(
    "SELECT COUNT(*) AS total FROM tbl_pelanggan"
)->fetch_assoc()['total'];

$transaksi = (int)$conn->query(
    "SELECT COUNT(*) AS total FROM tbl_transaksi"
)->fetch_assoc()['total'];

$totalPenjualan = (float)$conn->query(
    "SELECT COALESCE(SUM(total_belanja), 0) AS total
     FROM tbl_transaksi"
)->fetch_assoc()['total'];

// Stok rendah
$stokRendah = $conn->query(
    "SELECT nama_produk, stok
     FROM tbl_produk
     WHERE stok <= 5
     ORDER BY stok ASC"
);

// Grafik penjualan 7 hari terakhir
$grafik = $conn->query(
    "SELECT DATE(tanggal) AS tanggal,
            SUM(total_belanja) AS total
     FROM tbl_transaksi
     WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(tanggal)
     ORDER BY tanggal ASC"
);

$tanggal = [];
$penjualan = [];

while ($row = $grafik->fetch_assoc()) {
    $tanggal[] = date('d/m', strtotime($row['tanggal']));
    $penjualan[] = (float)$row['total'];
}

// Transaksi terbaru
$terbaru = $conn->query(
    "SELECT t.id_transaksi, t.tanggal, t.total_belanja,
            p.nama_pelanggan
     FROM tbl_transaksi t
     LEFT JOIN tbl_pelanggan p
        ON p.id_pelanggan = t.id_pelanggan
     ORDER BY t.id_transaksi DESC
     LIMIT 7"
);

$judul = "Dashboard Admin";
require_once "header.php";
?>

<div class="page-header">
    <div>
        <h1>Dashboard Admin</h1>
        <p class="muted">
            Selamat datang di halaman pengelolaan TokoKu.
        </p>
    </div>
    <span class="badge">Administrator</span>
</div>

<div class="welcome-card">
    <h2>Selamat datang, <?= e($_SESSION['nama'] ?? 'Admin') ?>!</h2>
    <p>
        Pantau aktivitas toko dan kelola produk, pelanggan,
        transaksi, serta laporan penjualan melalui dashboard ini.
    </p>
</div>

<div class="stats-grid">

    <div class="stat-card">
        <div class="stat-icon">📦</div>
        <h3>Total Produk</h3>
        <strong><?= number_format($produk, 0, ',', '.') ?></strong>
        <p class="muted">Produk terdaftar</p>
    </div>

    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <h3>Total Pelanggan</h3>
        <strong><?= number_format($pelanggan, 0, ',', '.') ?></strong>
        <p class="muted">Pelanggan terdaftar</p>
    </div>

    <div class="stat-card">
        <div class="stat-icon">🧾</div>
        <h3>Total Transaksi</h3>
        <strong><?= number_format($transaksi, 0, ',', '.') ?></strong>
        <p class="muted">Transaksi tercatat</p>
    </div>

    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <h3>Total Penjualan</h3>
        <strong style="font-size:20px">
            <?= rupiah($totalPenjualan) ?>
        </strong>
        <p class="muted">Akumulasi penjualan</p>
    </div>

</div>

<div class="panel">
    <div class="panel-heading">
        <div>
            <h3>Grafik Penjualan</h3>
            <p class="muted">Penjualan selama 7 hari terakhir</p>
        </div>
    </div>

    <div style="position:relative; width:100%; height:320px;">
        <canvas id="salesChart"></canvas>
    </div>
</div>

<div class="panel">
    <div class="panel-heading">
        <div>
            <h3>Transaksi Terbaru</h3>
            <p class="muted">7 transaksi terakhir</p>
        </div>

        <a class="btn btn-primary"
           href="<?= BASE_URL ?>admin/transaksi/index.php">
            Lihat Semua
        </a>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID Transaksi</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>Total Belanja</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>
            <?php if ($terbaru->num_rows === 0): ?>
                <tr>
                    <td colspan="5" style="text-align:center;">
                        Belum ada transaksi.
                    </td>
                </tr>
            <?php else: ?>
                <?php while ($row = $terbaru->fetch_assoc()): ?>
                    <tr>
                        <td>
                            #<?= (int)$row['id_transaksi'] ?>
                        </td>

                        <td>
                            <?= e(date('d-m-Y', strtotime($row['tanggal']))) ?>
                        </td>

                        <td>
                            <?= e($row['nama_pelanggan'] ?? '-') ?>
                        </td>

                        <td>
                            <?= rupiah($row['total_belanja']) ?>
                        </td>

                        <td>
                            <a class="btn btn-secondary btn-sm"
                               href="<?= BASE_URL ?>admin/transaksi/detail.php?id=<?= (int)$row['id_transaksi'] ?>">
                                Detail
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="panel">
    <div class="panel-heading">
        <div>
            <h3>Peringatan Stok Produk</h3>
            <p class="muted">Produk dengan stok 5 atau kurang</p>
        </div>

        <a class="btn btn-secondary btn-sm"
           href="<?= BASE_URL ?>admin/produk/index.php">
            Kelola Produk
        </a>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Nama Produk</th>
                    <th>Sisa Stok</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
            <?php if ($stokRendah->num_rows === 0): ?>
                <tr>
                    <td colspan="3" style="text-align:center;">
                        <span class="badge">Semua stok aman</span>
                    </td>
                </tr>
            <?php else: ?>
                <?php while ($row = $stokRendah->fetch_assoc()): ?>
                    <tr>
                        <td><?= e($row['nama_produk']) ?></td>

                        <td><?= (int)$row['stok'] ?></td>

                        <td>
                            <span class="badge badge-danger">
                                Stok Rendah
                            </span>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const salesCanvas = document.getElementById('salesChart');

if (salesCanvas && typeof Chart !== 'undefined') {
    new Chart(salesCanvas, {
        type: 'line',

        data: {
            labels: <?= json_encode($tanggal) ?>,
            datasets: [{
                label: 'Penjualan',
                data: <?= json_encode($penjualan) ?>,
                borderWidth: 3,
                tension: 0.35,
                fill: true,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },

                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Penjualan: Rp ' +
                                Number(context.raw).toLocaleString('id-ID');
                        }
                    }
                }
            },

            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' +
                                Number(value).toLocaleString('id-ID');
                        }
                    }
                }
            }
        }
    });
}
</script>

<?php require_once "../includes/footer.php"; ?>
