
<?php
require_once __DIR__ . "/../../config/koneksi.php";
require_once __DIR__ . "/../../config/auth.php";

wajib_kasir();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ambil produk
$produk = [];

$resultProduk = $conn->query("
    SELECT id_produk, nama_produk, harga, stok, foto, barcode
    FROM tbl_produk
    ORDER BY nama_produk ASC
");

if ($resultProduk) {
    while ($row = $resultProduk->fetch_assoc()) {
        $produk[] = $row;
    }
}

// Ambil pelanggan
$pelanggan = [];

$resultPelanggan = $conn->query("
    SELECT id_pelanggan, nama_pelanggan
    FROM tbl_pelanggan
    ORDER BY nama_pelanggan ASC
");

if ($resultPelanggan) {
    while ($row = $resultPelanggan->fetch_assoc()) {
        $pelanggan[] = $row;
    }
}
?>

<?php
if (file_exists(__DIR__ . "/../header.php")) {
    require_once __DIR__ . "/../header.php";
}
?>

<style>
.kasir-wrapper {
    padding: 20px;
    max-width: 1400px;
    margin: auto;
}

.kasir-title {
    font-size: 25px;
    font-weight: 700;
    margin-bottom: 5px;
}

.kasir-subtitle {
    color: #777;
    margin-bottom: 25px;
}

.kasir-layout {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 20px;
}

.kasir-panel {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 12px rgba(0,0,0,.06);
    margin-bottom: 20px;
}

.kasir-panel h3 {
    font-size: 18px;
    margin-bottom: 15px;
}

.scanner-panel {
    background: #f8fbff;
    border: 1px solid #dbeafe;
}

.scanner-input {
    width: 100%;
    padding: 16px;
    font-size: 18px;
    border: 2px solid #2563eb;
    border-radius: 9px;
    outline: none;
}

.scanner-input:focus {
    border-color: #1d4ed8;
    box-shadow: 0 0 0 3px rgba(37,99,235,.12);
}

.scanner-info {
    margin-top: 10px;
    font-size: 13px;
    color: #64748b;
}

.scanner-status {
    margin-top: 12px;
    padding: 10px;
    border-radius: 7px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 13px;
}

.scanner-status.success {
    background: #dcfce7;
    color: #166534;
}

.scanner-status.error {
    background: #fee2e2;
    color: #991b1b;
}

.produk-search {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    margin-bottom: 15px;
}

.produk-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 12px;
}

.produk-card {
    border: 1px solid #eee;
    border-radius: 10px;
    padding: 12px;
    transition: .2s;
}

.produk-card:hover {
    border-color: #2563eb;
    transform: translateY(-2px);
}

.produk-card img {
    width: 100%;
    height: 110px;
    object-fit: contain;
    margin-bottom: 8px;
}

.produk-card h4 {
    font-size: 14px;
    margin-bottom: 6px;
}

.produk-card p {
    font-size: 13px;
    margin: 3px 0;
}

.produk-harga {
    color: #2563eb;
    font-weight: bold;
}

.produk-stok {
    color: #777;
}

.btn-tambah {
    width: 100%;
    padding: 9px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 6px;
    margin-top: 8px;
    cursor: pointer;
}

.btn-tambah:disabled {
    background: #aaa;
    cursor: not-allowed;
}

.keranjang-table {
    width: 100%;
    border-collapse: collapse;
}

.keranjang-table th,
.keranjang-table td {
    padding: 10px 6px;
    border-bottom: 1px solid #eee;
    text-align: left;
    font-size: 13px;
}

.qty-control {
    display: flex;
    align-items: center;
    gap: 5px;
}

.qty-control button {
    width: 25px;
    height: 25px;
    border: none;
    background: #eee;
    border-radius: 5px;
    cursor: pointer;
}

.btn-hapus {
    color: #dc2626;
    background: none;
    border: none;
    cursor: pointer;
}

.total-box {
    display: flex;
    justify-content: space-between;
    font-size: 20px;
    font-weight: bold;
    padding: 15px 0;
    border-top: 2px solid #eee;
    margin-top: 10px;
}

.form-kasir label {
    display: block;
    font-weight: 600;
    margin: 12px 0 6px;
    font-size: 14px;
}

.form-kasir input,
.form-kasir select {
    width: 100%;
    padding: 11px;
    border: 1px solid #ddd;
    border-radius: 7px;
}

.btn-bayar {
    width: 100%;
    padding: 14px;
    margin-top: 18px;
    border: none;
    border-radius: 8px;
    background: #16a34a;
    color: white;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}

.btn-bayar:hover {
    background: #15803d;
}

.btn-bayar:disabled {
    background: #aaa;
    cursor: not-allowed;
}

.metode-pembayaran {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-top: 8px;
}

.metode-option {
    border: 2px solid #ddd;
    padding: 13px;
    border-radius: 8px;
    cursor: pointer;
    text-align: center;
    font-weight: bold;
}

.metode-option input {
    width: auto;
    margin-right: 5px;
}

.metode-option:has(input:checked) {
    border-color: #2563eb;
    background: #eff6ff;
    color: #1d4ed8;
}

.qris-container {
    display: none;
    margin-top: 18px;
    padding: 18px;
    border: 1px solid #ddd;
    border-radius: 10px;
    text-align: center;
    background: #fafafa;
}

.qris-container img {
    width: 100%;
    max-width: 320px;
    height: auto;
    object-fit: contain;
    margin: 15px auto;
    border-radius: 8px;
}

.qris-total {
    font-size: 23px;
    font-weight: bold;
    color: #16a34a;
    margin: 12px 0;
}

.qris-info {
    font-size: 13px;
    color: #64748b;
    line-height: 1.6;
}

.qris-konfirmasi {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    text-align: left;
    margin-top: 15px;
    padding: 12px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 13px;
}

.qris-konfirmasi input {
    width: auto;
    margin-top: 3px;
}

@media(max-width: 900px) {
    .kasir-layout {
        grid-template-columns: 1fr;
    }
}

@media(max-width: 500px) {
    .kasir-wrapper {
        padding: 12px;
    }

    .keranjang-table th,
    .keranjang-table td {
        padding: 7px 3px;
        font-size: 11px;
    }
}
</style>

<div class="kasir-wrapper">

    <h2 class="kasir-title">Transaksi Kasir</h2>

    <?php if (isset($_GET['pesan'])): ?>
    <div class="scanner-status error">
        <?= htmlspecialchars($_GET['pesan'], ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

    <p class="kasir-subtitle">
        Scan barcode produk atau pilih produk secara manual.
    </p>

    <div class="kasir-layout">

        <!-- KOLOM PRODUK -->
        <div>

            <!-- SCANNER USB -->
            <div class="kasir-panel scanner-panel">

                <h3>Scan Barcode Produk</h3>

                <input
                    type="text"
                    id="barcodeInput"
                    class="scanner-input"
                    placeholder="Arahkan scanner ke barcode produk..."
                    autocomplete="off"
                    autofocus
                >

                <div class="scanner-info">
                    Hubungkan scanner barcode USB ke laptop.
                    Hasil scan akan masuk otomatis ke kolom ini.
                </div>

                <div id="scannerStatus" class="scanner-status">
                    <strong>Siap Scan</strong> - Menunggu barcode produk.
                </div>

            </div>

            <!-- DAFTAR PRODUK -->
            <div class="kasir-panel">

                <h3>Daftar Produk</h3>

                <input
                    type="text"
                    id="cariProduk"
                    class="produk-search"
                    placeholder="Cari nama produk..."
                >

                <div class="produk-grid" id="produkGrid">

                    <?php foreach ($produk as $p): ?>

                        <div
                            class="produk-card"
                            data-nama="<?= htmlspecialchars(strtolower($p['nama_produk']), ENT_QUOTES, 'UTF-8') ?>"
                        >

                            <?php if (!empty($p['foto'])): ?>

                                <img
                                    src="<?= htmlspecialchars(BASE_URL . 'uploads/produk/' . $p['foto'], ENT_QUOTES, 'UTF-8') ?>"
                                    alt="Produk"
                                    onerror="this.style.display='none'"
                                >

                            <?php else: ?>

                                <img
                                    src="https://placehold.co/300x200?text=Produk"
                                    alt="Produk"
                                >

                            <?php endif; ?>

                            <h4>
                                <?= htmlspecialchars($p['nama_produk'], ENT_QUOTES, 'UTF-8') ?>
                            </h4>

                            <p class="produk-harga">
                                Rp <?= number_format($p['harga'], 0, ',', '.') ?>
                            </p>

                            <p class="produk-stok">
                                Stok: <?= (int)$p['stok'] ?>
                            </p>

                            <button
                                type="button"
                                class="btn-tambah"
                                onclick='tambahKeranjang(
                                    <?= (int)$p['id_produk'] ?>,
                                    <?= json_encode($p['nama_produk'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
                                    <?= (float)$p['harga'] ?>,
                                    <?= (int)$p['stok'] ?>
                                )'
                                <?= (int)$p['stok'] <= 0 ? 'disabled' : '' ?>
                            >
                                + Tambah
                            </button>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

        </div>

        <!-- KOLOM KERANJANG -->
        <div>

            <div class="kasir-panel">

                <h3>Keranjang Belanja</h3>

                <div style="overflow-x:auto;">

                    <table class="keranjang-table">

                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Qty</th>
                                <th>Subtotal</th>
                                <th></th>
                            </tr>
                        </thead>

                        <tbody id="keranjangBody">

                            <tr>
                                <td colspan="4" style="text-align:center;color:#888;">
                                    Belum ada produk
                                </td>
                            </tr>

                        </tbody>

                    </table>

                </div>

                <div class="total-box">
                    <span>Total</span>
                    <span id="totalBelanja">Rp 0</span>
                </div>

                <form
    action="proses.php"
    method="POST"
    class="form-kasir"
    id="formTransaksi"
>

    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
    >

    <input
        type="hidden"
        name="cart_json"
        id="cartJson"
    >

    <label>Pelanggan</label>

    <select name="id_pelanggan" id="idPelanggan" required>
        <option value="">Pilih Pelanggan</option>

        <?php foreach ($pelanggan as $pl): ?>
            <option value="<?= (int)$pl['id_pelanggan'] ?>">
                <?= htmlspecialchars($pl['nama_pelanggan'], ENT_QUOTES, 'UTF-8') ?>
            </option>
        <?php endforeach; ?>

    </select>

    <label>Metode Pembayaran</label>

    <div class="metode-pembayaran">

        <label class="metode-option">
            <input
                type="radio"
                name="metode_pembayaran"
                value="tunai"
                checked
                onchange="ubahMetodePembayaran()"
            >
            Tunai
        </label>

        <label class="metode-option">
            <input
                type="radio"
                name="metode_pembayaran"
                value="qris"
                onchange="ubahMetodePembayaran()"
            >
            QRIS
        </label>

    </div>

    <!-- PEMBAYARAN TUNAI -->

    <div id="formTunai">

        <label>Uang Diterima</label>

        <input
            type="number"
            name="uang_diterima"
            id="uangDiterima"
            min="0"
            placeholder="Masukkan uang diterima"
            required
        >

        <label>Kembalian</label>

        <input
            type="text"
            id="kembalian"
            value="Rp 0"
            readonly
        >

    </div>

    <!-- PEMBAYARAN QRIS -->

    <div class="qris-container" id="qrisContainer">

        <h3>Pembayaran QRIS</h3>

        <p class="qris-info">
            Silakan scan QRIS berikut menggunakan aplikasi
            mobile banking atau dompet digital yang mendukung QRIS.
        </p>

        <img
            src="<?= htmlspecialchars(BASE_URL . 'uploads/qris/qris-tokoku.jpeg', ENT_QUOTES, 'UTF-8') ?>"
            alt="QRIS TokoKu"
            onerror="this.style.display='none'; document.getElementById('qrisError').style.display='block';"
        >

        <p id="qrisError" style="display:none;color:#dc2626;">
            Gambar QRIS tidak ditemukan. Periksa lokasi file QRIS.
        </p>

        <div class="qris-total" id="totalQris">
            Rp 0
        </div>

        <p class="qris-info">
            Pastikan nominal pembayaran sesuai dengan total belanja.
            Periksa notifikasi atau riwayat transaksi pada aplikasi
            penerima sebelum mengonfirmasi pembayaran.
        </p>

        <label class="qris-konfirmasi">

            <input
                type="checkbox"
                name="qris_konfirmasi"
                id="qrisKonfirmasi"
                value="1"
            >

            <span>
                Saya sudah memeriksa pembayaran masuk dan memastikan
                pembayaran QRIS telah diterima oleh toko.
            </span>

        </label>

    </div>

    <button
        type="submit"
        class="btn-bayar"
        id="btnBayar"
    >
        Proses Pembayaran
    </button>

</form>

            </div>

        </div>

    </div>

</div>

<script>
const keranjang = {};

const barcodeInput = document.getElementById("barcodeInput");
const scannerStatus = document.getElementById("scannerStatus");

// =====================================
// METODE PEMBAYARAN
// =====================================

function getMetodePembayaran() {
    return document.querySelector(
        'input[name="metode_pembayaran"]:checked'
    ).value;
}

function ubahMetodePembayaran() {

    const metode = getMetodePembayaran();

    const formTunai = document.getElementById("formTunai");
    const qrisContainer = document.getElementById("qrisContainer");

    const uangDiterima = document.getElementById("uangDiterima");
    const qrisKonfirmasi = document.getElementById("qrisKonfirmasi");

    if (metode === "qris") {

        formTunai.style.display = "none";
        qrisContainer.style.display = "block";

        uangDiterima.required = false;
        uangDiterima.disabled = true;

        qrisKonfirmasi.required = true;

    } else {

        formTunai.style.display = "block";
        qrisContainer.style.display = "none";

        uangDiterima.required = true;
        uangDiterima.disabled = false;

        qrisKonfirmasi.required = false;
        qrisKonfirmasi.checked = false;

    }

    hitungKembalian();
}

// =====================================
// FORMAT RUPIAH
// =====================================

function formatRupiah(angka) {
    return "Rp " + Number(angka).toLocaleString("id-ID");
}

// =====================================
// STATUS SCANNER
// =====================================

function statusScanner(pesan, jenis = "") {

    scannerStatus.className = "scanner-status";

    if (jenis) {
        scannerStatus.classList.add(jenis);
    }

    scannerStatus.textContent = pesan;
}

// =====================================
// TAMBAH PRODUK
// =====================================

function tambahKeranjang(id, nama, harga, stok) {

    id = Number(id);
    harga = Number(harga);
    stok = Number(stok);

    if (stok <= 0) {
        alert("Stok produk habis!");
        return;
    }

    if (keranjang[id]) {

        if (keranjang[id].jumlah >= stok) {
            alert("Jumlah melebihi stok tersedia!");
            return;
        }

        keranjang[id].jumlah++;

    } else {

        keranjang[id] = {
            id: id,
            nama: nama,
            harga: harga,
            stok: stok,
            jumlah: 1
        };

    }

    tampilkanKeranjang();
}

// =====================================
// TAMPILKAN KERANJANG
// =====================================

function tampilkanKeranjang() {

    const tbody = document.getElementById("keranjangBody");

    tbody.innerHTML = "";

    let total = 0;
    const dataKeranjang = [];

    const daftar = Object.values(keranjang);

    if (daftar.length === 0) {

        tbody.innerHTML = `
            <tr>
                <td colspan="4" style="text-align:center;color:#888;">
                    Belum ada produk
                </td>
            </tr>
        `;

    }

    daftar.forEach(item => {

        const subtotal = item.harga * item.jumlah;

        total += subtotal;

        dataKeranjang.push({
            id: item.id,
            jumlah: item.jumlah
        });

        const tr = document.createElement("tr");

        const tdProduk = document.createElement("td");
        tdProduk.textContent = item.nama;

        const tdQty = document.createElement("td");

        const qtyControl = document.createElement("div");
        qtyControl.className = "qty-control";

        const btnKurang = document.createElement("button");
        btnKurang.type = "button";
        btnKurang.textContent = "-";
        btnKurang.addEventListener("click", () => ubahJumlah(item.id, -1));

        const jumlah = document.createElement("span");
        jumlah.textContent = item.jumlah;

        const btnTambah = document.createElement("button");
        btnTambah.type = "button";
        btnTambah.textContent = "+";
        btnTambah.addEventListener("click", () => ubahJumlah(item.id, 1));

        qtyControl.append(btnKurang, jumlah, btnTambah);
        tdQty.appendChild(qtyControl);

        const tdSubtotal = document.createElement("td");
        tdSubtotal.textContent = formatRupiah(subtotal);

        const tdHapus = document.createElement("td");

        const btnHapus = document.createElement("button");
        btnHapus.type = "button";
        btnHapus.className = "btn-hapus";
        btnHapus.textContent = "Hapus";
        btnHapus.addEventListener("click", () => hapusProduk(item.id));

        tdHapus.appendChild(btnHapus);

        tr.append(tdProduk, tdQty, tdSubtotal, tdHapus);

        tbody.appendChild(tr);

    });

    document.getElementById("totalBelanja").textContent =
        formatRupiah(total);

    document.getElementById("cartJson").value =
        JSON.stringify(dataKeranjang);

    hitungKembalian();
}

// =====================================
// UBAH JUMLAH
// =====================================

function ubahJumlah(id, perubahan) {

    if (!keranjang[id]) return;

    const jumlahBaru = keranjang[id].jumlah + perubahan;

    if (jumlahBaru <= 0) {

        delete keranjang[id];

    } else if (jumlahBaru > keranjang[id].stok) {

        alert("Jumlah melebihi stok tersedia!");

        return;

    } else {

        keranjang[id].jumlah = jumlahBaru;

    }

    tampilkanKeranjang();
}

// =====================================
// HAPUS PRODUK
// =====================================

function hapusProduk(id) {

    delete keranjang[id];

    tampilkanKeranjang();
}

// =====================================
// HITUNG KEMBALIAN
// =====================================

function hitungKembalian() {

    let total = 0;

    Object.values(keranjang).forEach(item => {
        total += item.harga * item.jumlah;
    });

    const metode = getMetodePembayaran();

    document.getElementById("totalQris").textContent =
        formatRupiah(total);

    if (metode === "qris") {
        document.getElementById("kembalian").value = formatRupiah(0);
        return;
    }

    const uang = Number(
        document.getElementById("uangDiterima").value
    ) || 0;

    const kembali = Math.max(0, uang - total);

    document.getElementById("kembalian").value =
        formatRupiah(kembali);
}

document.getElementById("uangDiterima")
    .addEventListener("input", hitungKembalian);

// =====================================
// CARI PRODUK BERDASARKAN BARCODE
// =====================================

async function cariBarcode(kode) {

    kode = String(kode).trim();

    if (!kode) return;

    statusScanner("Mencari produk...");

    try {

        const response = await fetch(
            "cari_barcode.php?barcode=" + encodeURIComponent(kode)
        );

        const data = await response.json();

        if (!data.success) {

            statusScanner(
                data.message || "Produk tidak ditemukan.",
                "error"
            );

            return;

        }

        const p = data.product;

        tambahKeranjang(
            p.id_produk,
            p.nama_produk,
            p.harga,
            p.stok
        );

        statusScanner(
            "Berhasil: " + p.nama_produk,
            "success"
        );

    } catch (error) {

        console.error(error);

        statusScanner(
            "Gagal menghubungi server.",
            "error"
        );

    }
}

// =====================================
// INPUT SCANNER USB
// =====================================

barcodeInput.addEventListener("keydown", function(e) {

    if (e.key === "Enter") {

        e.preventDefault();

        const kode = barcodeInput.value.trim();

        barcodeInput.value = "";

        if (kode !== "") {
            cariBarcode(kode);
        }

    }

});

// =====================================
// PENCARIAN PRODUK MANUAL
// =====================================

document.getElementById("cariProduk")
    .addEventListener("input", function() {

        const keyword = this.value.toLowerCase();

        document.querySelectorAll(".produk-card")
            .forEach(card => {

                const nama = card.dataset.nama || "";

                card.style.display =
                    nama.includes(keyword) ? "" : "none";

            });

    });

// =====================================
// VALIDASI PEMBAYARAN
// =====================================

// =====================================
// VALIDASI PEMBAYARAN
// =====================================

document.getElementById("formTransaksi")
    .addEventListener("submit", function(e) {

        const daftar = Object.values(keranjang);

        if (daftar.length === 0) {
            e.preventDefault();
            alert("Keranjang masih kosong!");
            return;
        }

        const total = daftar.reduce(
            (sum, item) => sum + item.harga * item.jumlah,
            0
        );

        const metode = getMetodePembayaran();

        if (!document.getElementById("idPelanggan").value) {
            e.preventDefault();
            alert("Silakan pilih pelanggan!");
            return;
        }

        // Validasi tunai
        if (metode === "tunai") {

            const uang = Number(
                document.getElementById("uangDiterima").value
            ) || 0;

            if (uang < total) {
                e.preventDefault();
                alert("Uang diterima kurang dari total belanja!");
                return;
            }

        }

        // Validasi QRIS
        if (metode === "qris") {

            const konfirmasi =
                document.getElementById("qrisKonfirmasi").checked;

            if (!konfirmasi) {
                e.preventDefault();

                alert(
                    "Silakan konfirmasi bahwa pembayaran QRIS sudah diterima."
                );

                return;
            }

        }

        document.getElementById("btnBayar").disabled = true;

    });

// =====================================
// FOKUS KEMBALI KE SCANNER
// =====================================

document.addEventListener("click", function(e) {

    if (
        !e.target.closest("input") &&
        !e.target.closest("select") &&
        !e.target.closest("button")
    ) {
        barcodeInput.focus();
    }

});

// Fokus awal
window.addEventListener("load", function() {
    ubahMetodePembayaran();
    barcodeInput.focus();
});

</script>

<?php
$footer = __DIR__ . "/../includes/footer.php";

if (file_exists($footer)) {
    require_once $footer;
}
?>
