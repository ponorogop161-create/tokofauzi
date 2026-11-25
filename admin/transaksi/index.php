
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";
wajib_admin();

$judul = "Data Transaksi";

$cari = trim($_GET['cari'] ?? '');
$filterKasir = trim($_GET['kasir'] ?? '');

// Pengaturan pagination
$batas = 10;
$halaman = max(1, (int)($_GET['halaman'] ?? 1));

// =====================================
// DATA DAFTAR KASIR
// =====================================

$sqlKasir = "
    SELECT id_user, nama
    FROM tbl_users
    WHERE role = 'kasir'
    ORDER BY nama ASC
";

$resultKasir = mysqli_query($conn, $sqlKasir);

// =====================================
// QUERY DASAR
// =====================================

$sqlDasar = "
    FROM tbl_transaksi t
    LEFT JOIN tbl_pelanggan p
        ON p.id_pelanggan = t.id_pelanggan
    LEFT JOIN tbl_users u
        ON u.id_user = t.id_user
    LEFT JOIN tbl_detail_transaksi d
        ON d.id_transaksi = t.id_transaksi
";

// =====================================
// KONDISI FILTER
// =====================================

$kondisi = [];
$parameter = [];
$tipe = "";

// Filter pencarian
if ($cari !== '') {
    $kondisi[] = "(
        CAST(t.id_transaksi AS CHAR) LIKE ?
        OR p.nama_pelanggan LIKE ?
        OR DATE_FORMAT(t.tanggal, '%Y-%m-%d') LIKE ?
    )";

    $keyword = "%$cari%";

    $parameter[] = $keyword;
    $parameter[] = $keyword;
    $parameter[] = $keyword;

    $tipe .= "sss";
}

// Filter kasir
if ($filterKasir === 'tanpa') {
    $kondisi[] = "t.id_user IS NULL";

} elseif ($filterKasir !== '' && ctype_digit($filterKasir)) {
    $kondisi[] = "t.id_user = ?";

    $parameter[] = (int)$filterKasir;
    $tipe .= "i";
}

// Gabungkan kondisi WHERE
$where = "";

if (!empty($kondisi)) {
    $where = " WHERE " . implode(" AND ", $kondisi);
}

// Fungsi binding parameter dinamis
$bindParameter = function ($stmt, $tipe, &$parameter) {
    if ($tipe !== '') {
        $referensi = [];

        foreach ($parameter as $key => $value) {
            $referensi[$key] = &$parameter[$key];
        }

        mysqli_stmt_bind_param(
            $stmt,
            $tipe,
            ...$referensi
        );
    }
};

// =====================================
// RINGKASAN TRANSAKSI
// =====================================

$sqlRingkasan = "
    SELECT
        COUNT(DISTINCT t.id_transaksi) AS total_transaksi,
        COALESCE(SUM(d.harga * d.jumlah), 0) AS total_penjualan
    $sqlDasar
    $where
";

$stmtRingkasan = mysqli_prepare($conn, $sqlRingkasan);

$parameterRingkasan = $parameter;
$bindParameter($stmtRingkasan, $tipe, $parameterRingkasan);

mysqli_stmt_execute($stmtRingkasan);

$resultRingkasan = mysqli_stmt_get_result($stmtRingkasan);
$ringkasan = mysqli_fetch_assoc($resultRingkasan);

$totalTransaksi = (int)$ringkasan['total_transaksi'];
$totalPenjualan = (float)$ringkasan['total_penjualan'];

mysqli_stmt_close($stmtRingkasan);

// =====================================
// PAGINATION
// =====================================

$totalHalaman = max(1, (int)ceil($totalTransaksi / $batas));

$halaman = min($halaman, $totalHalaman);
$mulai = ($halaman - 1) * $batas;

// =====================================
// QUERY DATA TRANSAKSI
// =====================================

$sql = "
    SELECT
        t.id_transaksi,
        t.tanggal,
        p.nama_pelanggan,
        u.nama AS nama_pengguna,
        u.role,
        COALESCE(SUM(d.harga * d.jumlah), 0) AS total_hitung
    $sqlDasar
    $where
    GROUP BY
        t.id_transaksi,
        t.tanggal,
        p.nama_pelanggan,
        u.nama,
        u.role
    ORDER BY t.id_transaksi DESC
    LIMIT ? OFFSET ?
";

$stmt = mysqli_prepare($conn, $sql);

$parameterData = $parameter;
$tipeData = $tipe . "ii";

$parameterData[] = $batas;
$parameterData[] = $mulai;

$bindParameter($stmt, $tipeData, $parameterData);

mysqli_stmt_execute($stmt);
$data = mysqli_stmt_get_result($stmt);

require_once "../header.php";

// =====================================
// URL PAGINATION
// =====================================

function linkHalaman($nomor, $cari, $kasir) {
    return "index.php?" . http_build_query([
        'cari' => $cari,
        'kasir' => $kasir,
        'halaman' => $nomor
    ]);
}
?>

<div class="page-header">
    <div>
        <h1>Data Transaksi</h1>
        <p>Pantau seluruh transaksi penjualan TokoKu.</p>
    </div>
</div>

<!-- RINGKASAN -->
<div class="stats-grid">
    <div class="stat-card">
        <h3>🧾 Total Transaksi</h3>
        <strong>
            <?= number_format($totalTransaksi, 0, ',', '.') ?>
        </strong>
    </div>

    <div class="stat-card">
        <h3>💰 Total Penjualan</h3>
        <strong><?= rupiah($totalPenjualan) ?></strong>
    </div>
</div>

