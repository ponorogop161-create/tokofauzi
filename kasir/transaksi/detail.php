
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";

wajib_kasir();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: riwayat.php?pesan=" . urlencode("ID transaksi tidak valid."));
    exit;
}

// =====================================
// AMBIL DATA TRANSAKSI
// =====================================

$stmt = $conn->prepare("
    SELECT
        t.id_transaksi,
        t.tanggal,
        t.total_belanja,
        t.uang_diterima,
        t.kembalian,
        t.metode_pembayaran,
        t.status_pembayaran,
        p.nama_pelanggan,
        u.nama AS nama_kasir
    FROM tbl_transaksi t
    LEFT JOIN tbl_pelanggan p
        ON t.id_pelanggan = p.id_pelanggan
    LEFT JOIN tbl_users u
        ON t.id_user = u.id_user
    WHERE t.id_transaksi = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

$transaksi = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$transaksi) {
    header("Location: riwayat.php?pesan=" . urlencode("Transaksi tidak ditemukan."));
    exit;
}

// =====================================
// AMBIL DETAIL BARANG DAN BARCODE
// =====================================

$stmt = $conn->prepare("
    SELECT
        d.id_produk,
        d.nama_produk,
        d.jumlah,
        d.harga,
        d.subtotal,
        p.barcode
    FROM tbl_detail_transaksi d
    LEFT JOIN tbl_produk p
        ON d.id_produk = p.id_produk
    WHERE d.id_transaksi = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

$items = [];
$totalHitung = 0;

while ($row = $result->fetch_assoc()) {

    $row['subtotal_hitung'] =
        (float)$row['harga'] * (int)$row['jumlah'];

    $totalHitung += $row['subtotal_hitung'];

    $items[] = $row;
}

$stmt->close();

// =====================================
// FORMAT RUPIAH
// =====================================

function formatRupiahStruk($angka): string
{
    return number_format((float)$angka, 0, ',', '.');
}

// =====================================
// METODE PEMBAYARAN
// =====================================

$metode = strtolower(trim($transaksi['metode_pembayaran'] ?? 'tunai'));

if ($metode === 'qris') {
    $namaMetode = 'QRIS';
} elseif ($metode === 'tunai') {
    $namaMetode = 'TUNAI';
} else {
    $namaMetode = strtoupper($metode);
}

// =====================================
// STATUS PEMBAYARAN
// =====================================

$statusPembayaran = strtolower(
    trim($transaksi['status_pembayaran'] ?? 'belum_bayar')
);

if ($statusPembayaran === 'lunas') {
    $namaStatus = 'LUNAS';
} elseif ($statusPembayaran === 'menunggu_verifikasi') {
    $namaStatus = 'MENUNGGU VERIFIKASI';
} else {
    $namaStatus = 'BELUM BAYAR';
}

$judul = "Struk Transaksi";

require_once "../header.php";
?>

<!-- =====================================
     HEADER HALAMAN
====================================== -->

<div class="kasir-page-header no-print">

    <div>
        <h1>Struk Transaksi</h1>

        <p>
            Nomor transaksi #<?= (int)$transaksi['id_transaksi'] ?>
        </p>
    </div>

    <div class="receipt-toolbar">

        <a href="riwayat.php" class="kasir-btn kasir-btn-secondary">
            Kembali
        </a>

        <button
            type="button"
            onclick="window.print()"
            class="kasir-btn">
            Cetak Struk
        </button>

    </div>

</div>

<!-- =====================================
     AREA STRUK
====================================== -->

<div class="receipt-area">

    <div class="thermal-receipt">

        <!-- HEADER TOKO -->

        <div class="receipt-header">

            <h1>TOKOKU</h1>

            <p>MINIMARKET & KEBUTUHAN SEHARI-HARI</p>

            <p>TERIMA KASIH TELAH BERBELANJA</p>

        </div>

        <div class="receipt-divider"></div>

        <!-- INFORMASI TRANSAKSI -->

        <div class="receipt-meta">

            <div>
                <span>No. Transaksi</span>
                <span>: <?= (int)$transaksi['id_transaksi'] ?></span>
            </div>

            <div>
                <span>Tanggal</span>
                <span>
                    : <?= e(date('d/m/Y', strtotime($transaksi['tanggal']))) ?>
                </span>
            </div>

            <div>
                <span>Kasir</span>
                <span>: <?= e($transaksi['nama_kasir'] ?? '-') ?></span>
            </div>

            <div>
                <span>Pelanggan</span>
                <span>: <?= e($transaksi['nama_pelanggan'] ?? 'UMUM') ?></span>
            </div>

        </div>

        <div class="receipt-divider"></div>

        <!-- DAFTAR BARANG -->

        <div class="receipt-items">

            <?php foreach ($items as $item): ?>

                <div class="receipt-product">

                    <div class="item-name">
                        <?= e($item['nama_produk']) ?>
                    </div>

                    <div class="item-detail">

                        <span>
                            <?= (int)$item['jumlah'] ?> x
                            <?= formatRupiahStruk($item['harga']) ?>
                        </span>

                        <span>
                            <?= formatRupiahStruk($item['subtotal_hitung']) ?>
                        </span>

                    </div>

                    <!-- BARCODE PRODUK -->

                    <?php if (!empty($item['barcode'])): ?>

                        <div class="receipt-barcode">

                            <svg
                                class="barcode-image"
                                data-barcode="<?= e($item['barcode']) ?>">
                            </svg>

                            <div class="barcode-number">
                                <?= e($item['barcode']) ?>
                            </div>

                        </div>

                    <?php else: ?>

                        <div class="barcode-empty">
                            Barcode tidak tersedia
                        </div>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        </div>

        <div class="receipt-divider"></div>

        <!-- RINGKASAN PEMBAYARAN -->

        <div class="receipt-summary">

            <div class="summary-row">

                <span>TOTAL BELANJA</span>

                <span>
                    Rp<?= formatRupiahStruk($transaksi['total_belanja']) ?>
                </span>

            </div>

            <div class="summary-row">

                <span>METODE BAYAR</span>

                <span>
                    <?= e($namaMetode) ?>
                </span>

            </div>

            <div class="summary-row">

                <span>STATUS BAYAR</span>

                <span>
                    <?= e($namaStatus) ?>
                </span>

            </div>

            <?php if ($metode === 'tunai'): ?>

                <div class="summary-row">

                    <span>UANG DITERIMA</span>

                    <span>
                        Rp<?= formatRupiahStruk($transaksi['uang_diterima']) ?>
                    </span>

                </div>

                <div class="summary-row summary-change">

                    <strong>KEMBALIAN</strong>

                    <strong>
                        Rp<?= formatRupiahStruk($transaksi['kembalian']) ?>
                    </strong>

                </div>

            <?php elseif ($metode === 'qris'): ?>

                <div class="summary-row summary-change">

                    <strong>DIBAYARKAN QRIS</strong>

                    <strong>
                        Rp<?= formatRupiahStruk($transaksi['total_belanja']) ?>
                    </strong>

                </div>

            <?php else: ?>

                <div class="summary-row">

                    <span>UANG DITERIMA</span>

                    <span>
                        Rp<?= formatRupiahStruk($transaksi['uang_diterima']) ?>
                    </span>

                </div>

            <?php endif; ?>

        </div>

        <div class="receipt-divider"></div>

        <!-- FOOTER STRUK -->

        <div class="receipt-footer">

            <p>BARANG YANG SUDAH DIBELI</p>

            <p>TIDAK DAPAT DITUKAR / DIKEMBALIKAN</p>

            <br>

            <h3>TERIMA KASIH</h3>

            <p>SELAMAT BERBELANJA KEMBALI</p>

            <br>

            <p>*** TOKOKU ***</p>

            <p>LAYANAN PELANGGAN TOKOKU</p>

        </div>

    </div>

</div>

<!-- =====================================
     CSS STRUK
====================================== -->

<style>

.receipt-toolbar {
    display: flex;
    gap: 10px;
}

.receipt-area {
    display: flex;
    justify-content: center;
    padding: 25px 15px;
}

.thermal-receipt {
    width: 340px;
    max-width: 100%;
    padding: 20px;
    background: #fff;
    color: #000;
    font-family: "Courier New", monospace;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.5;
    box-shadow: 0 3px 18px rgba(0, 0, 0, .10);
}

.receipt-header {
    text-align: center;
}

.receipt-header h1 {
    font-family: Arial, sans-serif;
    font-size: 30px;
    font-weight: 900;
    letter-spacing: 3px;
    margin: 0 0 5px;
    -webkit-text-stroke: .5px #000;
}

.receipt-header p {
    margin: 2px 0;
    font-size: 10px;
    font-weight: 800;
}

.receipt-divider {
    border-top: 1px dashed #000;
    margin: 12px 0;
}

.receipt-meta {
    font-weight: 800;
}

.receipt-meta div {
    display: grid;
    grid-template-columns: 95px 1fr;
    gap: 3px;
    margin-bottom: 4px;
}

.receipt-meta span:last-child {
    overflow-wrap: anywhere;
}

.receipt-product {
    margin-bottom: 12px;
}

.item-name {
    font-weight: 900;
    font-size: 12px;
    overflow-wrap: anywhere;
    margin-bottom: 3px;
}

.item-detail {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    font-weight: 800;
    margin-bottom: 5px;
}

.receipt-barcode {
    text-align: center;
    margin: 5px 0 12px;
    padding: 3px 0;
}

.barcode-image {
    display: block;
    width: 100%;
    max-width: 230px;
    height: 42px;
    margin: auto;
}

.barcode-number {
    font-family: "Courier New", monospace;
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 1px;
    text-align: center;
    margin-top: 2px;
}

.barcode-empty {
    font-size: 9px;
    font-weight: bold;
    text-align: center;
    margin-bottom: 10px;
}

.receipt-summary {
    margin-top: 5px;
    font-weight: 800;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    padding: 4px 0;
}

.summary-row span:last-child,
.summary-row strong:last-child {
    text-align: right;
}

.summary-change {
    border-top: 1px dashed #000;
    margin-top: 5px;
    padding-top: 8px;
    font-size: 14px;
    font-weight: 900;
}

.receipt-footer {
    text-align: center;
    font-size: 10px;
    font-weight: 800;
    line-height: 1.5;
}

.receipt-footer p {
    margin: 2px 0;
}

.receipt-footer h3 {
    font-size: 15px;
    font-weight: 900;
    margin: 5px 0;
    letter-spacing: 1px;
}

/* CETAK THERMAL 80 MM */

@media print {

    @page {
        size: 80mm auto;
        margin: 3mm;
    }

    html,
    body {
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
        padding: 0 !important;
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
        font-size: 11px !important;
        font-weight: 800 !important;
        color: #000 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .thermal-receipt * {
        color: #000 !important;
        text-shadow: 0 0 .3px #000;
    }

    .receipt-header h1 {
        font-size: 25px;
        font-weight: 900 !important;
        -webkit-text-stroke: .6px #000;
    }

    .receipt-header p {
        font-weight: 900 !important;
    }

    .item-name,
    .summary-row,
    .receipt-footer h3 {
        font-weight: 900 !important;
    }

    .barcode-image {
        max-width: 220px;
        height: 38px;
    }

    .barcode-number {
        font-size: 10px;
        font-weight: 900 !important;
    }

    .receipt-divider {
        border-color: #000;
    }

}

</style>

<!-- =====================================
     JAVASCRIPT BARCODE
====================================== -->

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    document.querySelectorAll(".barcode-image").forEach(function (element) {

        const barcode = element.dataset.barcode;

        if (!barcode) {
            return;
        }

        try {

            JsBarcode(element, barcode, {
                format: "CODE128",
                width: 1.5,
                height: 38,
                displayValue: false,
                margin: 0,
                lineColor: "#000000",
                background: "#ffffff"
            });

        } catch (error) {

            element.style.display = "none";

            console.error("Gagal membuat barcode:", error);

        }

    });

});
</script>

<?php require_once "../includes/footer.php"; ?>
