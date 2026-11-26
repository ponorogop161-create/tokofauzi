
<?php
require_once __DIR__ . "/../../config/koneksi.php";
require_once __DIR__ . "/../../config/auth.php";

wajib_user();

$idUser = (int)($_SESSION['id_user'] ?? 0);
$idTransaksi = (int)($_GET['id'] ?? 0);

if ($idTransaksi <= 0) {
    die("Nomor transaksi tidak valid.");
}

// =====================================
// AMBIL DATA TRANSAKSI
// =====================================

$stmt = $conn->prepare("
    SELECT
        t.id_transaksi,
        t.tanggal,
        t.total_belanja,
        t.ppn,
        t.no_hp,
        t.alamat_pengiriman,
        t.metode_pembayaran,
        t.status_pembayaran,
        t.status_pesanan,
        t.duitku_payment_code,
        p.nama_pelanggan,
        c.nama_cabang,
        c.kota AS kota_cabang
    FROM tbl_transaksi t
    LEFT JOIN tbl_pelanggan p
        ON t.id_pelanggan = p.id_pelanggan
    LEFT JOIN tbl_cabang c
        ON t.id_cabang = c.id_cabang
    WHERE t.id_transaksi = ?
      AND t.id_user = ?
    LIMIT 1
");

$stmt->bind_param("ii", $idTransaksi, $idUser);
$stmt->execute();

$transaksi = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$transaksi) {
    die("Transaksi tidak ditemukan atau bukan milik akun Anda.");
}

$ppn = (float)($transaksi['ppn'] ?? 0);
$subtotalBelanja = max(0, (float)$transaksi['total_belanja'] - $ppn);

// =====================================
// AMBIL DETAIL PRODUK
// =====================================

$stmt = $conn->prepare("
    SELECT
        nama_produk,
        jumlah,
        harga,
        subtotal
    FROM tbl_detail_transaksi
    WHERE id_transaksi = ?
");

$stmt->bind_param("i", $idTransaksi);
$stmt->execute();

$detail = $stmt->get_result();

$stmt->close();

// =====================================
// FUNGSI STATUS
// =====================================

function statusLabel($status)
{
    return ucwords(
        str_replace('_', ' ', (string)$status)
    );
}

// =====================================
// METODE PEMBAYARAN UMUM
// =====================================

$metode = [
    'QRIS'   => 'QRIS',
    'COD'    => 'COD (Bayar saat barang diterima)',
    'KASIR'  => 'Bayar langsung ke kasir toko',
    'DUITKU' => 'Duitku'
];

// =====================================
// DAFTAR METODE PEMBAYARAN DUITKU
// =====================================

$metodeDuitku = [
    // Virtual Account
    'BR' => 'Duitku (BRIVA / BRI Virtual Account)',
    'BC' => 'Duitku (BCA Virtual Account)',
    'M2' => 'Duitku (Mandiri Virtual Account)',
    'I1' => 'Duitku (BNI Virtual Account)',
    'B1' => 'Duitku (CIMB Niaga Virtual Account)',
    'BT' => 'Duitku (Permata Virtual Account)',
    'VA' => 'Duitku (Maybank Virtual Account)',
    'BV' => 'Duitku (BSI Virtual Account)',
    'A1' => 'Duitku (ATM Bersama)',
    'AG' => 'Duitku (Bank Artha Graha)',
    'NC' => 'Duitku (Bank Neo Commerce)',
    'S1' => 'Duitku (Bank Sahabat Sampoerna)',
    'DM' => 'Duitku (Danamon Virtual Account)',

    // E-Wallet
    'DA' => 'Duitku (DANA)',
    'OV' => 'Duitku (OVO)',
    'SA' => 'Duitku (ShopeePay)',
    'LF' => 'Duitku (LinkAja)',
    'LA' => 'Duitku (LinkAja)',

    // Retail
    'IR' => 'Duitku (Indomaret)',
    'FT' => 'Duitku (Alfamart / Pegadaian / Pos)',

    // Kartu dan layanan lainnya
    'VC' => 'Duitku (Kartu Kredit)',
    'JP' => 'Duitku (Jenius Pay)',
    'DN' => 'Duitku (Indodana PayLater)',
    'AT' => 'Duitku (Atome)',

    // QRIS
    'QR' => 'Duitku (QRIS)',
    'SP' => 'Duitku (QRIS ShopeePay)',
    'NQ' => 'Duitku (QRIS)',
    'SQ' => 'Duitku (QRIS Nusapay)'
];

// =====================================
// TENTUKAN NAMA METODE PEMBAYARAN
// =====================================

$kodeDuitku = strtoupper(
    trim($transaksi['duitku_payment_code'] ?? '')
);

$namaMetode = $metode[
    $transaksi['metode_pembayaran']
] ?? $transaksi['metode_pembayaran'];

if (
    $transaksi['metode_pembayaran'] === 'DUITKU' &&
    $kodeDuitku !== ''
) {
    $namaMetode = $metodeDuitku[$kodeDuitku]
        ?? 'Duitku (' . $kodeDuitku . ')';
} elseif ($transaksi['metode_pembayaran'] === 'DUITKU') {
    $namaMetode = 'Duitku (QRIS)';
}

// =====================================
// FORMAT STATUS PEMBAYARAN
// =====================================

$statusPembayaran = strtolower(
    trim($transaksi['status_pembayaran'] ?? '')
);

$statusClass = 'status-pending';

if ($statusPembayaran === 'lunas') {
    $statusClass = 'status-success';
} elseif (
    in_array(
        $statusPembayaran,
        ['gagal', 'dibatalkan', 'kadaluarsa'],
        true
    )
) {
    $statusClass = 'status-failed';
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>
    Struk Pesanan #<?= (int)$idTransaksi ?> | TokoKu
</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 25px;
    background: #eef2f7;
    font-family: Arial, sans-serif;
    color: #222;
}

.receipt {
    width: 100%;
    max-width: 430px;
    margin: 0 auto;
    padding: 25px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,.08);
}

