
<?php
require_once __DIR__ . "/../../config/auth.php";
wajib_admin();

require_once __DIR__ . "/../../config/koneksi.php";

$judul = "Laporan Penjualan";

$dari = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');

function tanggalValid($tanggal) {
    $date = DateTime::createFromFormat('Y-m-d', $tanggal);
    return $date && $date->format('Y-m-d') === $tanggal;
}

if (!tanggalValid($dari) || !tanggalValid($sampai) || $dari > $sampai) {
    $dari = date('Y-m-01');
    $sampai = date('Y-m-d');
}

// Ringkasan laporan
$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS jumlah_transaksi,
        COALESCE(SUM(total_belanja), 0) AS total_pendapatan,
        COALESCE(AVG(total_belanja), 0) AS rata_rata
    FROM tbl_transaksi
    WHERE DATE(tanggal) BETWEEN ? AND ?
");
$stmt->bind_param("ss", $dari, $sampai);
$stmt->execute();
$ringkasan = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Data grafik penjualan harian
$stmt = $conn->prepare("
    SELECT DATE(tanggal) AS tanggal,
           SUM(total_belanja) AS pendapatan
    FROM tbl_transaksi
    WHERE DATE(tanggal) BETWEEN ? AND ?
    GROUP BY DATE(tanggal)
    ORDER BY DATE(tanggal) ASC
");
$stmt->bind_param("ss", $dari, $sampai);
$stmt->execute();
$resultGrafik = $stmt->get_result();

$labels = [];
$dataGrafik = [];

while ($row = $resultGrafik->fetch_assoc()) {
    $labels[] = date('d/m/Y', strtotime($row['tanggal']));
    $dataGrafik[] = (float) $row['pendapatan'];
}
$stmt->close();

// Produk terlaris
$stmt = $conn->prepare("
    SELECT
        d.nama_produk,
        SUM(d.jumlah) AS total_terjual,
        SUM(d.subtotal) AS total_pendapatan
    FROM tbl_detail_transaksi d
    INNER JOIN tbl_transaksi t
        ON t.id_transaksi = d.id_transaksi
    WHERE DATE(t.tanggal) BETWEEN ? AND ?
    GROUP BY d.nama_produk
    ORDER BY total_terjual DESC
    LIMIT 10
");
$stmt->bind_param("ss", $dari, $sampai);
$stmt->execute();
$produkTerlaris = $stmt->get_result();
$stmt->close();

// Daftar transaksi
$stmt = $conn->prepare("
    SELECT
        t.id_transaksi,
        t.tanggal,
        t.total_belanja,
        p.nama_pelanggan,
        u.nama AS nama_kasir
    FROM tbl_transaksi t
    LEFT JOIN tbl_pelanggan p
        ON p.id_pelanggan = t.id_pelanggan
    LEFT JOIN tbl_users u
        ON u.id_user = t.id_user
    WHERE DATE(t.tanggal) BETWEEN ? AND ?
    ORDER BY t.tanggal DESC
");
$stmt->bind_param("ss", $dari, $sampai);
$stmt->execute();
$transaksi = $stmt->get_result();
$stmt->close();

require_once __DIR__ . "/../header.php";
?>

<div class="page-header">
    <div>
        <h2>Laporan Penjualan</h2>
        <p>Ringkasan dan analisis transaksi penjualan TokoKu.</p>
    </div>

    <div class="action-buttons">
        <a class="btn btn-primary"
           href="export.php?dari=<?= urlencode($dari) ?>&sampai=<?= urlencode($sampai) ?>">
            Ekspor CSV
        </a>

        <a class="btn btn-secondary"
           href="cetak.php?dari=<?= urlencode($dari) ?>&sampai=<?= urlencode($sampai) ?>"
           target="_blank">
            Cetak Laporan
        </a>
    </div>
</div>

<div class="panel" style="margin-bottom:20px;">
    <form method="GET" class="search-form"
          style="display:flex;gap:15px;align-items:end;flex-wrap:wrap;">

        <div>
            <label>Dari Tanggal</label>
            <input type="date" name="dari"
                   value="<?= e($dari) ?>" required>
        </div>

        <div>
            <label>Sampai Tanggal</label>
            <input type="date" name="sampai"
                   value="<?= e($sampai) ?>" required>
        </div>

        <button type="submit" class="btn btn-primary">
            Tampilkan
        </button>

        <a href="index.php" class="btn btn-secondary">
            Reset
        </a>
    </form>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-info">
            <span>Jumlah Transaksi</span>
            <h3><?= number_format($ringkasan['jumlah_transaksi']) ?></h3>
        </div>
        <div class="stat-icon">🧾</div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span>Total Pendapatan</span>
            <h3><?= rupiah($ringkasan['total_pendapatan']) ?></h3>
        </div>
        <div class="stat-icon">💰</div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span>Rata-rata Transaksi</span>
            <h3><?= rupiah($ringkasan['rata_rata']) ?></h3>
        </div>
        <div class="stat-icon">📊</div>
    </div>
</div>

<div class="panel" style="margin-top:25px;">
    <div class="card-header">
        <div>
            <h3>Grafik Penjualan Harian</h3>
            <p>Perkembangan pendapatan berdasarkan tanggal.</p>
        </div>
    </div>

    <div style="height:320px;position:relative;">
        <canvas id="grafikPenjualan"></canvas>
    </div>
</div>

<div class="panel" style="margin-top:25px;">
    <div class="card-header">
        <div>
            <h3>10 Produk Terlaris</h3>
            <p>Produk dengan jumlah penjualan terbanyak.</p>
        </div>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Nama Produk</th>
                    <th>Jumlah Terjual</th>
                    <th>Total Pendapatan</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($produkTerlaris->num_rows > 0): ?>
                    <?php $no = 1; ?>
                    <?php while ($produk = $produkTerlaris->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= e($produk['nama_produk']) ?></td>
                            <td><?= number_format($produk['total_terjual']) ?></td>
                            <td><?= rupiah($produk['total_pendapatan']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align:center;">
                            Belum ada data penjualan produk.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="panel" style="margin-top:25px;">
    <div class="card-header">
        <div>
            <h3>Riwayat Transaksi</h3>
            <p>Daftar transaksi berdasarkan periode laporan.</p>
        </div>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID Transaksi</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>Kasir / Pengguna</th>
                    <th>Total Belanja</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($transaksi->num_rows > 0): ?>
                    <?php while ($row = $transaksi->fetch_assoc()): ?>
                        <tr>
                            <td>#<?= e($row['id_transaksi']) ?></td>
                            <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                            <td><?= e($row['nama_pelanggan'] ?? 'Umum') ?></td>
                            <td><?= e($row['nama_kasir'] ?? '-') ?></td>
                            <td><?= rupiah($row['total_belanja']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align:center;">
                            Tidak ada transaksi pada periode ini.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const labelsPenjualan = <?= json_encode($labels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const dataPenjualan = <?= json_encode($dataGrafik) ?>;

const canvas = document.getElementById('grafikPenjualan');

new Chart(canvas, {
    type: 'line',
    data: {
        labels: labelsPenjualan,
        datasets: [{
            label: 'Pendapatan',
            data: dataPenjualan,
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.12)',
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
                display: true
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Pendapatan: Rp ' +
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
</script>

<?php require_once __DIR__ . "/../../includes/footer.php"; ?>
