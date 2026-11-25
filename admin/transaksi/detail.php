
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";

wajib_admin();

$judul = "Detail Transaksi";
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: index.php");
    exit;
}

$pesan = "";
$jenisPesan = "success";

// =====================================
// PROSES PERSETUJUAN PEMBAYARAN QRIS
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'setujui' || $aksi === 'tolak') {

        // Pastikan transaksi merupakan pembayaran QRIS
        $stmtCek = mysqli_prepare(
            $conn,
            "SELECT metode_pembayaran, status_pembayaran
             FROM tbl_transaksi
             WHERE id_transaksi = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param($stmtCek, "i", $id);
        mysqli_stmt_execute($stmtCek);

        $dataCek = mysqli_fetch_assoc(
            mysqli_stmt_get_result($stmtCek)
        );

        mysqli_stmt_close($stmtCek);

        if (!$dataCek) {

            $pesan = "Transaksi tidak ditemukan.";
            $jenisPesan = "danger";

        } elseif (
            strtoupper($dataCek['metode_pembayaran']) !== 'QRIS'
        ) {

            $pesan = "Transaksi ini bukan pembayaran QRIS.";
            $jenisPesan = "danger";

        } elseif (
            $dataCek['status_pembayaran'] !== 'menunggu_verifikasi'
        ) {

            $pesan = "Pembayaran sudah diproses sebelumnya.";
            $jenisPesan = "warning";

        } else {

            if ($aksi === 'setujui') {

                $stmtUpdate = mysqli_prepare(
                    $conn,
                    "UPDATE tbl_transaksi
                     SET status_pembayaran = 'lunas'
                     WHERE id_transaksi = ?
                     AND metode_pembayaran = 'QRIS'
                     AND status_pembayaran = 'menunggu_verifikasi'"
                );

                mysqli_stmt_bind_param($stmtUpdate, "i", $id);

                $pesanBerhasil = "Pembayaran QRIS berhasil disetujui.";

            } else {

                $stmtUpdate = mysqli_prepare(
                    $conn,
                    "UPDATE tbl_transaksi
                     SET status_pembayaran = 'belum_bayar',
                         bukti_pembayaran = NULL
                     WHERE id_transaksi = ?
                     AND metode_pembayaran = 'QRIS'
                     AND status_pembayaran = 'menunggu_verifikasi'"
                );

                mysqli_stmt_bind_param($stmtUpdate, "i", $id);

                $pesanBerhasil = "Pembayaran QRIS ditolak.";
            }

            if (mysqli_stmt_execute($stmtUpdate)) {

                if (mysqli_stmt_affected_rows($stmtUpdate) > 0) {
                    $pesan = $pesanBerhasil;
                    $jenisPesan = "success";
                } else {
                    $pesan = "Status pembayaran gagal diperbarui. Silakan muat ulang halaman.";
                    $jenisPesan = "danger";
                }

            } else {

                $pesan = "Terjadi kesalahan saat memperbarui pembayaran.";
                $jenisPesan = "danger";
            }

            mysqli_stmt_close($stmtUpdate);
        }
    }
}

// =====================================
// AMBIL INFORMASI TRANSAKSI
// =====================================

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        t.id_transaksi,
        t.tanggal,
        t.metode_pembayaran,
        t.status_pembayaran,
        t.status_pesanan,
        t.bukti_pembayaran,
        p.nama_pelanggan,
        p.kota,
        u.nama AS nama_pengguna,
        u.role
     FROM tbl_transaksi t
     LEFT JOIN tbl_pelanggan p
        ON p.id_pelanggan = t.id_pelanggan
     LEFT JOIN tbl_users u
        ON u.id_user = t.id_user
     WHERE t.id_transaksi = ?"
);

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$transaksi = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmt)
);

mysqli_stmt_close($stmt);

if (!$transaksi) {
    header("Location: index.php?pesan=" .
        urlencode("Transaksi tidak ditemukan."));
    exit;
}

// =====================================
// AMBIL DETAIL PRODUK
// =====================================

$stmtDetail = mysqli_prepare(
    $conn,
    "SELECT nama_produk, jumlah, harga
     FROM tbl_detail_transaksi
     WHERE id_transaksi = ?
     ORDER BY id_detail ASC"
);

mysqli_stmt_bind_param($stmtDetail, "i", $id);
mysqli_stmt_execute($stmtDetail);

$resultDetail = mysqli_stmt_get_result($stmtDetail);

