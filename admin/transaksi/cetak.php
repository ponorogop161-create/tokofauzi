
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";
wajib_admin();

$id = (int)($_GET['id'] ?? 0);

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        t.id_transaksi,
        t.tanggal,
        t.total_belanja,
        p.nama_pelanggan,
        u.nama AS nama_pengguna
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
    die("Transaksi tidak ditemukan.");
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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk #<?= $id ?> | TokoKu</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            padding: 25px;
            color: #222;
        }

        .receipt {
            width: 100%;
            max-width: 420px;
            margin: auto;
            background: white;
            padding: 25px;
            border: 1px solid #e5e7eb;
        }

        .center {
            text-align: center;
        }

        h2 {
            margin-bottom: 5px;
        }

        .line {
            border-top: 1px dashed #888;
            margin: 15px 0;
        }

        .info {
            font-size: 13px;
            line-height: 1.8;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        td {
            padding: 7px 0;
            vertical-align: top;
        }

        .right {
            text-align: right;
        }

        .total {
            font-size: 17px;
            font-weight: bold;
        }

        .actions {
            max-width: 420px;
            margin: 15px auto;
            display: flex;
            gap: 10px;
        }

        button, a {
            flex: 1;
            padding: 11px;
            border: 0;
            border-radius: 7px;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            font-size: 13px;
        }

        button {
            background: #2563eb;
            color: white;
        }

        a {
            background: #e2e8f0;
            color: #222;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .receipt {
                border: 0;
                max-width: none;
            }

            .actions {
                display: none;
            }
        }
    </style>
</head>

<body>

<div class="receipt">
    <div class="center">
        <h2>TOKOKU</h2>
        <p>Struk Pembelian</p>
    </div>

    <div class="line"></div>

    <div class="info">
        Nomor: #<?= $id ?><br>
        Tanggal: <?= e($transaksi['tanggal']) ?><br>
        Pelanggan: <?= e($transaksi['nama_pelanggan'] ?? 'Umum') ?><br>
        Petugas: <?= e($transaksi['nama_pengguna'] ?? '-') ?>
    </div>

    <div class="line"></div>

    <table>
        <?php while ($item = mysqli_fetch_assoc($detail)): ?>
            <tr>
                <td colspan="2">
                    <strong><?= e($item['nama_produk']) ?></strong>
                </td>
            </tr>
            <tr>
                <td>
                    <?= (int)$item['jumlah'] ?> x
                    <?= rupiah($item['harga']) ?>
                </td>
                <td class="right">
                    <?= rupiah($item['subtotal']) ?>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>

    <div class="line"></div>

    <table>
        <tr class="total">
            <td>Total Bayar</td>
            <td class="right">
                <?= rupiah($transaksi['total_belanja']) ?>
            </td>
        </tr>
    </table>

    <div class="line"></div>

    <div class="center info">
        Terima kasih telah berbelanja di TokoKu.<br>
        Semoga hari Anda menyenangkan!
    </div>
</div>

<div class="actions">
    <button onclick="window.print()">Cetak Struk</button>
    <a href="detail.php?id=<?= $id ?>">Kembali</a>
</div>

</body>
</html>
