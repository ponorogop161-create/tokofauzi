
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";
wajib_admin();

$cari = trim($_GET['cari'] ?? '');

// Pengaturan pagination
$batas = 10;
$halaman = max(1, (int)($_GET['halaman'] ?? 1));

// Menghitung total data
$sqlTotal = "SELECT COUNT(*) AS total FROM tbl_pelanggan";

if ($cari !== '') {
    $sqlTotal .= " WHERE nama_pelanggan LIKE ? OR kota LIKE ?";
}

$stmtTotal = mysqli_prepare($conn, $sqlTotal);

if ($cari !== '') {
    $keyword = "%$cari%";
    mysqli_stmt_bind_param($stmtTotal, "ss", $keyword, $keyword);
}

mysqli_stmt_execute($stmtTotal);
$resultTotal = mysqli_stmt_get_result($stmtTotal);
$rowTotal = mysqli_fetch_assoc($resultTotal);

$total = (int)$rowTotal['total'];
$totalHalaman = max(1, (int)ceil($total / $batas));

// Pastikan halaman tidak melebihi jumlah halaman
$halaman = min($halaman, $totalHalaman);
$mulai = ($halaman - 1) * $batas;

// Mengambil data sesuai halaman
$sql = "SELECT * FROM tbl_pelanggan";

if ($cari !== '') {
    $sql .= " WHERE nama_pelanggan LIKE ? OR kota LIKE ?";
}

$sql .= " ORDER BY id_pelanggan DESC LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);

if ($cari !== '') {
    mysqli_stmt_bind_param(
        $stmt,
        "ssii",
        $keyword,
        $keyword,
        $batas,
        $mulai
    );
} else {
    mysqli_stmt_bind_param($stmt, "ii", $batas, $mulai);
}

mysqli_stmt_execute($stmt);
$data = mysqli_stmt_get_result($stmt);

require_once "../header.php";

// Membuat URL pagination dengan mempertahankan pencarian
function linkHalaman($nomor, $cari) {
    return "index.php?" . http_build_query([
        'cari' => $cari,
        'halaman' => $nomor
    ]);
}
?>

<div class="content">
    <div class="page-header">
        <div>
            <h1>Data Pelanggan</h1>
            <p>Kelola seluruh data pelanggan TokoKu.</p>
        </div>

        <a href="tambah.php" class="btn btn-primary">
            + Tambah Pelanggan
        </a>
    </div>

    <?php if (isset($_GET['pesan'])): ?>
        <div class="alert alert-success">
            <?= e($_GET['pesan']) ?>
        </div>
    <?php endif; ?>

    <div class="content-card">
        <div class="card-header">
            <div>
                <h3>Daftar Pelanggan</h3>
                <p>Total <?= $total ?> pelanggan</p>
            </div>

            <form method="GET" class="search-form">
                <input
                    type="text"
                    name="cari"
                    placeholder="Cari nama atau kota..."
                    value="<?= e($cari) ?>"
                >

                <button type="submit" class="btn btn-primary">
                    Cari
                </button>

                <a href="index.php" class="btn btn-secondary">
                    Reset
                </a>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Pelanggan</th>
                        <th>Kota</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (mysqli_num_rows($data) > 0): ?>
                    <?php $no = $mulai + 1; ?>

                    <?php while ($row = mysqli_fetch_assoc($data)): ?>
                        <tr>
                            <td><?= $no++ ?></td>

                            <td>
                                <?= e($row['nama_pelanggan']) ?>
                            </td>

                            <td>
                                <?= e($row['kota']) ?>
                            </td>

                            <td>
                                <div class="action-buttons">
                                    <a
                                        class="btn btn-warning btn-sm"
                                        href="edit.php?id=<?= (int)$row['id_pelanggan'] ?>"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        action="hapus.php"
                                        onsubmit="return confirm('Yakin ingin menghapus pelanggan ini?')"
                                    >
                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$row['id_pelanggan'] ?>"
                                        >

                                        <button
                                            class="btn btn-danger btn-sm"
                                            type="submit"
                                        >
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center">
                            Data pelanggan tidak ditemukan.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total > 0): ?>
            <div class="pagination-container">

                <div class="pagination-info">
                    Menampilkan
                    <?= $mulai + 1 ?>
                    -
                    <?= min($mulai + $batas, $total) ?>
                    dari
                    <?= $total ?> pelanggan
                </div>

                <div class="pagination">

                    <?php if ($halaman > 1): ?>
                        <a href="<?= e(linkHalaman($halaman - 1, $cari)) ?>"
                           class="page-link">
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
                        <a href="<?= e(linkHalaman(1, $cari)) ?>"
                           class="page-link">1</a>

                        <?php if ($awal > 2): ?>
                            <span class="page-dots">...</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $awal; $i <= $akhir; $i++): ?>
                        <a
                            href="<?= e(linkHalaman($i, $cari)) ?>"
                            class="page-link <?= $i == $halaman ? 'active' : '' ?>"
                        >
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($akhir < $totalHalaman): ?>
                        <?php if ($akhir < $totalHalaman - 1): ?>
                            <span class="page-dots">...</span>
                        <?php endif; ?>

                        <a href="<?= e(linkHalaman($totalHalaman, $cari)) ?>"
                           class="page-link">
                            <?= $totalHalaman ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($halaman < $totalHalaman): ?>
                        <a href="<?= e(linkHalaman($halaman + 1, $cari)) ?>"
                           class="page-link">
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

<?php require_once "../../includes/footer.php"; ?>
