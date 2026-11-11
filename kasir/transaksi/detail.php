
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";

wajib_kasir();

$id = (int)($_GET['id'] ?? 0);
$idUser = (int)($_SESSION['id_user'] ?? 0);

// Ambil transaksi milik kasir yang sedang login
$stmt = $conn->prepare("
    SELECT
        t.id_transaksi,
        t.tanggal,
        t.total_belanja,
t.uang_diterima,
t.kembalian,
        p.nama_pelanggan,
        u.nama AS nama_kasir
    FROM tbl_transaksi t
    LEFT JOIN tbl_pelanggan p
        ON t.id_pelanggan = p.id_pelanggan
    LEFT JOIN tbl_users u
        ON t.id_user = u.id_user
    WHERE t.id_transaksi = ?
      AND t.id_user = ?
");

$stmt->bind_param("ii", $id, $idUser);
$stmt->execute();
$transaksi = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$transaksi) {
    header("Location: riwayat.php?pesan=" . urlencode("Transaksi tidak ditemukan."));
    exit;
}

// Ambil detail barang
$stmt = $conn->prepare("
    SELECT nama_produk, jumlah, harga, subtotal
    FROM tbl_detail_transaksi
    WHERE id_transaksi = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();
$detail = $stmt->get_result();

$items = [];
while ($row = $detail->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();

$judul = "Struk Transaksi";
require_once "../header.php";
?>

<div class="kasir-page-header no-print">
    <div>
        <h1>Struk Transaksi</h1>
        <p>Transaksi #<?= (int)$transaksi['id_transaksi'] ?> berhasil disimpan.</p>
    </div>

    <div class="receipt-toolbar">
        <a href="riwayat.php" class="kasir-btn kasir-btn-secondary">
            Kembali
        </a>

        <button type="button" onclick="window.print()" class="kasir-btn">
            Cetak Struk
        </button>
    </div>
</div>

<div class="receipt-area">
    <div class="thermal-receipt">

        <div class="receipt-center">
            <h2>TOKOKU</h2>
            <p>TERIMA KASIH TELAH BERBELANJA</p>
            <p>SELAMAT DATANG KEMBALI</p>
        </div>

        <div class="receipt-line"></div>

        <div class="receipt-info">
            <div>
                <span>No. Transaksi</span>
                <strong><?= (int)$transaksi['id_transaksi'] ?></strong>
            </div>

            <div>
                <span>Tanggal</span>
                <strong><?= e(date('d/m/Y', strtotime($transaksi['tanggal']))) ?></strong>
            </div>

            <div>
                <span>Kasir</span>
                <strong><?= e($transaksi['nama_kasir'] ?? 'Kasir') ?></strong>
            </div>

            <div>
                <span>Pelanggan</span>
                <strong><?= e($transaksi['nama_pelanggan'] ?? 'UMUM') ?></strong>
            </div>
        </div>

        <div class="receipt-line"></div>

        <div class="receipt-items">
            <?php foreach ($items as $item): ?>
                <div class="receipt-product">
                    <div class="receipt-product-name">
                        <?= e($item['nama_produk']) ?>
                    </div>

                    <div class="receipt-product-detail">
                        <span>
                            <?= (int)$item['jumlah'] ?> x
                            <?= number_format((float)$item['harga'], 0, ',', '.') ?>
                        </span>

                        <strong>
                            <?= number_format((float)$item['subtotal'], 0, ',', '.') ?>
                        </strong>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="receipt-line"></div>

        
<div class="receipt-total">
    <div>
        <span>TOTAL BELANJA</span>
        <strong>
            <?= number_format((float)$transaksi['total_belanja'], 0, ',', '.') ?>
        </strong>
    </div>

    <div>
        <span>TUNAI</span>
        <strong>
            <?= number_format((float)$transaksi['uang_diterima'], 0, ',', '.') ?>
        </strong>
    </div>

    <div>
        <span>KEMBALI</span>
        <strong>
            <?= number_format((float)$transaksi['kembalian'], 0, ',', '.') ?>
        </strong>
    </div>
</div>


        <div class="receipt-line"></div>

        <div class="receipt-center receipt-footer">
            <p>BARANG YANG SUDAH DIBELI</p>
            <p>TIDAK DAPAT DITUKAR</p>
            <br>
            <strong>TERIMA KASIH</strong>
            <p>SELAMAT BERBELANJA KEMBALI</p>
            <br>
            <p>*** TOKOKU ***</p>
        </div>

    </div>
</div>

<style>
.receipt-toolbar {
    display: flex;
    gap: 10px;
}

.receipt-area {
    display: flex;
    justify-content: center;
    padding: 20px;
}

.thermal-receipt {
    width: 340px;
    max-width: 100%;
    background: #fff;
    color: #111;
    padding: 22px 20px;
    box-shadow: 0 3px 18px rgba(0,0,0,.08);
    font-family: "Courier New", monospace;
    font-size: 12px;
}

.receipt-center {
    text-align: center;
}

.receipt-center h2 {
    font-family: Arial, sans-serif;
    font-size: 25px;
    font-weight: 900;
    letter-spacing: 2px;
    margin-bottom: 8px;
}

.receipt-center p {
    font-size: 10px;
    line-height: 1.6;
}

.receipt-line {
    border-top: 1px dashed #222;
    margin: 13px 0;
}

.receipt-info {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.receipt-info div {
    display: flex;
    justify-content: space-between;
    gap: 8px;
}

.receipt-info span {
    white-space: nowrap;
}

.receipt-info strong {
    text-align: right;
    overflow-wrap: anywhere;
}

.receipt-product {
    margin-bottom: 11px;
}

.receipt-product-name {
    font-weight: bold;
    overflow-wrap: anywhere;
    margin-bottom: 4px;
}

.receipt-product-detail {
    display: flex;
    justify-content: space-between;
    gap: 8px;
}

.receipt-total div {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    font-size: 14px;
    font-weight: bold;
}

.receipt-footer {
    line-height: 1.5;
}

@media print {
    @page {
        size: 80mm auto;
        margin: 3mm;
    }

    html, body {
        width: 80mm !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
    }

    .kasir-sidebar,
    .kasir-topbar,
    .no-print {
        display: none !important;
    }

    .kasir-main {
        width: 100% !important;
        margin: 0 !important;
        min-height: auto !important;
    }

    .kasir-content {
        padding: 0 !important;
        margin: 0 !important;
    }

    .receipt-area {
        display: block;
        padding: 0;
        margin: 0;
    }

    .thermal-receipt {
        width: 100%;
        max-width: none;
        padding: 0;
        margin: 0;
        box-shadow: none;
        font-size: 10px;
    }

    .receipt-center h2 {
        font-size: 21px;
    }

    .receipt-line {
        border-color: #000;
    }
}

.receipt-total div {
    padding: 4px 0;
}

.receipt-total div:last-child {
    border-top: 1px dashed #222;
    margin-top: 7px;
    padding-top: 10px;
}
</style>

<?php require_once "../includes/footer.php"; ?>
