
<?php
require_once "../config/koneksi.php";
require_once "../config/auth.php";

wajib_kasir();

$idUser = (int)($_SESSION['id_user'] ?? 0);

// Transaksi hari ini
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM tbl_transaksi
    WHERE id_user = ?
    AND DATE(tanggal) = CURDATE()
");

$stmt->bind_param("i", $idUser);
$stmt->execute();

$totalTransaksi = (int)$stmt
    ->get_result()
    ->fetch_assoc()['total'];

$stmt->close();


// =====================================
// OMZET HARI INI
// Berdasarkan harga x jumlah
// =====================================

$stmt = $conn->prepare("
    SELECT COALESCE(SUM(d.harga * d.jumlah), 0) AS total
    FROM tbl_transaksi t
    LEFT JOIN tbl_detail_transaksi d
        ON d.id_transaksi = t.id_transaksi
    WHERE t.id_user = ?
    AND DATE(t.tanggal) = CURDATE()
");

$stmt->bind_param("i", $idUser);
$stmt->execute();

$omzetHariIni = (float)$stmt
    ->get_result()
    ->fetch_assoc()['total'];

$stmt->close();


// =====================================
// SEMUA TRANSAKSI KASIR
// =====================================

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM tbl_transaksi
    WHERE id_user = ?
");

$stmt->bind_param("i", $idUser);
$stmt->execute();

$totalSemua = (int)$stmt
    ->get_result()
    ->fetch_assoc()['total'];

$stmt->close();


// =====================================
// GRAFIK OMZET 7 HARI TERAKHIR
// =====================================

$omzetGrafik = array_fill(0, 7, 0);
$labelGrafik = [];

for ($i = 6; $i >= 0; $i--) {
    $tanggal = date('Y-m-d', strtotime("-{$i} days"));
    $labelGrafik[] = date('d/m', strtotime($tanggal));
}

$stmt = $conn->prepare("
    SELECT
        DATE(t.tanggal) AS tanggal,
        COALESCE(SUM(d.harga * d.jumlah), 0) AS total
    FROM tbl_transaksi t
    LEFT JOIN tbl_detail_transaksi d
        ON d.id_transaksi = t.id_transaksi
    WHERE t.id_user = ?
    AND DATE(t.tanggal) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(t.tanggal)
    ORDER BY tanggal ASC
");

$stmt->bind_param("i", $idUser);
$stmt->execute();
$resultGrafik = $stmt->get_result();

while ($row = $resultGrafik->fetch_assoc()) {
    $selisihHari = (int)((strtotime(date('Y-m-d')) - strtotime($row['tanggal'])) / 86400);
    $index = 6 - $selisihHari;

    if ($index >= 0 && $index < 7) {
        $omzetGrafik[$index] = (float)$row['total'];
    }
}

$stmt->close();


// =====================================
// TRANSAKSI TERBARU
// =====================================

$stmt = $conn->prepare("
    SELECT
        t.id_transaksi,
        t.tanggal,
        COALESCE(SUM(d.harga * d.jumlah), 0) AS total_hitung,
        p.nama_pelanggan
    FROM tbl_transaksi t

    LEFT JOIN tbl_pelanggan p
        ON t.id_pelanggan = p.id_pelanggan

    LEFT JOIN tbl_detail_transaksi d
        ON d.id_transaksi = t.id_transaksi

    WHERE t.id_user = ?

    GROUP BY
        t.id_transaksi,
        t.tanggal,
        p.nama_pelanggan

    ORDER BY t.id_transaksi DESC
    LIMIT 5
");

$stmt->bind_param("i", $idUser);
$stmt->execute();

$transaksiTerbaru = $stmt->get_result();


// =====================================
// HEADER
// =====================================

$judul = "Dashboard Kasir";
require_once "header.php";
?>


<!-- HEADER HALAMAN -->
<div class="kasir-page-header">

    <div>
        <h1>Dashboard Kasir</h1>

        <p>
            Selamat datang,
            <?= e($_SESSION['nama'] ?? 'Kasir') ?>.
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

            <h2>
                <?= number_format($totalTransaksi, 0, ',', '.') ?>
            </h2>
        </div>

    </div>


    <div class="kasir-stat-card">

        <div class="kasir-stat-icon">Rp</div>

        <div>
            <p>Omzet Hari Ini</p>

            <h2>
                <?= rupiah($omzetHariIni) ?>
            </h2>
        </div>

    </div>


    <div class="kasir-stat-card">

        <div class="kasir-stat-icon">▣</div>

        <div>
            <p>Total Transaksi</p>

            <h2>
                <?= number_format($totalSemua, 0, ',', '.') ?>
            </h2>
        </div>

    </div>

</div>


<!-- GRAFIK OMZET -->
<div class="kasir-panel">

    <div class="kasir-panel-header">
        <div>
            <h3>Grafik Omzet</h3>
            <p>Omzet penjualan selama 7 hari terakhir.</p>
        </div>
    </div>

    <div class="kasir-chart-wrap">
        <canvas id="omzetKasirChart"></canvas>
    </div>

</div>


<!-- TRANSAKSI TERBARU -->
<div class="kasir-panel">

    <div class="kasir-panel-header">

        <div>
            <h3>Transaksi Terbaru</h3>

            <p>
                Lima transaksi terakhir yang kamu lakukan.
            </p>
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

                            <td>
                                <?= $no++ ?>
                            </td>


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
                                <?= e(
                                    $row['nama_pelanggan']
                                    ?? 'Pelanggan Umum'
                                ) ?>
                            </td>


                            <td>
                                <strong>
                                    <?= rupiah($row['total_hitung']) ?>
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
                        <td colspan="6"
                            style="text-align:center; padding:30px;">
                            Belum ada transaksi.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- GRAFIK CHART.JS -->
<script>
const omzetCanvas = document.getElementById('omzetKasirChart');

if (omzetCanvas && typeof Chart !== 'undefined') {
    new Chart(omzetCanvas, {
        type: 'line',
        data: {
            labels: <?= json_encode($labelGrafik, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            datasets: [{
                label: 'Omzet',
                data: <?= json_encode($omzetGrafik, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                borderColor: '#3978f6',
                backgroundColor: 'rgba(57, 120, 246, 0.12)',
                borderWidth: 3,
                fill: true,
                tension: 0.35,
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
                            return 'Omzet: Rp ' +
                                new Intl.NumberFormat('id-ID').format(context.raw);
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
                                new Intl.NumberFormat('id-ID').format(value);
                        }
                    }
                }
            }
        }
    });
}
</script>


<?php
$stmt->close();
require_once __DIR__ . "/includes/footer.php";
?>
