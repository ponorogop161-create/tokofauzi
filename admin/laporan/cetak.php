
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

// Data transaksi
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
    ORDER BY t.tanggal ASC, t.id_transaksi ASC
");
$stmt->bind_param("ss", $dari, $sampai);
$stmt->execute();
$transaksi = $stmt->get_result();

// Identitas laporan
$nomorLaporan = "LAP/" . date('Ymd') . "/TK";
$tanggalCetak = date('d/m/Y H:i');
$jumlahTransaksi = (int)$ringkasan['jumlah_transaksi'];
$totalPendapatan = (float)$ringkasan['total_pendapatan'];
$rataRata = (float)$ringkasan['rata_rata'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan TokoKu</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #222;
            background: #e9edf2;
            margin: 0;
            padding: 25px;
        }

        .actions {
            max-width: 210mm;
            margin: 0 auto 15px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn {
            border: none;
            padding: 10px 17px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
        }

        .btn-print {
            background: #2563eb;
            color: white;
        }

        .btn-close {
            background: #64748b;
            color: white;
        }

        .document {
            width: 210mm;
            min-height: 297mm;
            margin: auto;
            padding: 18mm 17mm;
            background: white;
            box-shadow: 0 2px 12px rgba(0,0,0,.12);
        }

        /* Kop laporan */
        .company-header {
            display: flex;
            align-items: center;
            gap: 15px;
            padding-bottom: 13px;
            border-bottom: 3px double #222;
        }

        .company-logo {
            width: 65px;
            height: 65px;
            border: 2px solid #222;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: bold;
            flex-shrink: 0;
        }

        .company-info h1 {
            font-size: 23px;
            margin: 0 0 5px;
            letter-spacing: 2px;
        }

        .company-info p {
            margin: 3px 0;
            font-size: 10px;
            color: #444;
        }

        .report-title {
            text-align: center;
            margin: 25px 0 20px;
        }

        .report-title h2 {
            font-size: 17px;
            margin: 0 0 7px;
            letter-spacing: 1px;
        }

        .report-title p {
            margin: 0;
            font-size: 11px;
        }

        /* Informasi laporan */
        .report-info {
            width: 100%;
            margin-bottom: 20px;
        }

        .report-info td {
            border: none;
            padding: 4px 0;
            vertical-align: top;
        }

        .report-info td:first-child {
            width: 125px;
        }

        /* Ringkasan */
        .section-title {
            font-size: 12px;
            font-weight: bold;
            margin: 20px 0 10px;
            padding-bottom: 6px;
            border-bottom: 1px solid #555;
        }

        .summary {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .summary-card {
            flex: 1;
            border: 1px solid #bbb;
            padding: 12px;
            min-height: 65px;
        }

        .summary-card span {
            display: block;
            font-size: 10px;
            color: #555;
            margin-bottom: 8px;
        }

        .summary-card strong {
            font-size: 15px;
        }

        /* Tabel transaksi */
        table.transaction-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .transaction-table th,
        .transaction-table td {
            border: 1px solid #777;
            padding: 8px 7px;
            font-size: 10px;
        }

        .transaction-table th {
            background: #e9edf2;
            text-align: center;
            font-weight: bold;
        }

        .transaction-table td {
            vertical-align: middle;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .total-row td {
            font-weight: bold;
            background: #f1f3f5;
        }

        /* Keterangan */
        .note {
            margin-top: 15px;
            font-size: 10px;
            line-height: 1.6;
        }

        /* Tanda tangan */
        .signature {
            display: flex;
            justify-content: space-between;
            margin-top: 45px;
            page-break-inside: avoid;
        }

        .signature-box {
            width: 42%;
            text-align: center;
            font-size: 11px;
        }

        .signature-space {
            height: 65px;
        }

        .signature-name {
            font-weight: bold;
            text-decoration: underline;
        }

        .footer {
            margin-top: 35px;
            padding-top: 8px;
            border-top: 1px solid #aaa;
            text-align: center;
            font-size: 9px;
            color: #555;
        }

        @page {
            size: A4 portrait;
            margin: 0;
        }

        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .actions {
                display: none !important;
            }

            .document {
                width: 210mm;
                min-height: 297mm;
                margin: 0;
                padding: 18mm 17mm;
                box-shadow: none;
            }

            thead {
                display: table-header-group;
            }

            tr {
                page-break-inside: avoid;
            }

            .summary-card,
            .transaction-table th,
            .total-row td {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>

<div class="actions">
    <button class="btn btn-print" onclick="window.print()">
        Cetak / Simpan PDF
    </button>

    <button class="btn btn-close" onclick="window.close()">
        Tutup
    </button>
</div>

<div class="document">

    <!-- KOP PERUSAHAAN -->
    <div class="company-header">
        <div class="company-logo">TK</div>

        <div class="company-info">
            <h1>TOKOKU</h1>
            <p><strong>LAPORAN ADMINISTRASI PENJUALAN</strong></p>
            <p>Dokumen resmi rekapitulasi transaksi penjualan</p>
        </div>
    </div>

    <!-- JUDUL -->
    <div class="report-title">
        <h2>LAPORAN PENJUALAN</h2>
        <p>PERIODE
            <?= date('d F Y', strtotime($dari)) ?>
            SAMPAI
            <?= date('d F Y', strtotime($sampai)) ?>
        </p>
    </div>

    <!-- INFORMASI LAPORAN -->
    <table class="report-info">
        <tr>
            <td>Nomor Laporan</td>
            <td>: <?= e($nomorLaporan) ?></td>
        </tr>
        <tr>
            <td>Tanggal Pembuatan</td>
            <td>: <?= e($tanggalCetak) ?></td>
        </tr>
        <tr>
            <td>Periode Laporan</td>
            <td>: <?= date('d/m/Y', strtotime($dari)) ?> - <?= date('d/m/Y', strtotime($sampai)) ?></td>
        </tr>
        <tr>
            <td>Status Dokumen</td>
            <td>: Laporan Penjualan</td>
        </tr>
    </table>

    <!-- RINGKASAN -->
    <div class="section-title">
        I. RINGKASAN PENJUALAN
    </div>

    <div class="summary">
        <div class="summary-card">
            <span>Jumlah Transaksi</span>
            <strong><?= number_format($jumlahTransaksi, 0, ',', '.') ?></strong>
        </div>

        <div class="summary-card">
            <span>Total Pendapatan</span>
            <strong><?= rupiah($totalPendapatan) ?></strong>
        </div>

        <div class="summary-card">
            <span>Rata-rata Transaksi</span>
            <strong><?= rupiah($rataRata) ?></strong>
        </div>
    </div>

    <!-- DAFTAR TRANSAKSI -->
    <div class="section-title">
        II. RINCIAN TRANSAKSI PENJUALAN
    </div>

    <table class="transaction-table">
        <thead>
            <tr>
                <th style="width:35px;">No.</th>
                <th style="width:75px;">ID Transaksi</th>
                <th style="width:85px;">Tanggal</th>
                <th>Pelanggan</th>
                <th>Kasir / Pengguna</th>
                <th style="width:105px;">Total Belanja</th>
            </tr>
        </thead>

        <tbody>
            <?php if ($transaksi->num_rows > 0): ?>
                <?php $no = 1; ?>

                <?php while ($row = $transaksi->fetch_assoc()): ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>

                        <td class="text-center">
                            #<?= (int)$row['id_transaksi'] ?>
                        </td>

                        <td class="text-center">
                            <?= date('d/m/Y', strtotime($row['tanggal'])) ?>
                        </td>

                        <td>
                            <?= e($row['nama_pelanggan'] ?? 'Umum') ?>
                        </td>

                        <td>
                            <?= e($row['nama_kasir'] ?? '-') ?>
                        </td>

                        <td class="text-right">
                            <?= rupiah($row['total_belanja']) ?>
                        </td>
                    </tr>
                <?php endwhile; ?>

                <tr class="total-row">
                    <td colspan="5" class="text-right">
                        TOTAL PENDAPATAN
                    </td>
                    <td class="text-right">
                        <?= rupiah($totalPendapatan) ?>
                    </td>
                </tr>

            <?php else: ?>
                <tr>
                    <td colspan="6" class="text-center">
                        Tidak ada transaksi pada periode ini.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- KETERANGAN -->
    <div class="note">
        <strong>Keterangan:</strong><br>
        Laporan ini dibuat berdasarkan data transaksi yang tersimpan
        dalam database TokoKu sesuai periode yang telah dipilih.
        Dokumen ini digunakan sebagai bahan pemeriksaan dan evaluasi
        penjualan.
    </div>

    <!-- TANDA TANGAN -->
    <div class="signature">
        <div class="signature-box">
            <p>Mengetahui,</p>
            <p>Penanggung Jawab</p>
            <div class="signature-space"></div>
            <p class="signature-name">( ................................ )</p>
        </div>

        <div class="signature-box">
            <p>
                Dibuat pada
                <?= date('d/m/Y') ?>
            </p>
            <p>Admin TokoKu</p>
            <div class="signature-space"></div>
            <p class="signature-name">( ................................ )</p>
        </div>
    </div>

    <div class="footer">
        TOKOKU | Dokumen Laporan Penjualan | Dicetak pada <?= e($tanggalCetak) ?>
    </div>

</div>

<script>
window.addEventListener('load', function () {
    // Membuka dialog cetak secara otomatis
    setTimeout(function () {
        window.print();
    }, 500);
});
</script>

<?php
$stmt->close();
?>

</body>
</html>
