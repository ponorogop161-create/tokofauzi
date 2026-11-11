
<?php
require_once __DIR__ . "/../../config/koneksi.php";
require_once __DIR__ . "/../../config/auth.php";

wajib_user();

$idUser = (int)($_SESSION['id_user'] ?? 0);
$idTransaksi = (int)($_GET['id'] ?? 0);

if ($idTransaksi <= 0) {
    header("Location: index.php");
    exit;
}

// Pastikan transaksi milik pengguna yang login
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
        t.id_cabang,
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
    header("Location: index.php?pesan=tidak_ditemukan");
    exit;
}

// Ambil detail produk
$stmtDetail = $conn->prepare("
    SELECT nama_produk, jumlah, harga, subtotal
    FROM tbl_detail_transaksi
    WHERE id_transaksi = ?
    ORDER BY id_detail ASC
");

$stmtDetail->bind_param("i", $idTransaksi);
$stmtDetail->execute();
$detailQuery = $stmtDetail->get_result();

$judul = "Detail Pesanan";

$metode = [
    'QRIS' => 'QRIS',
    'COD' => 'COD (Bayar saat barang diterima)',
    'KASIR' => 'Bayar langsung ke kasir toko'
];

$judulStatus = [
    'belum_bayar' => 'Belum Dibayar',
    'menunggu_verifikasi' => 'Menunggu Verifikasi',
    'lunas' => 'Lunas',
    'menunggu' => 'Menunggu',
    'diproses' => 'Diproses',
    'dikirim' => 'Dikirim',
    'selesai' => 'Selesai',
    'dibatalkan' => 'Dibatalkan'
];

require_once __DIR__ . "/../header.php";
?>

<style>
.order-detail-heading {
    margin-bottom: 22px;
}

.order-detail-heading h2 {
    margin: 0 0 7px;
    color: #172554;
    font-size: 25px;
}

.order-detail-heading p {
    margin: 0;
    color: #64748b;
    font-size: 14px;
}

.order-detail-panel {
    margin-bottom: 20px;
    padding: 22px;
    background: white;
    border: 1px solid #e5eaf1;
    border-radius: 13px;
}

.order-detail-panel h3 {
    margin: 0 0 18px;
    color: #172554;
    font-size: 16px;
}

.order-info-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}

.order-info-item span {
    display: block;
    margin-bottom: 6px;
    color: #64748b;
    font-size: 12px;
}

.order-info-item strong {
    color: #172554;
    font-size: 14px;
}

.order-table-wrap {
    overflow-x: auto;
}

.order-table {
    width: 100%;
    min-width: 550px;
    border-collapse: collapse;
}

.order-table th {
    padding: 14px;
    background: #f8fafc;
    color: #64748b;
    text-align: left;
    font-size: 12px;
}

.order-table td {
    padding: 15px 14px;
    border-bottom: 1px solid #edf0f4;
    color: #334155;
    font-size: 13px;
}

.order-total {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    padding-top: 18px;
    color: #172554;
    font-weight: 700;
}

.order-total strong {
    color: #2563eb;
    font-size: 20px;
}

.order-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

.order-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 11px 18px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.order-print {
    background: #2563eb;
    color: white;
}

.order-back {
    background: #e2e8f0;
    color: #334155;
}

.order-badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 12px;
    font-weight: 600;
}

@media (max-width: 600px) {
    .order-info-grid {
        grid-template-columns: 1fr;
    }

    .order-detail-panel {
        padding: 16px;
    }
}
</style>

<div class="order-detail-heading">
    <h2>Detail Pesanan</h2>
    <p>Informasi lengkap transaksi belanjamu.</p>
</div>

<div class="order-detail-panel">
    <h3>Informasi Transaksi</h3>

    <div class="order-info-grid">
        <div class="order-info-item">
            <span>Nomor Transaksi</span>
            <strong>
                TRX-<?= str_pad((int)$transaksi['id_transaksi'], 5, '0', STR_PAD_LEFT) ?>
            </strong>
        </div>

        <div class="order-info-item">
            <span>Tanggal Transaksi</span>
            <strong>
                <?= date('d-m-Y H:i', strtotime($transaksi['tanggal'])) ?>
            </strong>
        </div>

        <div class="order-info-item">
            <span>Nama Pelanggan</span>
            <strong><?= e($transaksi['nama_pelanggan'] ?? '-') ?></strong>
        </div>

        <div class="order-info-item">
            <span>Nomor HP</span>
            <strong><?= e($transaksi['no_hp'] ?? '-') ?></strong>
        </div>

        <div class="order-info-item">
            <span>Metode Pembayaran</span>
            <strong>
                <?= e($metode[$transaksi['metode_pembayaran']] ?? '-') ?>
            </strong>
        </div>

        <div class="order-info-item">
            <span>Status Pembayaran</span>
            <strong>
                <span class="order-badge">
                    <?= e($judulStatus[$transaksi['status_pembayaran']] ?? '-') ?>
                </span>
            </strong>
        </div>

        <div class="order-info-item">
            <span>Status Pesanan</span>
            <strong>
                <span class="order-badge">
                    <?= e($judulStatus[$transaksi['status_pesanan']] ?? '-') ?>
                </span>
            </strong>
        </div>

        <div class="order-info-item">
            <span>Alamat / Cabang</span>
            <strong>
                <?php if ($transaksi['metode_pembayaran'] === 'KASIR'): ?>
                    <?= e($transaksi['nama_cabang'] ?? '-') ?>
                    - <?= e($transaksi['kota_cabang'] ?? '-') ?>
                <?php else: ?>
                    <?= e($transaksi['alamat_pengiriman'] ?? '-') ?>
                <?php endif; ?>
            </strong>
        </div>
    </div>
</div>

<div class="order-detail-panel">
    <h3>Produk yang Dibeli</h3>

    <div class="order-table-wrap">
        <table class="order-table">
            <thead>
                <tr>
                    <th>Nama Produk</th>
                    <th>Harga</th>
                    <th>Jumlah</th>
                    <th>Subtotal</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($detailQuery->num_rows > 0): ?>
                    <?php while ($item = $detailQuery->fetch_assoc()): ?>
                        <tr>
                            <td><?= e($item['nama_produk']) ?></td>
                            <td><?= rupiah($item['harga']) ?></td>
                            <td><?= (int)$item['jumlah'] ?></td>
                            <td><?= rupiah($item['subtotal']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">Detail produk tidak ditemukan.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="order-total">
        <span>Total Belanja</span>
        <strong><?= rupiah($transaksi['total_belanja']) ?></strong>
    </div>
</div>

<div class="order-actions">
    <a href="cetak.php?id=<?= (int)$idTransaksi ?>"
       target="_blank"
       class="order-button order-print">
        🖨 Cetak Struk
    </a>

    <a href="index.php" class="order-button order-back">
        ← Kembali ke Pesanan
    </a>
</div>

<?php
$stmtDetail->close();
require_once __DIR__ . "/../includes/footer.php";
?>
