
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

$pesan = "";
$jenisPesan = "success";

// =====================================
// PROSES UPLOAD ULANG BUKTI QRIS
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_FILES['bukti_pembayaran']) ||
        $_FILES['bukti_pembayaran']['error'] !== UPLOAD_ERR_OK
    ) {

        $pesan = "Silakan pilih bukti pembayaran terlebih dahulu.";
        $jenisPesan = "danger";

    } else {

        // Periksa kepemilikan transaksi dan status pembayaran
        $stmtCek = $conn->prepare("
            SELECT id_transaksi
            FROM tbl_transaksi
            WHERE id_transaksi = ?
              AND id_user = ?
              AND metode_pembayaran = 'QRIS'
              AND status_pembayaran = 'belum_bayar'
            LIMIT 1
        ");

        $stmtCek->bind_param("ii", $idTransaksi, $idUser);
        $stmtCek->execute();

        $transaksiBolehUpload = $stmtCek->get_result()->fetch_assoc();

        $stmtCek->close();

        if (!$transaksiBolehUpload) {

            $pesan = "Transaksi tidak ditemukan atau belum dapat menerima bukti pembayaran baru.";
            $jenisPesan = "danger";

        } else {

            $file = $_FILES['bukti_pembayaran'];

            // Batas ukuran 2 MB
            $maksimalUkuran = 2 * 1024 * 1024;

            if ($file['size'] > $maksimalUkuran) {

                $pesan = "Ukuran file maksimal 2 MB.";
                $jenisPesan = "danger";

            } else {

                // Validasi MIME file
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                $daftarMime = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp'
                ];

                if (!isset($daftarMime[$mime])) {

                    $pesan = "Format file harus JPG, PNG, atau WEBP.";
                    $jenisPesan = "danger";

                } else {

                    $folderUpload = __DIR__ . "/../../assets/uploads/pembayaran/";

                    if (
                        !is_dir($folderUpload) &&
                        !mkdir($folderUpload, 0755, true)
                    ) {
                        $pesan = "Folder upload tidak dapat dibuat.";
                        $jenisPesan = "danger";
                    } else {

                        try {

                            $namaFile = "qris_" .
                                $idTransaksi . "_" .
                                bin2hex(random_bytes(12)) . "." .
                                $daftarMime[$mime];

                            $lokasiFile = $folderUpload . $namaFile;

                            $pathDatabase =
                                "assets/uploads/pembayaran/" . $namaFile;

                            if (!move_uploaded_file(
                                $file['tmp_name'],
                                $lokasiFile
                            )) {

                                $pesan = "Bukti pembayaran gagal diunggah.";
                                $jenisPesan = "danger";

                            } else {

                                // Update hanya jika status masih belum_bayar
                                $stmtUpdate = $conn->prepare("
                                    UPDATE tbl_transaksi
                                    SET bukti_pembayaran = ?,
                                        status_pembayaran = 'menunggu_verifikasi'
                                    WHERE id_transaksi = ?
                                      AND id_user = ?
                                      AND metode_pembayaran = 'QRIS'
                                      AND status_pembayaran = 'belum_bayar'
                                ");

                                $stmtUpdate->bind_param(
                                    "sii",
                                    $pathDatabase,
                                    $idTransaksi,
                                    $idUser
                                );

                                if ($stmtUpdate->execute()) {

                                    if ($stmtUpdate->affected_rows > 0) {

                                        $pesan = "Bukti pembayaran berhasil dikirim. Silakan tunggu verifikasi Admin.";
                                        $jenisPesan = "success";

                                    } else {

                                        $pesan = "Bukti pembayaran tidak dapat diproses. Silakan muat ulang halaman.";
                                        $jenisPesan = "danger";

                                        if (is_file($lokasiFile)) {
                                            unlink($lokasiFile);
                                        }
                                    }

                                } else {

                                    $pesan = "Terjadi kesalahan saat menyimpan bukti pembayaran.";
                                    $jenisPesan = "danger";

                                    if (is_file($lokasiFile)) {
                                        unlink($lokasiFile);
                                    }
                                }

                                $stmtUpdate->close();
                            }

                        } catch (Throwable $e) {

                            $pesan = "Terjadi kesalahan saat mengunggah bukti pembayaran.";
                            $jenisPesan = "danger";
                        }
                    }
                }
            }
        }
    }
}

