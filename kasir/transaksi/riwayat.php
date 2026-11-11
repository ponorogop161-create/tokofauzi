
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";

wajib_kasir();

$idUser = (int)($_SESSION['id_user'] ?? 0);

// Filter pencarian
$cari = trim($_GET['cari'] ?? '');
$tanggalMulai = trim($_GET['mulai'] ?? '');
$tanggalAkhir = trim($_GET['akhir'] ?? '');

$halaman = max(1, (int)($_GET['halaman'] ?? 1));
$batas = 10;
$offset = ($halaman - 1) * $batas;

// Validasi tanggal
if ($tanggalMulai !== '' &&
    !DateTime::createFromFormat('Y-m-d', $tanggalMulai)) {
    $tanggalMulai = '';
}

if ($tanggalAkhir !== '' &&
    !DateTime::createFromFormat('Y-m-d', $tanggalAkhir)) {
    $tanggalAkhir = '';
}

// Susun kondisi query
$kondisi = " WHERE t.id_user = ?";
$types = "i";
$params = [$idUser];

if ($cari !== '') {
    $kondisi .= " AND (
        CAST(t.id_transaksi AS CHAR) LIKE ?
        OR COALESCE(p.nama_pelanggan, 'Pelanggan Umum') LIKE ?
    )";

    $keyword = "%" . $cari . "%";
    $types .= "ss";
    $params[] = $keyword;
    $params[] = $keyword;
}

if ($tanggalMulai !== '') {
    $kondisi .= " AND DATE(t.tanggal) >= ?";
    $types .= "s";
    $params[] = $tanggalMulai;
}

if ($tanggalAkhir !== '') {
    $kondisi .= " AND DATE(t.tanggal) <= ?";
    $types .= "s";
    $params[] = $tanggalAkhir;
}

// Helper untuk prepared statement dinamis
function jalankanQuery(mysqli $conn, string $sql, string $types, array $params): mysqli_stmt
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Query gagal disiapkan: " . $conn->error);
    }

    if ($types !== '') {
        $args = [$types];

        foreach ($params as $key => $value) {
            $args[] = &$params[$key];
        }

        call_user_func_array([$stmt, 'bind_param'], $args);
    }

    $stmt->execute();

    return $stmt;
}

// Statistik transaksi sesuai filter
$sqlStatistik = "
    SELECT
        COUNT(*) AS jumlah_transaksi,
        COALESCE(SUM(t.total_belanja), 0) AS total_penjualan
    FROM tbl_transaksi t
    LEFT JOIN tbl_pelanggan p
        ON t.id_pelanggan = p.id_pelanggan
    $kondisi
";

$stmtStatistik = jalankanQuery(
    $conn,
    $sqlStatistik,
    $types,
    $params
);

$statistik = $stmtStatistik->get_result()->fetch_assoc();
$stmtStatistik->close();

// Hitung jumlah halaman
$totalData = (int)$statistik['jumlah_transaksi'];
$totalHalaman = max(1, (int)ceil($totalData / $batas));

if ($halaman > $totalHalaman) {
    $halaman = $totalHalaman;
    $offset = ($halaman - 1) * $batas;
}

// Ambil data transaksi
$sql = "
    SELECT
        t.id_transaksi,
        t.tanggal,
        t.total_belanja,
        t.uang_diterima,
        t.kembalian,
        p.nama_pelanggan
    FROM tbl_transaksi t
    LEFT JOIN tbl_pelanggan p
        ON t.id_pelanggan = p.id_pelanggan
    $kondisi
    ORDER BY t.id_transaksi DESC
    LIMIT ? OFFSET ?
";

$typesData = $types . "ii";
$paramsData = $params;
$paramsData[] = $batas;
$paramsData[] = $offset;

$stmt = jalankanQuery(
    $conn,
    $sql,
    $typesData,
    $paramsData
);

$transaksi = $stmt->get_result();

$judul = "Riwayat Transaksi";
require_once "../header.php";
?>

<div class="kasir-page-header">
    <div>
        <h1>Riwayat Transaksi</h1>
        <p>Lihat dan cari transaksi penjualan yang pernah kamu lakukan.</p>
    </div>

    <a href="<?= BASE_URL ?>kasir/transaksi/index.php"
       class="kasir-btn">
        + Transaksi Baru
    </a>
</div>

<?php if (!empty($_GET['pesan'])): ?>
    <div class="kasir-alert">
        <?= e($_GET['pesan']) ?>
    </div>
<?php endif; ?>

<!-- STATISTIK -->
<div class="kasir-stats">

    <div class="kasir-stat-card">
        <div class="kasir-stat-icon">▤</div>
        <div>
            <p>Jumlah Transaksi</p>
            <h2><?= $totalData ?></h2>
        </div>
    </div>

    <div class="kasir-stat-card">
        <div class="kasir-stat-icon">Rp</div>
        <div>
            <p>Total Penjualan</p>
            <h2><?= rupiah($statistik['total_penjualan']) ?></h2>
        </div>
    </div>

</div>

