
<?php
require_once __DIR__ . "/../../config/koneksi.php";
require_once __DIR__ . "/../../config/auth.php";
wajib_user();

$judul = "Checkout Pesanan";
$keranjang = $_SESSION['keranjang'] ?? [];

if (empty($keranjang)) {
    header("Location: ../keranjang/index.php");
    exit;
}

$daftarProduk = [];
$total = 0;

foreach ($keranjang as $id => $jumlah) {
    $id = (int)$id;
    $jumlah = (int)$jumlah;

    if ($id <= 0 || $jumlah <= 0) continue;

    $stmt = $conn->prepare(
        "SELECT id_produk, nama_produk, harga, stok
         FROM tbl_produk WHERE id_produk = ? AND stok > 0"
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $produk = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$produk || $jumlah > (int)$produk['stok']) {
        die("Stok produk " . e($produk['nama_produk'] ?? 'tidak tersedia') .
            " tidak mencukupi. Silakan periksa keranjang.");
    }

    $produk['jumlah'] = $jumlah;
    $produk['subtotal'] = (float)$produk['harga'] * $jumlah;
    $total += $produk['subtotal'];
    $daftarProduk[] = $produk;
}

if (!$daftarProduk) {
    die("Tidak ada produk valid di keranjang.");
}

$cabang = $conn->query(
    "SELECT id_cabang, nama_cabang, kota FROM tbl_cabang ORDER BY nama_cabang"
);

$qrPath = __DIR__ . "/../../assets/images/qris-tokoku.png";
$qrTersedia = is_file($qrPath);

require __DIR__ . "/../header.php";
?>

