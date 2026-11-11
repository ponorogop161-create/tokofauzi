
<?php
require_once __DIR__ . "/../../config/koneksi.php";
require_once __DIR__ . "/../../config/auth.php";
wajib_user();

$idUser = (int)($_SESSION['id_user'] ?? 0);
$idTransaksi = (int)($_GET['id'] ?? 0);

if ($idTransaksi <= 0) {
    die("Nomor transaksi tidak valid.");
}

$stmt = $conn->prepare("
    SELECT
        t.id_transaksi,
        t.tanggal,
        t.total_belanja,
        t.no_hp,
        t.alamat_pengiriman,
        t.metode_pembayaran,
        t.status_pembayaran,
        t.status_pesanan,
        p.nama_pelanggan,
        c.nama_cabang,
        c.kota AS kota_cabang
    FROM tbl_transaksi t
    LEFT JOIN tbl_pelanggan p
        ON t.id_pelanggan = p.id_pelanggan
    LEFT JOIN tbl_cabang c
        ON t.id_cabang = c.id_cabang
    WHERE t.id_transaksi = ? AND t.id_user = ?
    LIMIT 1
");

$stmt->bind_param("ii", $idTransaksi, $idUser);
$stmt->execute();
$transaksi = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$transaksi) {
    die("Transaksi tidak ditemukan atau bukan milik akun Anda.");
}

$stmt = $conn->prepare("
    SELECT nama_produk, jumlah, harga, subtotal
    FROM tbl_detail_transaksi
    WHERE id_transaksi = ?
");
$stmt->bind_param("i", $idTransaksi);
$stmt->execute();
$detail = $stmt->get_result();
$stmt->close();

function statusLabel($status) {
    return ucwords(str_replace('_', ' ', (string)$status));
}

$metode = [
    'QRIS' => 'QRIS',
    'COD' => 'COD (Bayar saat barang diterima)',
    'KASIR' => 'Bayar langsung ke kasir toko'
];

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Struk Pesanan #<?= $idTransaksi ?> | TokoKu</title>

<style>
*{box-sizing:border-box}
body{
    margin:0;
    padding:25px;
    background:#eef2f7;
    font-family:Arial,sans-serif;
    color:#222;
}
.receipt{
    width:100%;
    max-width:430px;
    margin:0 auto;
    padding:25px;
    background:white;
    border-radius:8px;
    box-shadow:0 4px 20px rgba(0,0,0,.08);
}
.store{text-align:center}
.store h2{margin:0;font-size:25px;letter-spacing:2px}
.store p{margin:6px 0;font-size:12px;color:#666}
.line{
    border:0;
    border-top:1px dashed #999;
    margin:17px 0;
}
.info{
    display:flex;
    justify-content:space-between;
    gap:12px;
    font-size:12px;
    margin:9px 0;
}
.info span:first-child{color:#666}
.info span:last-child{text-align:right;font-weight:600}
.items{
    width:100%;
    border-collapse:collapse;
    font-size:12px;
}
.items th{
    text-align:left;
    padding:8px 3px;
    border-bottom:1px dashed #999;
}
.items td{padding:9px 3px;vertical-align:top}
.items .right{text-align:right}
.product-name{font-weight:600}
.product-detail{color:#666;margin-top:4px;font-size:11px}
.total{
    display:flex;
    justify-content:space-between;
    font-size:17px;
    font-weight:700;
}
.payment{
    background:#f8fafc;
    border-radius:7px;
    padding:12px;
    font-size:12px;
    line-height:1.7;
}
.footer{text-align:center;font-size:11px;color:#666;line-height:1.6}
.actions{
    max-width:430px;
    margin:15px auto;
    display:flex;
    gap:10px;
}
.actions button,.actions a{
    flex:1;
    border:0;
    padding:12px;
    border-radius:7px;
    text-align:center;
    text-decoration:none;
    cursor:pointer;
    font-weight:600;
    font-size:13px;
}
.print{background:#2563eb;color:white}
.back{background:#e2e8f0;color:#334155}
@media print{
    body{padding:0;background:white}
    .receipt{max-width:none;box-shadow:none;border-radius:0;padding:8px}
    .actions{display:none}
    @page{size:80mm auto;margin:4mm}
}
</style>
</head>

<body>

<div class="receipt">

    <div class="store">
        <h2>TOKOKU</h2>
        <p>STRUK PEMBELIAN ONLINE</p>
        <p>Terima kasih telah berbelanja di TokoKu</p>
    </div>

    <hr class="line">

    <div class="info">
        <span>No. Transaksi</span>
        <span>#<?= $idTransaksi ?></span>
    </div>

    <div class="info">
        <span>Tanggal</span>
        <span><?= date('d/m/Y H:i', strtotime($transaksi['tanggal'])) ?></span>
    </div>

    <div class="info">
        <span>Pembeli</span>
        <span><?= e($transaksi['nama_pelanggan'] ?? '-') ?></span>
    </div>

    <div class="info">
        <span>No. HP</span>
        <span><?= e($transaksi['no_hp'] ?? '-') ?></span>
    </div>

    <hr class="line">

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
                        <?= (int)$item['jumlah'] ?> × <?= rupiah($item['harga']) ?>
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

    <div class="total">
        <span>TOTAL</span>
        <span><?= rupiah($transaksi['total_belanja']) ?></span>
    </div>

    <hr class="line">

    <div class="payment">
        <strong>INFORMASI PEMBAYARAN</strong><br>
        Metode:
        <?= e($metode[$transaksi['metode_pembayaran']] ?? $transaksi['metode_pembayaran']) ?><br>

        Status pembayaran:
        <?= e(statusLabel($transaksi['status_pembayaran'])) ?><br>

        Status pesanan:
        <?= e(statusLabel($transaksi['status_pesanan'])) ?><br>

        <?php if ($transaksi['metode_pembayaran'] === 'KASIR'): ?>
            Cabang:
            <?= e($transaksi['nama_cabang'] ?? '-') ?>
            (<?= e($transaksi['kota_cabang'] ?? '-') ?>)
        <?php else: ?>
            Alamat:
            <?= e($transaksi['alamat_pengiriman'] ?? '-') ?>
        <?php endif; ?>
    </div>

    <hr class="line">

    <div class="footer">
        <strong>TERIMA KASIH</strong><br>
        Simpan struk ini sebagai bukti pesanan Anda.<br>
        TokoKu - Belanja Mudah dan Nyaman
    </div>

</div>

<div class="actions">
    <button class="print" onclick="window.print()">
        Cetak Struk
    </button>

    <a class="back" href="detail.php?id=<?= $idTransaksi ?>">
        Kembali
    </a>
</div>

</body>
</html>
