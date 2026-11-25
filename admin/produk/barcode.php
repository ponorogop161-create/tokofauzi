
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";

wajib_admin();

$judul = "Cetak Barcode Produk";

// Ambil semua produk yang sudah memiliki barcode
$stmt = $conn->prepare("
    SELECT id_produk, nama_produk, barcode, harga, stok
    FROM tbl_produk
    WHERE barcode IS NOT NULL AND barcode != ''
    ORDER BY nama_produk ASC
");

$stmt->execute();
$produk = $stmt->get_result();

require_once "../header.php";
?>

<div class="barcode-page">

    <div class="barcode-header no-print">
        <div>
            <h1>Cetak Barcode Produk</h1>
            <p>Barcode produk TokoKu untuk proses transaksi kasir.</p>
        </div>

        <div class="barcode-actions">
            <a href="index.php" class="btn btn-secondary">Kembali</a>
            <button onclick="window.print()" class="btn btn-primary">
                Cetak Semua Barcode
            </button>
        </div>
    </div>

    <div class="barcode-grid">

        <?php if ($produk->num_rows > 0): ?>

            <?php while ($row = $produk->fetch_assoc()): ?>

                <div class="barcode-card">

                    <div class="barcode-store">TOKOKU</div>

                    <h3><?= e($row['nama_produk']) ?></h3>

                    <svg
                        class="barcode-image"
                        data-barcode="<?= e($row['barcode']) ?>">
                    </svg>

                    <div class="barcode-number">
                        <?= e($row['barcode']) ?>
                    </div>

                    <div class="barcode-price">
                        <?= rupiah($row['harga']) ?>
                    </div>

                    <small>
                        ID Produk: <?= (int)$row['id_produk'] ?>
                    </small>

                    <button
                        class="btn btn-primary no-print"
                        onclick="cetakSatu(this)">
                        Cetak Barcode
                    </button>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="panel">
                Belum ada produk yang memiliki barcode.
                Jalankan generate_barcode.php terlebih dahulu.
            </div>

        <?php endif; ?>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".barcode-image").forEach(function (svg) {
        const kode = svg.dataset.barcode;

        try {
            JsBarcode(svg, kode, {
                format: "EAN13",
                width: 2,
                height: 65,
                displayValue: false,
                margin: 5,
                background: "#ffffff",
                lineColor: "#000000"
            });
        } catch (error) {
            svg.outerHTML = '<p>Barcode tidak valid</p>';
        }
    });
});

function cetakSatu(button) {
    const kartu = button.closest(".barcode-card");

    document.querySelectorAll(".barcode-card").forEach(function (item) {
        item.classList.remove("cetak-aktif");
    });

    kartu.classList.add("cetak-aktif");

    window.print();

    kartu.classList.remove("cetak-aktif");
}
</script>

<style>
.barcode-page {
    padding: 10px 0;
}

.barcode-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}

.barcode-header h1 {
    margin: 0 0 5px;
}

.barcode-header p {
    color: #64748b;
    margin: 0;
}

.barcode-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.barcode-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    gap: 18px;
}

.barcode-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px 12px;
    text-align: center;
    color: #111827;
}

.barcode-store {
    font-size: 19px;
    font-weight: 900;
    letter-spacing: 3px;
    margin-bottom: 8px;
}

.barcode-card h3 {
    font-size: 14px;
    min-height: 35px;
    margin: 5px 0;
}

.barcode-image {
    width: 100%;
    max-width: 230px;
    height: 85px;
}

.barcode-number {
    font-family: monospace;
    font-size: 14px;
    letter-spacing: 2px;
}

.barcode-price {
    font-weight: bold;
    margin: 8px 0;
}

.barcode-card small {
    display: block;
    color: #64748b;
    margin-bottom: 12px;
}

@media print {
    @page {
        size: A4;
        margin: 10mm;
    }

    body * {
        visibility: hidden;
    }

    .barcode-grid,
    .barcode-grid * {
        visibility: visible;
    }

    .barcode-grid {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
    }

    .barcode-card {
        border: 1px dashed #999;
        border-radius: 0;
        padding: 10px 5px;
        break-inside: avoid;
    }

    .barcode-card .no-print {
        display: none !important;
    }

    .barcode-card:not(.cetak-aktif) {
        display: block;
    }

    .barcode-card.cetak-aktif ~ .barcode-card {
        display: none !important;
    }

    .barcode-card.cetak-aktif {
        display: block !important;
    }
}
</style>

<?php
$stmt->close();
require_once __DIR__ . "/../../includes/footer.php";
?>