<div class="content-card">

    <div class="card-header">
        <div>
            <h3>📋 Riwayat Transaksi</h3>
            <p>Seluruh transaksi yang tersimpan dalam database.</p>
        </div>
    </div>

    <!-- FORM PENCARIAN DAN FILTER -->
    <form method="GET" class="search-form"
          style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;">

        <input
            type="text"
            name="cari"
            placeholder="Cari ID, pelanggan, tanggal..."
            value="<?= e($cari) ?>"
            style="min-width:200px;"
        >

        <select name="kasir" style="min-width:180px;">
            <option value="">Semua Kasir</option>

            <?php if ($resultKasir): ?>
                <?php while ($kasir = mysqli_fetch_assoc($resultKasir)): ?>
                    <option
                        value="<?= (int)$kasir['id_user'] ?>"
                        <?= $filterKasir === (string)$kasir['id_user'] ? 'selected' : '' ?>
                    >
                        <?= e($kasir['nama']) ?>
                    </option>
                <?php endwhile; ?>
            <?php endif; ?>

            <option value="tanpa"
                <?= $filterKasir === 'tanpa' ? 'selected' : '' ?>>
                Tanpa Kasir
            </option>
        </select>

        <button type="submit" class="btn btn-primary">
            🔍 Terapkan Filter
        </button>

        <a href="index.php" class="btn btn-secondary">
            Reset
        </a>
    </form>

    <!-- TABEL TRANSAKSI -->
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID Transaksi</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>Dilayani Oleh</th>
                    <th>Role</th>
                    <th>Total Belanja</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>
                <?php if (mysqli_num_rows($data) > 0): ?>

                    <?php while ($row = mysqli_fetch_assoc($data)): ?>
                        <tr>
                            <td>
                                #<?= (int)$row['id_transaksi'] ?>
                            </td>

                            <td>
                                <?= e($row['tanggal']) ?>
                            </td>

                            <td>
                                <?= e($row['nama_pelanggan'] ?? 'Umum') ?>
                            </td>

                            <td>
                                <?= e($row['nama_pengguna'] ?? 'Tidak tercatat') ?>
                            </td>

                            <td>
                                <span class="badge">
                                    <?= e(ucfirst($row['role'] ?? '-')) ?>
                                </span>
                            </td>

                            <td>
                                <strong>
                                    <?= rupiah($row['total_hitung']) ?>
                                </strong>
                            </td>

                            <td>
                                <a
                                    href="detail.php?id=<?= (int)$row['id_transaksi'] ?>"
                                    class="btn btn-primary btn-sm"
                                >
                                    👁 Detail
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7" class="text-center">
                            Tidak ada transaksi yang ditemukan.
                        </td>
                    </tr>

                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($totalTransaksi > 0): ?>

        <div class="pagination-container">

            <div class="pagination-info">
                Menampilkan
                <?= $mulai + 1 ?>
                -
                <?= min($mulai + $batas, $totalTransaksi) ?>
                dari
                <?= number_format($totalTransaksi, 0, ',', '.') ?>
                transaksi
            </div>

            <div class="pagination">

                <?php if ($halaman > 1): ?>
                    <a
                        href="<?= e(linkHalaman($halaman - 1, $cari, $filterKasir)) ?>"
                        class="page-link"
                    >
                        &laquo; Sebelumnya
                    </a>
                <?php else: ?>
                    <span class="page-link disabled">
                        &laquo; Sebelumnya
                    </span>
                <?php endif; ?>

                <?php
                $awal = max(1, $halaman - 2);
                $akhir = min($totalHalaman, $halaman + 2);
                ?>

                <?php if ($awal > 1): ?>
                    <a
                        href="<?= e(linkHalaman(1, $cari, $filterKasir)) ?>"
                        class="page-link"
                    >
                        1
                    </a>

                    <?php if ($awal > 2): ?>
                        <span class="page-dots">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $awal; $i <= $akhir; $i++): ?>
                    <a
                        href="<?= e(linkHalaman($i, $cari, $filterKasir)) ?>"
                        class="page-link <?= $i == $halaman ? 'active' : '' ?>"
                    >
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($akhir < $totalHalaman): ?>

                    <?php if ($akhir < $totalHalaman - 1): ?>
                        <span class="page-dots">...</span>
                    <?php endif; ?>

                    <a
                        href="<?= e(linkHalaman($totalHalaman, $cari, $filterKasir)) ?>"
                        class="page-link"
                    >
                        <?= $totalHalaman ?>
                    </a>

                <?php endif; ?>

                <?php if ($halaman < $totalHalaman): ?>
                    <a
                        href="<?= e(linkHalaman($halaman + 1, $cari, $filterKasir)) ?>"
                        class="page-link"
                    >
                        Selanjutnya &raquo;
                    </a>
                <?php else: ?>
                    <span class="page-link disabled">
                        Selanjutnya &raquo;
                    </span>
                <?php endif; ?>

            </div>
        </div>

    <?php endif; ?>

</div>

<style>
.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 20px;
    padding: 15px 0;
}

.pagination-info {
    font-size: 13px;
    color: #64748b;
}

.pagination {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 5px;
}

.page-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    text-decoration: none;
    color: #334155;
    background: #fff;
    font-size: 13px;
    font-weight: 600;
}

.page-link:hover {
    background: #eff6ff;
    border-color: #2563eb;
    color: #2563eb;
}

.page-link.active {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}

.page-link.disabled {
    color: #94a3b8;
    background: #f8fafc;
    cursor: not-allowed;
}

.page-dots {
    padding: 0 4px;
    color: #64748b;
}

@media (max-width: 600px) {
    .pagination-container {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<?php
mysqli_stmt_close($stmt);
require_once "../../includes/footer.php";
?>