// =====================================
// AMBIL INFORMASI TRANSAKSI
// =====================================

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
        t.bukti_pembayaran,
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

// =====================================
// AMBIL DETAIL PRODUK
// =====================================

$stmtDetail = $conn->prepare("
    SELECT nama_produk, jumlah, harga
    FROM tbl_detail_transaksi
    WHERE id_transaksi = ?
    ORDER BY id_detail ASC
");

$stmtDetail->bind_param("i", $idTransaksi);
$stmtDetail->execute();

$detailQuery = $stmtDetail->get_result();

$daftarProduk = [];
$totalBelanja = 0;

while ($item = $detailQuery->fetch_assoc()) {

    $harga = (float)$item['harga'];
    $jumlah = (int)$item['jumlah'];

    $item['subtotal_hitung'] = $harga * $jumlah;

    $totalBelanja += $item['subtotal_hitung'];

    $daftarProduk[] = $item;
}

// =====================================
// LABEL STATUS
// =====================================

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

$statusPembayaran = $transaksi['status_pembayaran'];
$isQris = strtoupper($transaksi['metode_pembayaran']) === 'QRIS';

$bisaUploadUlang =
    $isQris && $statusPembayaran === 'belum_bayar';

$buktiPembayaran = $transaksi['bukti_pembayaran'] ?? '';

$buktiUrl = '';

if (!empty($buktiPembayaran)) {
    $buktiUrl = BASE_URL . ltrim($buktiPembayaran, '/');
}

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
    border: none;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
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

.order-alert {
    padding: 13px 15px;
    margin-bottom: 18px;
    border-radius: 8px;
    font-size: 13px;
    line-height: 1.6;
}

.order-alert-success {
    background: #dcfce7;
    color: #166534;
}

.order-alert-danger {
    background: #fee2e2;
    color: #991b1b;
}

.order-alert-warning {
    background: #fef3c7;
    color: #92400e;
}

.order-alert-info {
    background: #dbeafe;
    color: #1e40af;
}

.payment-proof {
    max-width: 400px;
    max-height: 500px;
    width: 100%;
    object-fit: contain;
    border: 1px solid #e5eaf1;
    border-radius: 10px;
    padding: 8px;
}

.payment-upload {
    margin-top: 18px;
    padding: 18px;
    background: #f8fafc;
    border: 1px solid #e5eaf1;
    border-radius: 10px;
}

.payment-upload label {
    display: block;
    margin-bottom: 10px;
    color: #172554;
    font-size: 13px;
    font-weight: 600;
}

.payment-upload input[type="file"] {
    display: block;
    width: 100%;
    margin-bottom: 12px;
    padding: 10px;
    background: white;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    font-size: 13px;
}

.payment-upload small {
    display: block;
    margin-bottom: 15px;
    color: #64748b;
    font-size: 12px;
}

.order-submit {
    background: #2563eb;
    color: white;
}

.order-submit:hover {
    background: #1d4ed8;
}

@media (max-width: 600px) {
    .order-info-grid {
        grid-template-columns: 1fr;
    }

    .order-detail-panel {
        padding: 16px;
    }

    .order-total strong {
        font-size: 17px;
    }
}
</style>

<div class="order-detail-heading">
    <h2>Detail Pesanan</h2>
    <p>Informasi lengkap transaksi belanjamu.</p>
</div>

<?php if ($pesan !== ''): ?>
    <div class="order-alert order-alert-<?= e($jenisPesan) ?>">
        <?= e($pesan) ?>
    </div>
<?php endif; ?>

<!-- INFORMASI TRANSAKSI -->

<div class="order-detail-panel">
    <h3>Informasi Transaksi</h3>

    <div class="order-info-grid">

        <div class="order-info-item">
            <span>Nomor Transaksi</span>
            <strong>
                TRX-<?= str_pad(
                    (int)$transaksi['id_transaksi'],
                    5,
                    '0',
                    STR_PAD_LEFT
                ) ?>
            </strong>
        </div>

        <div class="order-info-item">
            <span>Tanggal Transaksi</span>
            <strong>
                <?= date(
                    'd-m-Y H:i',
                    strtotime($transaksi['tanggal'])
                ) ?>
            </strong>
        </div>

        <div class="order-info-item">
            <span>Nama Pelanggan</span>
            <strong>
                <?= e($transaksi['nama_pelanggan'] ?? '-') ?>
            </strong>
        </div>

        <div class="order-info-item">
            <span>Nomor HP</span>
            <strong>
                <?= e($transaksi['no_hp'] ?? '-') ?>
            </strong>
        </div>

        <div class="order-info-item">
            <span>Metode Pembayaran</span>
            <strong>
                <?= e(
                    $metode[$transaksi['metode_pembayaran']] ?? '-'
                ) ?>
            </strong>
        </div>

        <div class="order-info-item">
            <span>Status Pembayaran</span>
            <strong>
                <span class="order-badge">
                    <?= e(
                        $judulStatus[$statusPembayaran] ?? '-'
                    ) ?>
                </span>
            </strong>
        </div>

        <div class="order-info-item">
            <span>Status Pesanan</span>
            <strong>
                <span class="order-badge">
                    <?= e(
                        $judulStatus[$transaksi['status_pesanan']] ?? '-'
                    ) ?>
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

<!-- VERIFIKASI DAN UPLOAD BUKTI QRIS -->

<?php if ($isQris): ?>

<div class="order-detail-panel">

    <h3>💳 Pembayaran QRIS</h3>

    <?php if ($statusPembayaran === 'menunggu_verifikasi'): ?>

        <div class="order-alert order-alert-info">
            Bukti pembayaran sudah dikirim dan sedang diperiksa oleh Admin.
            Silakan tunggu sampai proses verifikasi selesai.
        </div>

    <?php elseif ($statusPembayaran === 'lunas'): ?>

        <div class="order-alert order-alert-success">
            Pembayaran berhasil diverifikasi. Terima kasih telah melakukan pembayaran.
        </div>

    <?php elseif ($statusPembayaran === 'belum_bayar'): ?>

        <div class="order-alert order-alert-warning">
            Pembayaran belum disetujui atau bukti sebelumnya ditolak Admin.
            Silakan unggah ulang bukti pembayaran QRIS yang benar.
        </div>

    <?php endif; ?>

    <?php if (!empty($buktiUrl)): ?>

        <div style="margin-bottom:20px;">
            <label style="display:block; margin-bottom:10px; font-weight:600;">
                Bukti Pembayaran
            </label>

            <a href="<?= e($buktiUrl) ?>"
               target="_blank"
               rel="noopener noreferrer">

                <img
                    src="<?= e($buktiUrl) ?>"
                    alt="Bukti Pembayaran QRIS"
                    class="payment-proof"
                >

            </a>
        </div>

    <?php endif; ?>

    <?php if ($bisaUploadUlang): ?>

        <form
            method="POST"
            enctype="multipart/form-data"
            class="payment-upload"
        >

            <label for="bukti_pembayaran">
                Upload Ulang Bukti Pembayaran
            </label>

            <input
                type="file"
                name="bukti_pembayaran"
                id="bukti_pembayaran"
                accept=".jpg,.jpeg,.png,.webp"
                required
            >

            <small>
                Format JPG, JPEG, PNG, atau WEBP. Maksimal ukuran file 2 MB.
                Pastikan bukti pembayaran terlihat jelas.
            </small>

            <button type="submit" class="order-button order-submit">
                📤 Kirim Bukti Pembayaran
            </button>

        </form>

    <?php endif; ?>

</div>

<?php endif; ?>

<!-- DETAIL PRODUK -->

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

                <?php if (count($daftarProduk) > 0): ?>

                    <?php foreach ($daftarProduk as $item): ?>

                        <tr>
                            <td><?= e($item['nama_produk']) ?></td>
                            <td><?= rupiah($item['harga']) ?></td>
                            <td><?= (int)$item['jumlah'] ?></td>
                            <td><?= rupiah($item['subtotal_hitung']) ?></td>
                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="4">
                            Detail produk tidak ditemukan.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>
    </div>

    <div class="order-total">
        <span>Total Belanja</span>
        <strong><?= rupiah($totalBelanja) ?></strong>
    </div>
</div>

<!-- TOMBOL AKSI -->

<div class="order-actions">

    <a
        href="cetak.php?id=<?= (int)$idTransaksi ?>"
        target="_blank"
        class="order-button order-print"
    >
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