.store {
    text-align: center;
}

.store h2 {
    margin: 0;
    font-size: 25px;
    letter-spacing: 2px;
    font-weight: 800;
}

.store p {
    margin: 6px 0;
    font-size: 12px;
    color: #555;
    font-weight: 600;
}

.line {
    border: 0;
    border-top: 1px dashed #999;
    margin: 17px 0;
}

.info {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    font-size: 12px;
    margin: 9px 0;
}

.info span:first-child {
    color: #555;
}

.info span:last-child {
    text-align: right;
    font-weight: 700;
}

.items {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

.items th {
    text-align: left;
    padding: 8px 3px;
    border-bottom: 1px dashed #999;
    font-weight: 800;
}

.items td {
    padding: 9px 3px;
    vertical-align: top;
    font-weight: 600;
}

.items .right {
    text-align: right;
}

.product-name {
    font-weight: 800;
}

.product-detail {
    color: #555;
    margin-top: 4px;
    font-size: 11px;
    font-weight: 600;
}

.total {
    display: flex;
    justify-content: space-between;
    font-size: 17px;
    font-weight: 800;
}

.total-row {
    display: flex;
    justify-content: space-between;
    margin: 7px 0;
    font-size: 13px;
    font-weight: 600;
}

.total-row span:last-child {
    text-align: right;
}

.payment {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 7px;
    padding: 12px;
    font-size: 12px;
    line-height: 1.8;
    font-weight: 600;
}

.payment-title {
    display: block;
    font-weight: 800;
    margin-bottom: 7px;
}

.payment-row {
    display: flex;
    justify-content: space-between;
    gap: 10px;
}

.payment-row span:first-child {
    color: #555;
}

.payment-row span:last-child {
    text-align: right;
    font-weight: 800;
}

.status {
    display: inline-block;
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 800;
}

.status-success {
    color: #166534;
    background: #dcfce7;
}

.status-pending {
    color: #92400e;
    background: #fef3c7;
}

.status-failed {
    color: #991b1b;
    background: #fee2e2;
}

.footer {
    text-align: center;
    font-size: 11px;
    color: #555;
    line-height: 1.7;
    font-weight: 600;
}

.actions {
    max-width: 430px;
    margin: 15px auto;
    display: flex;
    gap: 10px;
}

.actions button,
.actions a {
    flex: 1;
    border: 0;
    padding: 12px;
    border-radius: 7px;
    text-align: center;
    text-decoration: none;
    cursor: pointer;
    font-weight: 700;
    font-size: 13px;
}

.print {
    background: #2563eb;
    color: white;
}

.back {
    background: #e2e8f0;
    color: #334155;
}

@media print {
    body {
        padding: 0;
        background: white;
    }

    .receipt {
        max-width: none;
        box-shadow: none;
        border-radius: 0;
        padding: 8px;
    }

    .actions {
        display: none;
    }

    @page {
        size: 80mm auto;
        margin: 4mm;
    }
}
</style>

</head>

<body>

<div class="receipt">

    <!-- HEADER TOKO -->

    <div class="store">
        <h2>TOKOKU</h2>
        <p>STRUK PEMBELIAN ONLINE</p>
        <p>Terima kasih telah berbelanja di TokoKu</p>
    </div>

    <hr class="line">

    <!-- INFORMASI TRANSAKSI -->

    <div class="info">
        <span>No. Transaksi</span>
        <span>#<?= (int)$idTransaksi ?></span>
    </div>

    <div class="info">
        <span>Tanggal</span>
        <span>
            <?= date(
                'd/m/Y H:i',
                strtotime($transaksi['tanggal'])
            ) ?>
        </span>
    </div>

    <div class="info">
        <span>Pembeli</span>
        <span>
            <?= e($transaksi['nama_pelanggan'] ?? '-') ?>
        </span>
    </div>

    <div class="info">
        <span>No. HP</span>
        <span>
            <?= e($transaksi['no_hp'] ?? '-') ?>
        </span>
    </div>

    <hr class="line">

    <!-- DAFTAR PRODUK -->

    <table class="items">

        <thead>
            <tr>
                <th>Produk</th>
                <th class="right">Subtotal</th>
            </tr>
        </thead>

        <tbody>

        <?php while ($item = $detail->fetch_assoc()): ?>

            <tr>
                <td>
                    <div class="product-name">
                        <?= e($item['nama_produk']) ?>
                    </div>

                    <div class="product-detail">
                        <?= (int)$item['jumlah'] ?>
                        ×
                        <?= rupiah($item['harga']) ?>
                    </div>
                </td>

                <td class="right">
                    <?= rupiah($item['subtotal']) ?>
                </td>
            </tr>

        <?php endwhile; ?>

        </tbody>

    </table>

    <hr class="line">

    <!-- RINGKASAN TAGIHAN -->

    <div class="total-row">
        <span>Subtotal</span>
        <span><?= rupiah($subtotalBelanja) ?></span>
    </div>

    <div class="total-row">
        <span>PPN (11%)</span>
        <span><?= rupiah($ppn) ?></span>
    </div>

    <div class="total">
        <span>TOTAL BAYAR</span>

        <span>
            <?= rupiah($transaksi['total_belanja']) ?>
        </span>
    </div>

    <hr class="line">

    <!-- INFORMASI PEMBAYARAN -->

    <div class="payment">

        <span class="payment-title">
            INFORMASI PEMBAYARAN
        </span>

        <div class="payment-row">
            <span>Metode Pembayaran</span>
            <span><?= e($namaMetode) ?></span>
        </div>

        <div class="payment-row">
            <span>Status pembayaran</span>

            <span>
                <span class="status <?= e($statusClass) ?>">
                    <?= e(statusLabel($transaksi['status_pembayaran'])) ?>
                </span>
            </span>
        </div>

        <?php if ($transaksi['metode_pembayaran'] === 'KASIR'): ?>

            <div class="payment-row">
                <span>Cabang</span>
                <span>
                    <?= e($transaksi['nama_cabang'] ?? '-') ?>
                </span>
            </div>

            <div class="payment-row">
                <span>Kota</span>
                <span>
                    <?= e($transaksi['kota_cabang'] ?? '-') ?>
                </span>
            </div>

        <?php else: ?>

            <div class="payment-row">
                <span>Alamat</span>
                <span>
                    <?= e($transaksi['alamat_pengiriman'] ?? '-') ?>
                </span>
            </div>

        <?php endif; ?>

    </div>

    <hr class="line">

    <!-- FOOTER -->

    <div class="footer">
        <strong>TERIMA KASIH</strong><br>

        Simpan struk ini sebagai bukti pesanan Anda.<br>

        TokoKu - Belanja Mudah dan Nyaman
    </div>

</div>

<!-- TOMBOL AKSI -->

<div class="actions">

    <button
        type="button"
        class="print"
        onclick="window.print()"
    >
        Cetak Struk
    </button>

    <a
        class="back"
        href="detail.php?id=<?= (int)$idTransaksi ?>"
    >
        Kembali
    </a>

</div>

</body>
</html>