// Hitung subtotal dan total
$daftarProduk = [];
$totalItem = 0;
$totalPembayaran = 0;

while ($item = mysqli_fetch_assoc($resultDetail)) {

    $harga = (float)$item['harga'];
    $jumlah = (int)$item['jumlah'];

    $item['subtotal_hitung'] = $harga * $jumlah;

    $totalItem += $jumlah;
    $totalPembayaran += $item['subtotal_hitung'];

    $daftarProduk[] = $item;
}

mysqli_stmt_close($stmtDetail);

// =====================================
// STATUS PEMBAYARAN
// =====================================

$statusPembayaran = $transaksi['status_pembayaran'];

$labelPembayaran = [
    'belum_bayar' => 'Belum Dibayar',
    'menunggu_verifikasi' => 'Menunggu Verifikasi',
    'lunas' => 'Lunas'
];

$labelStatus = $labelPembayaran[$statusPembayaran]
    ?? ucfirst(str_replace('_', ' ', $statusPembayaran));

$warnaStatus = [
    'belum_bayar' => 'warning',
    'menunggu_verifikasi' => 'info',
    'lunas' => 'success'
];

$badgeStatus = $warnaStatus[$statusPembayaran] ?? 'secondary';

$metodePembayaran = strtoupper(
    $transaksi['metode_pembayaran'] ?? '-'
);

$isQris = $metodePembayaran === 'QRIS';

$menungguVerifikasi =
    $isQris &&
    $statusPembayaran === 'menunggu_verifikasi';

// =====================================
// PATH BUKTI PEMBAYARAN
// =====================================

$buktiPembayaran = $transaksi['bukti_pembayaran'] ?? '';

$buktiUrl = '';

if (!empty($buktiPembayaran)) {
    $buktiUrl = BASE_URL . ltrim($buktiPembayaran, '/');
}

require_once "../header.php";
?>

<div class="page-header">
    <div>
        <h1>Detail Transaksi #<?= $id ?></h1>
        <p>Informasi lengkap transaksi penjualan.</p>
    </div>

    <div class="action-buttons">
        <a href="cetak.php?id=<?= $id ?>"
           target="_blank"
           class="btn btn-success">
            🖨 Cetak Struk
        </a>

        <a href="index.php" class="btn btn-secondary">
            ← Kembali
        </a>
    </div>
</div>

<?php if ($pesan !== ''): ?>
    <div class="alert alert-<?= e($jenisPesan) ?>">
        <?= e($pesan) ?>
    </div>
<?php endif; ?>

<!-- =====================================
     INFORMASI TRANSAKSI
===================================== -->

<div class="content-card">

    <div class="card-header">
        <div>
            <h3>📋 Informasi Transaksi</h3>
            <p>Nomor transaksi: #<?= $id ?></p>
        </div>

        <span class="badge">Transaksi Tersimpan</span>
    </div>

    <div class="detail-grid">

        <div>
            <label>Tanggal Transaksi</label>
            <strong><?= e($transaksi['tanggal']) ?></strong>
        </div>

        <div>
            <label>Nama Pelanggan</label>
            <strong>
                <?= e($transaksi['nama_pelanggan'] ?? 'Umum') ?>
            </strong>
        </div>

        <div>
            <label>Kota</label>
            <strong>
                <?= e($transaksi['kota'] ?? '-') ?>
            </strong>
        </div>

        <div>
            <label>Petugas / Pengguna</label>
            <strong>
                <?= e($transaksi['nama_pengguna'] ?? 'Tidak tercatat') ?>
            </strong>
        </div>

        <div>
            <label>Role</label>
            <strong>
                <?= e(ucfirst($transaksi['role'] ?? '-')) ?>
            </strong>
        </div>

        <div>
            <label>Metode Pembayaran</label>
            <strong><?= e($metodePembayaran) ?></strong>
        </div>

        <div>
            <label>Status Pembayaran</label>
            <strong>
                <span class="badge badge-<?= e($badgeStatus) ?>">
                    <?= e($labelStatus) ?>
                </span>
            </strong>
        </div>

        <div>
            <label>Status Pesanan</label>
            <strong>
                <?= e(ucfirst(str_replace(
                    '_',
                    ' ',
                    $transaksi['status_pesanan'] ?? '-'
                ))) ?>
            </strong>
        </div>

    </div>

</div>

<!-- =====================================
     VERIFIKASI PEMBAYARAN QRIS
===================================== -->

<?php if ($isQris): ?>

