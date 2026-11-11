
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";

wajib_kasir();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_kasir'])) {
    $_SESSION['csrf_kasir'] = bin2hex(random_bytes(32));
}

$produk = $conn->query("
    SELECT id_produk, nama_produk, harga, stok, foto
    FROM tbl_produk
    WHERE stok > 0
    ORDER BY nama_produk ASC
");

$pelanggan = $conn->query("
    SELECT id_pelanggan, nama_pelanggan
    FROM tbl_pelanggan
    ORDER BY nama_pelanggan ASC
");

$judul = "Transaksi Baru";
require_once "../header.php";
?>

<div class="kasir-page-header">
    <div>
        <h1>Transaksi Baru</h1>
        <p>Pilih produk dan masukkan ke keranjang belanja.</p>
    </div>
    <a href="riwayat.php" class="kasir-btn kasir-btn-secondary">
        Riwayat Transaksi
    </a>
</div>

<form action="proses.php" method="POST" id="formTransaksi">
    <input type="hidden" name="csrf_token"
           value="<?= e($_SESSION['csrf_kasir']) ?>">
    <input type="hidden" name="cart_json" id="cart_json">

    <div class="kasir-pos-grid">

        <div class="kasir-panel">
            <div class="kasir-panel-header">
                <div>
                    <h3>Daftar Produk</h3>
                    <p>Pilih produk yang akan dibeli.</p>
                </div>
            </div>

            <div class="kasir-product-search">
                <input type="text" id="cariProduk"
                       placeholder="Cari nama produk...">
            </div>

            <div class="kasir-product-grid" id="daftarProduk">
                <?php while ($p = $produk->fetch_assoc()): ?>
                    <div class="kasir-product-card"
                         data-nama="<?= e(strtolower($p['nama_produk'])) ?>">

                        <?php if (!empty($p['foto'])): ?>
                            <img
                                src="<?= BASE_URL . e($p['foto']) ?>"
                                alt="<?= e($p['nama_produk']) ?>"
                            >
                        <?php else: ?>
                            <div class="kasir-product-placeholder">▣</div>
                        <?php endif; ?>

                        <div class="kasir-product-info">
                            <h4><?= e($p['nama_produk']) ?></h4>
                            <strong><?= rupiah($p['harga']) ?></strong>
                            <small>Stok: <?= (int)$p['stok'] ?></small>
                        </div>

                        <button
                            type="button"
                            class="kasir-btn kasir-add-product"
                            data-id="<?= (int)$p['id_produk'] ?>"
                            data-nama="<?= e($p['nama_produk']) ?>"
                            data-harga="<?= (float)$p['harga'] ?>"
                            data-stok="<?= (int)$p['stok'] ?>"
                        >
                            + Tambah
                        </button>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <div class="kasir-panel kasir-cart-panel">
            <div class="kasir-panel-header">
                <div>
                    <h3>Keranjang Belanja</h3>
                    <p>Daftar produk yang dipilih.</p>
                </div>
                <span class="kasir-cart-count" id="jumlahItem">0 item</span>
            </div>

            <div id="keranjangKosong" class="kasir-empty-cart">
                <div>🛒</div>
                <strong>Keranjang masih kosong</strong>
                <p>Tambahkan produk untuk memulai transaksi.</p>
            </div>

            <div id="daftarKeranjang" class="kasir-cart-list"></div>

            <div class="kasir-cart-summary">
                <div class="kasir-form-group">
                    <label for="id_pelanggan">Pelanggan</label>
                    <select name="id_pelanggan" id="id_pelanggan">
                        <option value="">Pelanggan Umum</option>
                        <?php while ($pl = $pelanggan->fetch_assoc()): ?>
                            <option value="<?= (int)$pl['id_pelanggan'] ?>">
                                <?= e($pl['nama_pelanggan']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="kasir-total-row">
                    <span>Total Belanja</span>
                    <strong id="totalBelanja">Rp 0</strong>
                </div>

                <div class="kasir-form-group">
                    <label for="uangBayar">Uang Pembayaran (Rp)</label>
                    <input type="number"
       id="uangBayar"
       name="uang_diterima"
       min="0"
       step="1"
       required
       placeholder="Masukkan uang pembayaran">
                </div>

                <div class="kasir-total-row">
                    <span>Kembalian</span>
                    <strong id="uangKembalian">Rp 0</strong>
                </div>

                <button type="submit" class="kasir-btn kasir-pay-button"
                        id="tombolBayar" disabled>
                    Proses Pembayaran
                </button>

                <button type="button" class="kasir-btn kasir-btn-secondary kasir-clear-button"
                        id="kosongkanKeranjang">
                    Kosongkan Keranjang
                </button>
            </div>
        </div>

    </div>
</form>

<script>
const keranjang = {};

const formatRupiah = angka =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0
    }).format(angka);

function hitungTotal() {
    return Object.values(keranjang).reduce(
        (total, item) => total + item.harga * item.jumlah, 0
    );
}

function tampilkanKeranjang() {
    const daftar = document.getElementById('daftarKeranjang');
    const kosong = document.getElementById('keranjangKosong');
    const total = hitungTotal();
    const jumlah = Object.values(keranjang).reduce(
        (hasil, item) => hasil + item.jumlah, 0
    );

    daftar.innerHTML = '';

    const itemKeranjang = Object.values(keranjang);

    kosong.style.display = itemKeranjang.length ? 'none' : 'block';

    itemKeranjang.forEach(item => {
        const subtotal = item.harga * item.jumlah;

        const baris = document.createElement('div');
        baris.className = 'kasir-cart-item';

        const nama = document.createElement('strong');
        nama.textContent = item.nama;

        const harga = document.createElement('small');
        harga.textContent = formatRupiah(item.harga) + ' / item';

        const info = document.createElement('div');
        info.className = 'kasir-cart-item-info';
        info.append(nama, harga);

        const kontrol = document.createElement('div');
        kontrol.className = 'kasir-quantity-control';

        const kurang = document.createElement('button');
        kurang.type = 'button';
        kurang.textContent = '−';
        kurang.onclick = () => ubahJumlah(item.id, -1);

        const angka = document.createElement('span');
        angka.textContent = item.jumlah;

        const tambah = document.createElement('button');
        tambah.type = 'button';
        tambah.textContent = '+';
        tambah.onclick = () => ubahJumlah(item.id, 1);

        kontrol.append(kurang, angka, tambah);

        const subtotalEl = document.createElement('strong');
        subtotalEl.textContent = formatRupiah(subtotal);

        const hapus = document.createElement('button');
        hapus.type = 'button';
        hapus.className = 'kasir-remove-item';
        hapus.textContent = 'Hapus';
        hapus.onclick = () => {
            delete keranjang[item.id];
            tampilkanKeranjang();
        };

        const sisiKanan = document.createElement('div');
        sisiKanan.className = 'kasir-cart-item-action';
        sisiKanan.append(subtotalEl, hapus);

        baris.append(info, kontrol, sisiKanan);
        daftar.appendChild(baris);
    });

    document.getElementById('jumlahItem').textContent =
        jumlah + ' item';

    document.getElementById('totalBelanja').textContent =
        formatRupiah(total);

    document.getElementById('cart_json').value =
        JSON.stringify(itemKeranjang.map(item => ({
            id: item.id,
            jumlah: item.jumlah
        })));

    document.getElementById('tombolBayar').disabled =
        itemKeranjang.length === 0;

    hitungKembalian();
}

function ubahJumlah(id, perubahan) {
    if (!keranjang[id]) return;

    const jumlahBaru = keranjang[id].jumlah + perubahan;

    if (jumlahBaru <= 0) {
        delete keranjang[id];
    } else if (jumlahBaru <= keranjang[id].stok) {
        keranjang[id].jumlah = jumlahBaru;
    } else {
        alert('Jumlah melebihi stok yang tersedia.');
    }

    tampilkanKeranjang();
}

document.querySelectorAll('.kasir-add-product').forEach(tombol => {
    tombol.addEventListener('click', () => {
        const id = tombol.dataset.id;

        if (!keranjang[id]) {
            keranjang[id] = {
                id: Number(id),
                nama: tombol.dataset.nama,
                harga: Number(tombol.dataset.harga),
                stok: Number(tombol.dataset.stok),
                jumlah: 1
            };
        } else if (keranjang[id].jumlah < keranjang[id].stok) {
            keranjang[id].jumlah++;
        } else {
            alert('Stok produk tidak mencukupi.');
        }

        tampilkanKeranjang();
    });
});

function hitungKembalian() {
    const bayar = Number(document.getElementById('uangBayar').value) || 0;
    const total = hitungTotal();
    const kembalian = Math.max(0, bayar - total);

    document.getElementById('uangKembalian').textContent =
        formatRupiah(kembalian);
}

document.getElementById('uangBayar').addEventListener(
    'input', hitungKembalian
);

document.getElementById('kosongkanKeranjang').addEventListener('click', () => {
    if (confirm('Kosongkan semua produk dari keranjang?')) {
        Object.keys(keranjang).forEach(id => delete keranjang[id]);
        tampilkanKeranjang();
    }
});

document.getElementById('cariProduk').addEventListener('input', function() {
    const kata = this.value.toLowerCase();

    document.querySelectorAll('.kasir-product-card').forEach(card => {
        card.style.display = card.dataset.nama.includes(kata)
            ? ''
            : 'none';
    });
});

document.getElementById('formTransaksi').addEventListener('submit', function(e) {
    const total = hitungTotal();
    const bayar = Number(document.getElementById('uangBayar').value) || 0;

    if (total <= 0) {
        e.preventDefault();
        alert('Keranjang masih kosong.');
        return;
    }

    if (bayar < total) {
        e.preventDefault();
        alert('Uang pembayaran belum mencukupi.');
        return;
    }

    if (!confirm('Lanjutkan pembayaran sebesar ' + formatRupiah(total) + '?')) {
        e.preventDefault();
    }
});

tampilkanKeranjang();
</script>

<?php require_once "../includes/footer.php"; ?>
