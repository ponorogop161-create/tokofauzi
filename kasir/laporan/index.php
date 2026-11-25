
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";

wajib_kasir();

$idUser = (int)($_SESSION['id_user'] ?? 0);

$judul = "Laporan Penjualan";

// Filter
$cari = trim($_GET['cari'] ?? '');
$tanggalMulai = trim($_GET['mulai'] ?? '');
$tanggalAkhir = trim($_GET['akhir'] ?? '');

$modeCetak = ($_GET['cetak'] ?? '') === '1';

$halaman = max(1, (int)($_GET['halaman'] ?? 1));
$batas = 10;
$offset = ($halaman - 1) * $batas;

// Validasi tanggal
function validasiTanggal($tanggal) {
    if ($tanggal === '') {
        return '';
    }

    $date = DateTime::createFromFormat('!Y-m-d', $tanggal);

    return ($date && $date->format('Y-m-d') === $tanggal)
        ? $tanggal
        : '';
}

$tanggalMulai = validasiTanggal($tanggalMulai);
$tanggalAkhir = validasiTanggal($tanggalAkhir);

// Kondisi query
$kondisi = " WHERE t.id_user = ?";
$types = "i";
$params = [$idUser];

if ($cari !== '') {
    $kondisi .= " AND (
        CAST(t.id_transaksi AS CHAR) LIKE ?
        OR COALESCE(p.nama_pelanggan, 'Pelanggan Umum') LIKE ?
    )";

    $keyword = "%{$cari}%";

    $types .= "ss";
    $params[] = $keyword;
    $params[] = $keyword;
}

if ($tanggalMulai !== '') {
    $kondisi .= " AND DATE(t.tanggal) >= ?";
    $types .= "s";
    $params[] = $tanggalMulai;
}

if ($tanggalAkhir !== '') {
    $kondisi .= " AND DATE(t.tanggal) <= ?";
    $types .= "s";
    $params[] = $tanggalAkhir;
}

// Helper prepared statement
function jalankanQueryLaporan($conn, $sql, $types, $params) {
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Query gagal: " . e($conn->error));
    }

    if ($types !== '') {
        $args = [$types];

        foreach ($params as $key => $value) {
            $args[] = &$params[$key];
        }

        call_user_func_array([$stmt, 'bind_param'], $args);
    }

    $stmt->execute();

    return $stmt;
}

// Statistik laporan
$sqlStatistik = "
    SELECT
        COUNT(*) AS jumlah_transaksi,

        COALESCE(SUM(
            CASE
                WHEN t.status_pembayaran = 'lunas'
                     AND t.status <> 'dibatalkan'
                THEN t.total_belanja
                ELSE 0
            END
        ), 0) AS total_penjualan,

        COALESCE(SUM(
            CASE
                WHEN t.status = 'selesai'
                THEN 1
                ELSE 0
            END
        ), 0) AS transaksi_selesai,

        COALESCE(SUM(
            CASE
                WHEN t.status = 'dibatalkan'
                THEN 1
                ELSE 0
            END
        ), 0) AS transaksi_dibatalkan

    FROM tbl_transaksi t

    LEFT JOIN tbl_pelanggan p
        ON t.id_pelanggan = p.id_pelanggan

    $kondisi
";

$stmtStatistik = jalankanQueryLaporan(
    $conn,
    $sqlStatistik,
    $types,
    $params
);

$statistik = $stmtStatistik->get_result()->fetch_assoc();
$stmtStatistik->close();

$totalData = (int)$statistik['jumlah_transaksi'];
$totalHalaman = max(1, (int)ceil($totalData / $batas));

if ($halaman > $totalHalaman) {
    $halaman = $totalHalaman;
    $offset = ($halaman - 1) * $batas;
}

// Ambil data transaksi
$sql = "
    SELECT
        t.id_transaksi,
        t.tanggal,
        t.total_belanja,
        t.metode_pembayaran,
        t.status,
        t.status_pembayaran,
        p.nama_pelanggan

    FROM tbl_transaksi t

    LEFT JOIN tbl_pelanggan p
        ON t.id_pelanggan = p.id_pelanggan

    $kondisi

    ORDER BY t.id_transaksi DESC
";

// Mode cetak menampilkan seluruh hasil filter
if (!$modeCetak) {
    $sql .= " LIMIT ? OFFSET ?";

    $typesData = $types . "ii";
    $paramsData = $params;
    $paramsData[] = $batas;
    $paramsData[] = $offset;
} else {
    $typesData = $types;
    $paramsData = $params;
}

$stmt = jalankanQueryLaporan(
    $conn,
    $sql,
    $typesData,
    $paramsData
);

$transaksi = $stmt->get_result();

// Nama kasir
$namaKasir = $_SESSION['nama'] ?? 'Kasir';