<div class="content-card">

    <div class="card-header">
        <div>
            <h3>💳 Verifikasi Pembayaran QRIS</h3>
            <p>Periksa bukti pembayaran pelanggan sebelum menyetujui transaksi.</p>
        </div>
    </div>

    <div style="padding: 15px 0;">

        <div style="margin-bottom: 15px;">
            <strong>Status Pembayaran:</strong>

            <span class="badge badge-<?= e($badgeStatus) ?>">
                <?= e($labelStatus) ?>
            </span>
        </div>

        <?php if (!empty($buktiUrl)): ?>

            <div style="margin-bottom: 20px;">

                <label style="display:block; margin-bottom:10px;">
                    <strong>Bukti Pembayaran Pelanggan</strong>
                </label>

                <a href="<?= e($buktiUrl) ?>"
                   target="_blank"
                   rel="noopener noreferrer">

                    <img
                        src="<?= e($buktiUrl) ?>"
                        alt="Bukti Pembayaran QRIS"
                        style="
                            display:block;
                            max-width:100%;
                            width:400px;
                            max-height:500px;
                            object-fit:contain;
                            border:1px solid #ddd;
                            border-radius:10px;
                            padding:8px;
                        "
                    >

                </a>

                <small>
                    Klik gambar untuk melihat ukuran penuh.
                </small>

            </div>

        <?php else: ?>

            <div class="alert alert-warning">
                Bukti pembayaran belum tersedia.
            </div>

        <?php endif; ?>

        <?php if ($menungguVerifikasi && !empty($buktiUrl)): ?>

            <div class="alert alert-info">
                Pembayaran QRIS sedang menunggu persetujuan Admin.
                Pastikan bukti pembayaran sesuai dengan total transaksi.
            </div>

            <div class="action-buttons"
                 style="display:flex; gap:10px; flex-wrap:wrap;">

                <form method="POST"
                      onsubmit="return confirm('Apakah Anda yakin ingin menyetujui pembayaran ini?');">

                    <input type="hidden"
                           name="aksi"
                           value="setujui">

                    <button type="submit"
                            class="btn btn-success">
                        ✓ Setujui Pembayaran
                    </button>

                </form>

                <form method="POST"
                      onsubmit="return confirm('Apakah Anda yakin ingin menolak pembayaran ini?');">

                    <input type="hidden"
                           name="aksi"
                           value="tolak">

                    <button type="submit"
                            class="btn btn-danger">
                        ✕ Tolak Pembayaran
                    </button>

                </form>

            </div>

        <?php elseif ($statusPembayaran === 'lunas'): ?>

            <div class="alert alert-success">
                Pembayaran QRIS telah disetujui oleh Admin.
            </div>

        <?php elseif ($statusPembayaran === 'belum_bayar'): ?>

            <div class="alert alert-warning">
                Pembayaran belum lunas. Pelanggan perlu mengirimkan
                bukti pembayaran yang baru.
            </div>

        <?php endif; ?>

    </div>

</div>

<?php endif; ?>

<!-- =====================================
     DETAIL PRODUK
===================================== -->

<div class="content-card">

    <div class="card-header">
        <div>
            <h3>🛍️ Produk yang Dibeli</h3>
            <p>Rincian barang dalam transaksi ini.</p>
        </div>
    </div>

    <div class="table-responsive">

        <table class="table">

            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Produk</th>
                    <th>Jumlah</th>
                    <th>Harga</th>
                    <th>Subtotal</th>
                </tr>
            </thead>

            <tbody>

                <?php if (count($daftarProduk) > 0): ?>

                    <?php foreach ($daftarProduk as $index => $item): ?>

                        <tr>
                            <td><?= $index + 1 ?></td>

                            <td>
                                <?= e($item['nama_produk']) ?>
                            </td>

                            <td>
                                <?= (int)$item['jumlah'] ?>
                            </td>

                            <td>
                                <?= rupiah($item['harga']) ?>
                            </td>

                            <td>
                                <?= rupiah($item['subtotal_hitung']) ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="5" class="text-center">
                            Detail produk tidak ditemukan.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

    <div class="transaction-total">

        <div>
            <span>Total Item</span>
            <strong><?= $totalItem ?> barang</strong>
        </div>

        <div>
            <span>Total Pembayaran</span>
            <strong><?= rupiah($totalPembayaran) ?></strong>
        </div>

    </div>

</div>

<?php
require_once "../../includes/footer.php";
?>