<!-- FILTER -->
<div class="kasir-panel">
    <div class="kasir-panel-header">
        <div>
            <h3>Filter Riwayat</h3>
            <p>Cari berdasarkan nomor transaksi, pelanggan, atau tanggal.</p>
        </div>
    </div>

    <form method="GET" class="riwayat-filter">
        <div class="riwayat-filter-search">
            <label for="cari">Pencarian</label>
            <input
                type="text"
                id="cari"
                name="cari"
                value="<?= e($cari) ?>"
                placeholder="Nomor transaksi atau nama pelanggan"
            >
        </div>

        <div>
            <label for="mulai">Dari Tanggal</label>
            <input
                type="date"
                id="mulai"
                name="mulai"
                value="<?= e($tanggalMulai) ?>"
            >
        </div>

        <div>
            <label for="akhir">Sampai Tanggal</label>
            <input
                type="date"
                id="akhir"
                name="akhir"
                value="<?= e($tanggalAkhir) ?>"
            >
        </div>

        <div class="riwayat-filter-actions">
            <button type="submit" class="kasir-btn">
                Cari
            </button>

            <a href="riwayat.php"
               class="kasir-btn kasir-btn-secondary">
                Reset
            </a>
        </div>
    </form>
</div>

<!-- TABEL -->
<div class="kasir-panel">
    <div class="kasir-panel-header">
        <div>
            <h3>Daftar Transaksi</h3>
            <p>
                Menampilkan <?= $transaksi->num_rows ?> dari
                <?= $totalData ?> transaksi.
            </p>
        </div>
    </div>

    <div class="kasir-table-wrap">
        <table class="kasir-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>ID Transaksi</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>Total</th>
                    <th>Tunai</th>
                    <th>Kembalian</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($transaksi->num_rows > 0): ?>
                    <?php $no = $offset + 1; ?>

                    <?php while ($row = $transaksi->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>

                            <td>
                                <strong>
                                    #<?= (int)$row['id_transaksi'] ?>
                                </strong>
                            </td>

                            <td>
                                <?= e(date(
                                    'd-m-Y',
                                    strtotime($row['tanggal'])
                                )) ?>
                            </td>

                            <td>
                                <?= e($row['nama_pelanggan'] ?? 'Pelanggan Umum') ?>
                            </td>

                            <td>
                                <strong><?= rupiah($row['total_belanja']) ?></strong>
                            </td>

                            <td><?= rupiah($row['uang_diterima']) ?></td>

                            <td><?= rupiah($row['kembalian']) ?></td>

                            <td>
                                <a
                                    href="detail.php?id=<?= (int)$row['id_transaksi'] ?>"
                                    class="kasir-btn kasir-btn-small"
                                >
                                    Detail
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="8" class="riwayat-empty">
                            <div>▤</div>
                            <strong>Belum ada transaksi</strong>
                            <p>Transaksi yang kamu lakukan akan muncul di sini.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($totalHalaman > 1): ?>
        <div class="riwayat-pagination">

            <?php
            $queryFilter = [
                'cari' => $cari,
                'mulai' => $tanggalMulai,
                'akhir' => $tanggalAkhir
            ];
            ?>

            <?php if ($halaman > 1): ?>
                <a href="?<?= e(http_build_query(
                    array_merge($queryFilter, ['halaman' => $halaman - 1])
                )) ?>">
                    &laquo; Sebelumnya
                </a>
            <?php endif; ?>

            <span>
                Halaman <?= $halaman ?> dari <?= $totalHalaman ?>
            </span>

            <?php if ($halaman < $totalHalaman): ?>
                <a href="?<?= e(http_build_query(
                    array_merge($queryFilter, ['halaman' => $halaman + 1])
                )) ?>">
                    Berikutnya &raquo;
                </a>
            <?php endif; ?>

        </div>
    <?php endif; ?>
</div>

<style>
.riwayat-filter {
    display: grid;
    grid-template-columns: minmax(180px, 2fr) minmax(140px, 1fr)
        minmax(140px, 1fr) auto;
    gap: 14px;
    align-items: end;
    padding: 20px;
}

.riwayat-filter label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 8px;
}

.riwayat-filter input {
    width: 100%;
    min-width: 0;
    height: 42px;
    padding: 0 12px;
    border: 1px solid #e1e7ef;
    border-radius: 8px;
    font-size: 12px;
    box-sizing: border-box;
}

.riwayat-filter-actions {
    display: flex;
    gap: 8px;
}

.kasir-alert {
    padding: 13px 16px;
    background: #eaf8ef;
    color: #167342;
    border-radius: 9px;
    margin-bottom: 20px;
    font-size: 13px;
}

.kasir-btn-small {
    padding: 7px 12px;
    font-size: 11px;
}

.riwayat-empty {
    text-align: center;
    padding: 35px !important;
    color: #8994a5;
}

.riwayat-empty div {
    font-size: 30px;
    margin-bottom: 8px;
}

.riwayat-empty strong {
    display: block;
    color: #475569;
    margin-bottom: 5px;
}

.riwayat-empty p {
    font-size: 12px;
}

.riwayat-pagination {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 15px;
    padding: 18px 20px;
    font-size: 12px;
}

.riwayat-pagination a {
    color: #3978f6;
    text-decoration: none;
    font-weight: 600;
}

@media (max-width: 900px) {
    .riwayat-filter {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 550px) {
    .riwayat-filter {
        grid-template-columns: 1fr;
    }

    .riwayat-filter-actions {
        flex-wrap: wrap;
    }

    .riwayat-pagination {
        justify-content: center;
        flex-wrap: wrap;
    }
}
</style>

<?php
$stmt->close();
require_once "../includes/footer.php";
?>
