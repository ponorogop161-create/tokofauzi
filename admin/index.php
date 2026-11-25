
<?php
require_once "../config/koneksi.php";
require_once "../config/auth.php";

wajib_admin();

// =====================================
// STATISTIK
// =====================================

// Total produk
$produk = (int)$conn->query(
    "SELECT COUNT(*) AS total FROM tbl_produk"
)->fetch_assoc()['total'];

// Total pelanggan
$pelanggan = (int)$conn->query(
    "SELECT COUNT(*) AS total FROM tbl_pelanggan"
)->fetch_assoc()['total'];

// Total transaksi
$transaksi = (int)$conn->query(
    "SELECT COUNT(*) AS total FROM tbl_transaksi"
)->fetch_assoc()['total'];

// Total penjualan berdasarkan detail transaksi
$totalPenjualan = (float)$conn->query(
    "SELECT COALESCE(SUM(harga * jumlah), 0) AS total
     FROM tbl_detail_transaksi"
)->fetch_assoc()['total'];


// =====================================
// STOK RENDAH
// =====================================

$stokRendah = $conn->query(
    "SELECT nama_produk, stok
     FROM tbl_produk
     WHERE stok <= 5
     ORDER BY stok ASC"
);


// =====================================
// GRAFIK PENJUALAN 7 HARI TERAKHIR
// =====================================

$grafik = $conn->query(
    "SELECT
        DATE(t.tanggal) AS tanggal,
        COALESCE(SUM(d.harga * d.jumlah), 0) AS total
     FROM tbl_transaksi t
     LEFT JOIN tbl_detail_transaksi d
        ON d.id_transaksi = t.id_transaksi
     WHERE t.tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(t.tanggal)
     ORDER BY tanggal ASC"
);

$tanggal = [];
$penjualan = [];

while ($row = $grafik->fetch_assoc()) {
    $tanggal[] = date('d/m', strtotime($row['tanggal']));
    $penjualan[] = (float)$row['total'];
}


// =====================================
// TRANSAKSI TERBARU
// =====================================

$terbaru = $conn->query(
    "SELECT
        t.id_transaksi,
        t.tanggal,
        COALESCE(SUM(d.harga * d.jumlah), 0) AS total_hitung,
        p.nama_pelanggan
     FROM tbl_transaksi t
     LEFT JOIN tbl_pelanggan p
        ON p.id_pelanggan = t.id_pelanggan
     LEFT JOIN tbl_detail_transaksi d
        ON d.id_transaksi = t.id_transaksi
     GROUP BY
        t.id_transaksi,
        t.tanggal,
        p.nama_pelanggan
     ORDER BY t.id_transaksi DESC
     LIMIT 7"
);


// =====================================
// HEADER
// =====================================

$judul = "Dashboard Admin";
require_once "header.php";
?>

<!-- HEADER HALAMAN -->
<div class="page-header">
    <div>
        <h1>Dashboard Admin</h1>
        <p class="muted">
            Selamat datang di halaman pengelolaan TokoKu.
        </p>
    </div>

    <span class="badge">Administrator</span>
</div>


<!-- WELCOME CARD -->
<div class="welcome-card">
    <h2>
        Selamat datang, <?= e($_SESSION['nama'] ?? 'Admin') ?>!
    </h2>

    <p>
        Pantau aktivitas toko dan kelola produk, pelanggan,
        transaksi, serta laporan penjualan melalui dashboard ini.
    </p>
</div>


<!-- STATISTIK -->
<div class="stats-grid">

    <div class="stat-card">
        <div class="stat-icon">📦</div>
        <h3>Total Produk</h3>

        <strong>
            <?= number_format($produk, 0, ',', '.') ?>
        </strong>

        <p class="muted">Produk terdaftar</p>
    </div>


    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <h3>Total Pelanggan</h3>

        <strong>
            <?= number_format($pelanggan, 0, ',', '.') ?>
        </strong>

        <p class="muted">Pelanggan terdaftar</p>
    </div>


    <div class="stat-card">
        <div class="stat-icon">🧾</div>
        <h3>Total Transaksi</h3>

        <strong>
            <?= number_format($transaksi, 0, ',', '.') ?>
        </strong>

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


<!-- GRAFIK PENJUALAN -->
<div class="panel">

    <div class="panel-heading">
        <div>
            <h3>Grafik Penjualan</h3>
            <p class="muted">
                Penjualan selama 7 hari terakhir
            </p>
        </div>
    </div>

    <div style="position:relative; width:100%; height:320px;">
        <canvas id="salesChart"></canvas>
    </div>

</div>


<!-- TRANSAKSI TERBARU -->
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
                            <?= e(date(
                                'd-m-Y',
                                strtotime($row['tanggal'])
                            )) ?>
                        </td>

                        <td>
                            <?= e($row['nama_pelanggan'] ?? '-') ?>
                        </td>

                        <td>
                            <?= rupiah($row['total_hitung']) ?>
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


<!-- PERINGATAN STOK RENDAH -->
<div class="panel">

    <div class="panel-heading">
        <div>
            <h3>Peringatan Stok Produk</h3>
            <p class="muted">
                Produk dengan stok 5 atau kurang
            </p>
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
                        <span class="badge">
                            Semua stok aman
                        </span>
                    </td>
                </tr>

            <?php else: ?>

                <?php while ($row = $stokRendah->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <?= e($row['nama_produk']) ?>
                        </td>

                        <td>
                            <?= (int)$row['stok'] ?>
                        </td>

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


<!-- GRAFIK CHART.JS -->
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