// Jika mode cetak
if ($modeCetak):
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Laporan Kasir | TokoKu</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            color: #222;
            font-size: 12px;
            margin: 25px;
        }

        .kop {
            text-align: center;
            border-bottom: 3px solid #222;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .kop h1 {
            margin: 0 0 5px;
            font-size: 24px;
        }

        .kop p {
            margin: 3px;
        }

        .judul {
            text-align: center;
            margin-bottom: 20px;
        }

        .info {
            margin-bottom: 15px;
            line-height: 1.8;
        }

        .statistik {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .statistik div {
            flex: 1;
            border: 1px solid #999;
            padding: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #555;
            padding: 8px;
            text-align: left;
        }

        th {
            background: #eee;
            font-weight: bold;
        }

        .total {
            text-align: right;
            margin-top: 15px;
            font-size: 14px;
        }

        .ttd {
            margin-top: 55px;
            display: flex;
            justify-content: flex-end;
            text-align: center;
        }

        .ttd div {
            width: 220px;
        }

        .ttd .nama {
            margin-top: 65px;
            font-weight: bold;
            text-decoration: underline;
        }

        @media print {
            body {
                margin: 10mm;
            }

            .no-print {
                display: none !important;
            }
        }
    </style>
</head>

<body onload="window.print()">

<div class="kop">
    <h1>TOKOKU</h1>
    <p>LAPORAN PENJUALAN KASIR</p>
    <p>Laporan transaksi penjualan</p>
</div>

<div class="judul">
    <h3>LAPORAN TRANSAKSI PENJUALAN</h3>
</div>

<div class="info">
    <strong>Nama Kasir:</strong> <?= e($namaKasir) ?><br>

    <strong>Periode:</strong>
    <?= $tanggalMulai ? e(date('d-m-Y', strtotime($tanggalMulai))) : 'Semua tanggal' ?>
    s/d
    <?= $tanggalAkhir ? e(date('d-m-Y', strtotime($tanggalAkhir))) : 'Sekarang' ?><br>

    <strong>Tanggal Cetak:</strong> <?= date('d-m-Y H:i') ?>
</div>

<div class="statistik">
    <div>
        <strong>Jumlah Transaksi</strong><br>
        <?= $totalData ?>
    </div>

    <div>
        <strong>Transaksi Selesai</strong><br>
        <?= (int)$statistik['transaksi_selesai'] ?>
    </div>

    <div>
        <strong>Transaksi Dibatalkan</strong><br>
        <?= (int)$statistik['transaksi_dibatalkan'] ?>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>No.</th>
            <th>ID Transaksi</th>
            <th>Tanggal</th>
            <th>Pelanggan</th>
            <th>Metode Pembayaran</th>
            <th>Status Pembayaran</th>
            <th>Status Transaksi</th>
            <th>Total</th>
        </tr>
    </thead>

    <tbody>
        <?php if ($transaksi->num_rows > 0): ?>
            <?php $no = 1; ?>

            <?php while ($row = $transaksi->fetch_assoc()): ?>
                <tr>
                    <td><?= $no++ ?></td>

                    <td>#<?= (int)$row['id_transaksi'] ?></td>

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
                        <?= e($row['metode_pembayaran'] ?? '-') ?>
                    </td>

                    <td>
                        <?= e(ucfirst(
                            str_replace(
                                '_',
                                ' ',
                                $row['status_pembayaran'] ?? '-'
                            )
                        )) ?>
                    </td>

                    <td>
                        <?= e(ucfirst($row['status'] ?? '-')) ?>
                    </td>

                    <td>
                        <?= rupiah($row['total_belanja']) ?>
                    </td>
                </tr>
            <?php endwhile; ?>

        <?php else: ?>
            <tr>
                <td colspan="8" style="text-align:center">
                    Tidak ada transaksi.
                </td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="total">
    <strong>
        Total Penjualan (Pembayaran Lunas):
        <?= rupiah($statistik['total_penjualan']) ?>
    </strong>
</div>

<div class="ttd">
    <div>
        <p>Kasir,</p>
        <p class="nama"><?= e($namaKasir) ?></p>
    </div>
</div>

</body>
</html>

<?php
$stmt->close();
exit;
endif;

// Tampilan utama
require_once "../header.php";
?>

<div class="kasir-page-header">
    <div>
        <h1>Laporan Penjualan</h1>
        <p>Laporan transaksi penjualan yang kamu lakukan.</p>
    </div>

    <?php
    $parameterCetak = [
        'cari' => $cari,
        'mulai' => $tanggalMulai,
        'akhir' => $tanggalAkhir,
        'cetak' => 1
    ];
    ?>

    <a href="?<?= e(http_build_query($parameterCetak)) ?>"
       target="_blank"
       class="kasir-btn">
        Cetak Laporan
    </a>
</div>

<!-- STATISTIK -->
<div class="kasir-stats">

    <div class="kasir-stat-card">
        <div class="kasir-stat-icon">▤</div>
        <div>
            <p>Jumlah Transaksi</p>
            <h2><?= $totalData ?></h2>
        </div>
    </div>

    <div class="kasir-stat-card">
        <div class="kasir-stat-icon">✓</div>
        <div>
            <p>Transaksi Selesai</p>
            <h2><?= (int)$statistik['transaksi_selesai'] ?></h2>
        </div>
    </div>

    <div class="kasir-stat-card">
        <div class="kasir-stat-icon">Rp</div>
        <div>
            <p>Total Penjualan</p>
            <h2><?= rupiah($statistik['total_penjualan']) ?></h2>
        </div>
    </div>

    <div class="kasir-stat-card">
        <div class="kasir-stat-icon">×</div>
        <div>
            <p>Dibatalkan</p>
            <h2><?= (int)$statistik['transaksi_dibatalkan'] ?></h2>
        </div>
    </div>

</div>

<!-- FILTER -->
<div class="kasir-panel">
    <div class="kasir-panel-header">
        <div>
            <h3>Filter Laporan</h3>
            <p>Cari transaksi berdasarkan nomor, pelanggan, atau tanggal.</p>
        </div>
    </div>

    <form method="GET" class="laporan-filter">

        <div>
            <label for="cari">Pencarian</label>
            <input
                type="text"
                id="cari"
                name="cari"
                value="<?= e($cari) ?>"
                placeholder="ID transaksi atau nama pelanggan"
            >
        </div>

        <div>
            <label for="mulai">Dari Tanggal</label>
            <input
                type="date"
                id="mulai"
                name="mulai"
                value="<?= e($tanggalMulai) ?>"
            >
        </div>

        <div>
            <label for="akhir">Sampai Tanggal</label>
            <input
                type="date"
                id="akhir"
                name="akhir"
                value="<?= e($tanggalAkhir) ?>"
            >
        </div>

        <div class="laporan-actions">
            <button type="submit" class="kasir-btn">
                Cari
            </button>

            <a href="index.php"
               class="kasir-btn kasir-btn-secondary">
                Reset
            </a>
        </div>

    </form>
</div>

<!-- TABEL LAPORAN -->
<div class="kasir-panel">

    <div class="kasir-panel-header">
        <div>
            <h3>Rincian Transaksi</h3>
            <p>
                Menampilkan <?= $transaksi->num_rows ?>
                dari <?= $totalData ?> transaksi.
            </p>
        </div>
    </div>

    <div class="kasir-table-wrap">
        <table class="kasir-table">

            <thead>
                <tr>
                    <th>No.</th>
                    <th>ID Transaksi</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>Metode Pembayaran</th>
                    <th>Status Pembayaran</th>
                    <th>Status Transaksi</th>
                    <th>Total</th>
                </tr>
            </thead>

            <tbody>

                <?php if ($transaksi->num_rows > 0): ?>

                    <?php $no = $offset + 1; ?>

                    <?php while ($row = $transaksi->fetch_assoc()): ?>

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
                                <?= e($row['metode_pembayaran'] ?? '-') ?>
                            </td>

                            <td>
                                <?= e(ucfirst(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $row['status_pembayaran'] ?? '-'
                                    )
                                )) ?>
                            </td>

                            <td>
                                <?= e(ucfirst($row['status'] ?? '-')) ?>
                            </td>

                            <td>
                                <strong>
                                    <?= rupiah($row['total_belanja']) ?>
                                </strong>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8" class="laporan-empty">
                            Belum ada transaksi pada periode ini.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>
        </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($totalHalaman > 1): ?>

        <div class="laporan-pagination">

            <?php
            $queryFilter = [
                'cari' => $cari,
                'mulai' => $tanggalMulai,
                'akhir' => $tanggalAkhir
            ];
            ?>

            <?php if ($halaman > 1): ?>
                <a href="?<?= e(http_build_query(
                    array_merge($queryFilter, [
                        'halaman' => $halaman - 1
                    ])
                )) ?>">
                    &laquo; Sebelumnya
                </a>
            <?php endif; ?>

            <span>
                Halaman <?= $halaman ?> dari <?= $totalHalaman ?>
            </span>

            <?php if ($halaman < $totalHalaman): ?>
                <a href="?<?= e(http_build_query(
                    array_merge($queryFilter, [
                        'halaman' => $halaman + 1
                    ])
                )) ?>">
                    Berikutnya &raquo;
                </a>
            <?php endif; ?>

        </div>

    <?php endif; ?>

</div>

<style>
.laporan-filter {
    display: grid;
    grid-template-columns: minmax(180px, 2fr) 1fr 1fr auto;
    gap: 14px;
    align-items: end;
    padding: 20px;
}

.laporan-filter label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 8px;
}

.laporan-filter input {
    width: 100%;
    height: 42px;
    padding: 0 12px;
    border: 1px solid #e1e7ef;
    border-radius: 8px;
    font-size: 12px;
    box-sizing: border-box;
}

.laporan-actions {
    display: flex;
    gap: 8px;
}

.laporan-empty {
    text-align: center;
    padding: 35px !important;
    color: #8994a5;
}

.laporan-pagination {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 15px;
    padding: 18px 20px;
    font-size: 12px;
}

.laporan-pagination a {
    color: #3978f6;
    text-decoration: none;
    font-weight: 600;
}

@media (max-width: 900px) {
    .laporan-filter {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 550px) {
    .laporan-filter {
        grid-template-columns: 1fr;
    }

    .laporan-actions {
        flex-wrap: wrap;
    }

    .laporan-pagination {
        justify-content: center;
        flex-wrap: wrap;
    }
}
</style>

<?php
$stmt->close();
require_once "../includes/footer.php";
?>