<style>
.checkout-page{max-width:1100px;margin:auto}
.checkout-grid{display:grid;grid-template-columns:1.4fr 1fr;gap:22px}
.checkout-box{background:#fff;border:1px solid #e5eaf0;border-radius:15px;padding:22px}
.checkout-box h3{margin:0 0 18px}
.checkout-form label{display:block;font-weight:600;margin:14px 0 7px}
.checkout-form input,.checkout-form textarea,.checkout-form select{
 width:100%;padding:12px;border:1px solid #d8e0e9;border-radius:9px;
 box-sizing:border-box;font:inherit;background:#fff
}
.checkout-form textarea{min-height:100px;resize:vertical}
.payment-option{display:flex;gap:12px;align-items:flex-start;padding:15px;
 border:1px solid #e1e7ef;border-radius:11px;margin:10px 0;cursor:pointer}
.payment-option:has(input:checked){border-color:#2563eb;background:#eff6ff}
.payment-option input{width:auto;margin-top:4px}
.payment-option strong{display:block}
.payment-option small{color:#64748b}
.payment-detail{background:#f8fafc;border:1px solid #e2e8f0;
 border-radius:10px;padding:15px;margin-top:12px}
.qris-image{display:block;width:100%;max-width:260px;height:auto;
 margin:12px auto;border:1px solid #e5e7eb;border-radius:10px}
.checkout-item{display:flex;justify-content:space-between;gap:12px;
 padding:12px 0;border-bottom:1px solid #edf0f4}
.checkout-total{display:flex;justify-content:space-between;
 font-size:19px;font-weight:700;margin-top:18px}
.checkout-submit{width:100%;border:0;background:#2563eb;color:white;
 padding:14px;border-radius:10px;font-weight:700;cursor:pointer;margin-top:18px}
.checkout-submit:hover{background:#1d4ed8}
@media(max-width:760px){.checkout-grid{grid-template-columns:1fr}}
</style>

<div class="checkout-page">
    <h2>Checkout Pesanan</h2>
    <p>Lengkapi informasi dan pilih metode pembayaran.</p>

    <?php if (isset($_GET['error'])): ?>
        <div style="background:#fee2e2;color:#991b1b;padding:12px;border-radius:9px;margin-bottom:15px">
            <?= e($_GET['error']) ?>
        </div>
    <?php endif; ?>

    <div class="checkout-grid">
        <div class="checkout-box">
            <h3>Informasi Pembeli</h3>

            <form class="checkout-form" action="proses_checkout.php"
                  method="POST" enctype="multipart/form-data">

                <label>Nama Penerima / Pemesan</label>
                <input type="text" name="nama_penerima"
                       value="<?= e($_SESSION['nama'] ?? '') ?>" required>

                <label>Nomor HP</label>
                <input type="tel" name="no_hp" required
                       pattern="[0-9+ -]{9,20}"
                       placeholder="Contoh: 081234567890">

                <div id="alamatBox">
                    <label>Kota / Kabupaten</label>
                    <input type="text" name="kota" id="kota"
                           placeholder="Masukkan kota/kabupaten">

                    <label>Alamat Lengkap Pengiriman</label>
                    <textarea name="alamat_pengiriman" id="alamat"
                              placeholder="Jalan, nomor rumah, RT/RW, kecamatan, dan patokan"></textarea>
                </div>

                <h3 style="margin-top:25px">Metode Pembayaran</h3>

                <label class="payment-option">
                    <input type="radio" name="metode_pembayaran"
                           value="QRIS" required>
                    <span>
                        <strong>QRIS</strong>
                        <small>Bayar dengan scan QR Code.</small>
                    </span>
                </label>

                <label class="payment-option">
                    <input type="radio" name="metode_pembayaran" value="COD">
                    <span>
                        <strong>COD</strong>
                        <small>Bayar saat barang diterima.</small>
                    </span>
                </label>

                <label class="payment-option">
                    <input type="radio" name="metode_pembayaran" value="KASIR">
                    <span>
                        <strong>Bayar langsung ke kasir toko</strong>
                        <small>Datang ke cabang toko untuk membayar.</small>
                    </span>
                </label>

                <div id="qrisDetail" class="payment-detail" hidden>
                    <strong>Scan QRIS TokoKu</strong>

                    <?php if ($qrTersedia): ?>
                        <img class="qris-image"
                             src="<?= BASE_URL ?>uploads/qris/qris-tokoku.jpeg"
                             alt="QRIS TokoKu">
                    <?php else: ?>
                        <p>Gambar QRIS belum tersedia. Admin dapat menambahkannya nanti.</p>
                    <?php endif; ?>

                    <label>Upload Bukti Pembayaran</label>
                    <input type="file" name="bukti_pembayaran"
                           id="bukti" accept="image/jpeg,image/png,image/webp">
                    <small>Format JPG, PNG, atau WEBP. Maksimal 2 MB.</small>
                </div>

                <div id="kasirDetail" class="payment-detail" hidden>
                    <strong>Pilih Cabang Toko</strong>
                    <label>Cabang pembayaran</label>
                    <select name="id_cabang" id="id_cabang">
                        <option value="">-- Pilih cabang --</option>
                        <?php while ($c = $cabang->fetch_assoc()): ?>
                            <option value="<?= (int)$c['id_cabang'] ?>">
                                <?= e($c['nama_cabang']) ?> - <?= e($c['kota']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <p>Silakan datang ke cabang yang dipilih untuk melakukan pembayaran.</p>
                </div>

                <button type="submit" class="checkout-submit"
                        onclick="return confirm('Apakah data pesanan sudah benar?')">
                    Buat Pesanan
                </button>
            </form>
        </div>

        <div class="checkout-box">
            <h3>Ringkasan Belanja</h3>

            <?php foreach ($daftarProduk as $item): ?>
                <div class="checkout-item">
                    <div>
                        <strong><?= e($item['nama_produk']) ?></strong>
                        <div style="font-size:13px;color:#64748b">
                            <?= $item['jumlah'] ?> × <?= rupiah($item['harga']) ?>
                        </div>
                    </div>
                    <strong><?= rupiah($item['subtotal']) ?></strong>
                </div>
            <?php endforeach; ?>

            <div class="checkout-total">
                <span>Total</span>
                <span><?= rupiah($total) ?></span>
            </div>
        </div>
    </div>
</div>

<script>
const metode = document.querySelectorAll('input[name="metode_pembayaran"]');
const qrisDetail = document.getElementById('qrisDetail');
const kasirDetail = document.getElementById('kasirDetail');
const alamatBox = document.getElementById('alamatBox');
const bukti = document.getElementById('bukti');
const idCabang = document.getElementById('id_cabang');
const alamat = document.getElementById('alamat');
const kota = document.getElementById('kota');

function aturPembayaran() {
    const pilihan = document.querySelector('input[name="metode_pembayaran"]:checked')?.value;

    qrisDetail.hidden = pilihan !== 'QRIS';
    kasirDetail.hidden = pilihan !== 'KASIR';
    alamatBox.hidden = pilihan === 'KASIR';

    bukti.required = pilihan === 'QRIS';
    idCabang.required = pilihan === 'KASIR';
    alamat.required = pilihan !== 'KASIR';
    kota.required = pilihan !== 'KASIR';
}

metode.forEach(input => input.addEventListener('change', aturPembayaran));
aturPembayaran();
</script>

<?php require __DIR__ . "/../includes/footer.php"; ?>
