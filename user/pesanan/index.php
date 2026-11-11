
<?php
require_once __DIR__ . "/../../config/koneksi.php";
require_once __DIR__ . "/../../config/auth.php";

wajib_user();

$idUser = (int)($_SESSION['id_user'] ?? 0);
$judul = "Pesanan Saya";

$cari = trim($_GET['cari'] ?? '');

$sql = "
    SELECT
        t.id_transaksi,
        t.tanggal,
        t.total_belanja,
        p.nama_pelanggan
    FROM tbl_transaksi t
    LEFT JOIN tbl_pelanggan p
        ON t.id_pelanggan = p.id_pelanggan
    WHERE t.id_user = ?
";

if ($cari !== '') {
    $sql .= " AND (
        CAST(t.id_transaksi AS CHAR) LIKE ?
        OR p.nama_pelanggan LIKE ?
        OR CAST(t.tanggal AS CHAR) LIKE ?
    )";
}

$sql .= " ORDER BY t.id_transaksi DESC";

$stmt = $conn->prepare($sql);

if ($cari !== '') {
    $keyword = "%" . $cari . "%";
    $stmt->bind_param("isss", $idUser, $keyword, $keyword, $keyword);
} else {
    $stmt->bind_param("i", $idUser);
}

$stmt->execute();
$pesananQuery = $stmt->get_result();

$totalPesanan = $pesananQuery->num_rows;
$totalBelanja = 0;

$daftarPesanan = [];

while ($row = $pesananQuery->fetch_assoc()) {
    $daftarPesanan[] = $row;
    $totalBelanja += (float)$row['total_belanja'];
}

require_once __DIR__ . "/../header.php";
?>

<style>
.pesanan-heading {
    margin-bottom: 24px;
}

.pesanan-heading h2 {
    margin: 0 0 7px;
    color: #172554;
    font-size: 25px;
}

.pesanan-heading p {
    margin: 0;
    color: #64748b;
    font-size: 14px;
}

.pesanan-stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 23px;
}

.pesanan-stat {
    padding: 20px;
    background: #fff;
    border: 1px solid #e5eaf1;
    border-radius: 13px;
}

.pesanan-stat span {
    display: block;
    margin-bottom: 9px;
    color: #64748b;
    font-size: 13px;
}

.pesanan-stat strong {
    color: #172554;
    font-size: 23px;
}

.pesanan-panel {
    overflow: hidden;
    background: white;
    border: 1px solid #e5eaf1;
    border-radius: 13px;
}

.pesanan-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    padding: 19px 20px;
    border-bottom: 1px solid #e5eaf1;
}

.pesanan-panel-header h3 {
    margin: 0;
    color: #172554;
    font-size: 16px;
}

.pesanan-search {
    display: flex;
    gap: 8px;
}

.pesanan-search input {
    width: 220px;
    min-height: 39px;
    padding: 0 12px;
    border: 1px solid #dbe2ea;
    border-radius: 8px;
}

.pesanan-search button {
    padding: 0 15px;
    border: none;
    border-radius: 8px;
    background: #2563eb;
    color: white;
    cursor: pointer;
}

.pesanan-table-wrap {
    overflow-x: auto;
}

.pesanan-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
}

.pesanan-table th {
    padding: 14px 18px;
    background: #f8fafc;
    color: #64748b;
    font-size: 12px;
    text-align: left;
}

.pesanan-table td {
    padding: 16px 18px;
    border-top: 1px solid #edf0f4;
    color: #334155;
    font-size: 13px;
}

.pesanan-number {
    color: #2563eb;
    font-weight: 700;
}

.pesanan-total {
    color: #172554;
    font-weight: 700;
}

.pesanan-detail {
    display: inline-block;
    padding: 8px 13px;
    border-radius: 7px;
    background: #eff6ff;
    color: #2563eb;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
}

.pesanan-detail:hover {
    background: #dbeafe;
}

.pesanan-empty {
    padding: 55px 20px;
    text-align: center;
}

.pesanan-empty-icon {
    margin-bottom: 12px;
    font-size: 48px;
}

.pesanan-empty h3 {
    margin: 0 0 8px;
    color: #172554;
}

.pesanan-empty p {
    margin: 0 0 18px;
    color: #64748b;
    font-size: 14px;
}

.pesanan-empty a {
    display: inline-block;
    padding: 11px 18px;
    border-radius: 8px;
    background: #2563eb;
    color: white;
    text-decoration: none;
    font-size: 13px;
}

@media (max-width: 600px) {
    .pesanan-stats {
        grid-template-columns: 1fr;
    }

    .pesanan-search {
        width: 100%;
    }

    .pesanan-search input {
        width: 100%;
        min-width: 0;
    }

    .pesanan-panel-header {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>

<div class="pesanan-heading">
    <h2>Pesanan Saya</h2>
    <p>Lihat daftar transaksi dan riwayat belanjamu di TokoKu.</p>
</div>

<div class="pesanan-stats">
    <div class="pesanan-stat">
        <span>Total Transaksi</span>
        <strong><?= number_format($totalPesanan, 0, ',', '.') ?></strong>
    </div>

    <div class="pesanan-stat">
        <span>Total Belanja</span>
        <strong><?= rupiah($totalBelanja) ?></strong>
    </div>
</div>

<div class="pesanan-panel">

    <div class="pesanan-panel-header">
        <h3>Riwayat Transaksi</h3>

        <form method="GET" class="pesanan-search">
            <input
                type="text"
                name="cari"
                placeholder="Cari nomor atau tanggal..."
                value="<?= e($cari) ?>"
            >
            <button type="submit">Cari</button>
        </form>
    </div>

    <?php if (count($daftarPesanan) > 0): ?>

        <div class="pesanan-table-wrap">
            <table class="pesanan-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nomor Transaksi</th>
                        <th>Tanggal</th>
                        <th>Pelanggan</th>
                        <th>Total Belanja</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($daftarPesanan as $index => $pesanan): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>

                            <td class="pesanan-number">
                                TRX-<?= str_pad((int)$pesanan['id_transaksi'], 5, '0', STR_PAD_LEFT) ?>
                            </td>

                            <td>
                                <?= date('d-m-Y H:i', strtotime($pesanan['tanggal'])) ?>
                            </td>

                            <td>
                                <?= e($pesanan['nama_pelanggan'] ?? '-') ?>
                            </td>

                            <td class="pesanan-total">
                                <?= rupiah($pesanan['total_belanja']) ?>
                            </td>

                            <td>
                                <a
                                    href="detail.php?id=<?= (int)$pesanan['id_transaksi'] ?>"
                                    class="pesanan-detail"
                                >
                                    Detail →
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>

        <div class="pesanan-empty">
            <div class="pesanan-empty-icon">📦</div>
            <h3>Belum Ada Pesanan</h3>

            <p>
                <?= $cari !== ''
                    ? 'Transaksi yang kamu cari tidak ditemukan.'
                    : 'Kamu belum memiliki riwayat transaksi.' ?>
            </p>

            <a href="../produk/index.php">Mulai Belanja</a>
        </div>

    <?php endif; ?>

</div>

<?php
$stmt->close();
require_once __DIR__ . "/../includes/footer.php";
?>
