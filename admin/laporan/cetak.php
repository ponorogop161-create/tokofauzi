
<?php
require_once __DIR__ . "/../../config/auth.php";
wajib_admin();

require_once __DIR__ . "/../../config/koneksi.php";

$dari = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');

function tanggalValidCetak($tanggal) {
    $date = DateTime::createFromFormat('Y-m-d', $tanggal);
    return $date && $date->format('Y-m-d') === $tanggal;
}

if (
    !tanggalValidCetak($dari) ||
    !tanggalValidCetak($sampai) ||
    $dari > $sampai
) {
    die("Periode tanggal tidak valid.");
}

$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS jumlah_transaksi,
        COALESCE(SUM(total_belanja), 0) AS total_pendapatan
    FROM tbl_transaksi
    WHERE DATE(tanggal) BETWEEN ? AND ?
");
$stmt->bind_param("ss", $dari, $sampai);
$stmt->execute();
$ringkasan = $stmt->get_result()->fetch_assoc();
$stmt->close();

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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Laporan Penjualan - TokoKu</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #222;
            margin: 35px;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #222;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }

        .header h1 {
            margin: 0 0 8px;
        }

        .summary {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
        }

        .summary div {
            border: 1px solid #ccc;
            padding: 15px;
            flex: 1;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            border: 1px solid #ccc;
            padding: 9px;
            text-align: left;
        }

        th {
            background: #eee;
        }

        .text-right {
            text-align: right;
        }

        .actions {
            margin-bottom: 20px;
        }

        @media print {
            .actions {
                display: none;
            }

            body {
                margin: 10mm;
            }
        }
    </style>
</head>
<body>

<div class="actions">
    <button onclick="window.print()">Cetak Laporan</button>
    <button onclick="window.close()">Tutup</button>
</div>

<div class="header">
    <h1>TOKOKU</h1>
    <p>LAPORAN PENJUALAN</p>
    <p>
        Periode:
        <?= date('d/m/Y', strtotime($dari)) ?>
        -
        <?= date('d/m/Y', strtotime($sampai)) ?>
    </p>
</div>

<div class="summary">
    <div>
        <strong>Jumlah Transaksi</strong>
        <h2><?= number_format($ringkasan['jumlah_transaksi']) ?></h2>
    </div>

    <div>
        <strong>Total Pendapatan</strong>
        <h2><?= rupiah($ringkasan['total_pendapatan']) ?></h2>
    </div>
</div>

<h3>Daftar Transaksi</h3>

<table>
    <thead>
        <tr>
            <th>No.</th>
            <th>ID Transaksi</th>
            <th>Tanggal</th>
            <th>Pelanggan</th>
            <th>Kasir / Pengguna</th>
            <th>Total Belanja</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($transaksi->num_rows > 0): ?>
            <?php $no = 1; ?>
            <?php while ($row = $transaksi->fetch_assoc()): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td>#<?= e($row['id_transaksi']) ?></td>
                    <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                    <td><?= e($row['nama_pelanggan'] ?? 'Umum') ?></td>
                    <td><?= e($row['nama_kasir'] ?? '-') ?></td>
                    <td class="text-right"><?= rupiah($row['total_belanja']) ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" style="text-align:center;">
                    Tidak ada transaksi.
                </td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<p style="margin-top:30px;text-align:right;">
    Dicetak pada <?= date('d/m/Y H:i') ?>
</p>

</body>
</html>

<?php
$stmt->close();
?>
