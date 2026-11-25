
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";
wajib_admin();

$judul = "Akun Pengguna";
$cari = trim($_GET['cari'] ?? '');

// Pengaturan pagination
$batas = 10;
$halaman = max(1, (int)($_GET['halaman'] ?? 1));

// Menghitung total akun
$sqlTotal = "SELECT COUNT(*) AS total
             FROM tbl_users u
             LEFT JOIN tbl_pelanggan p
             ON p.id_pelanggan = u.id_pelanggan";

if ($cari !== '') {
    $sqlTotal .= " WHERE u.nama LIKE ?
                   OR u.username LIKE ?
                   OR u.role LIKE ?";
}

$stmtTotal = mysqli_prepare($conn, $sqlTotal);

if ($cari !== '') {
    $keyword = "%$cari%";
    mysqli_stmt_bind_param(
        $stmtTotal,
        "sss",
        $keyword,
        $keyword,
        $keyword
    );
}

mysqli_stmt_execute($stmtTotal);
$resultTotal = mysqli_stmt_get_result($stmtTotal);
$rowTotal = mysqli_fetch_assoc($resultTotal);

$total = (int)$rowTotal['total'];
$totalHalaman = max(1, (int)ceil($total / $batas));

// Memastikan halaman valid
$halaman = min($halaman, $totalHalaman);
$mulai = ($halaman - 1) * $batas;

// Mengambil data akun sesuai halaman
$sql = "SELECT u.id_user, u.nama, u.username, u.role,
               u.created_at, p.nama_pelanggan
        FROM tbl_users u
        LEFT JOIN tbl_pelanggan p
        ON p.id_pelanggan = u.id_pelanggan";

if ($cari !== '') {
    $sql .= " WHERE u.nama LIKE ?
              OR u.username LIKE ?
              OR u.role LIKE ?";
}

$sql .= " ORDER BY u.id_user DESC LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);

if ($cari !== '') {
    mysqli_stmt_bind_param(
        $stmt,
        "sssii",
        $keyword,
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

// Membuat URL pagination
function linkHalaman($nomor, $cari) {
    return "index.php?" . http_build_query([
        'cari' => $cari,
        'halaman' => $nomor
    ]);
}
?>

<div class="page-header">
    <div>
        <h1>Akun Pengguna</h1>
        <p>Kelola akun Admin, Kasir, dan User TokoKu.</p>
    </div>

    <a href="tambah.php" class="btn btn-primary">
        + Tambah Akun
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
            <h3>Daftar Akun</h3>
            <p>Total <?= $total ?> akun terdaftar</p>
        </div>

        <form method="GET" class="search-form">
            <input
                type="text"
                name="cari"
                placeholder="Cari nama, username, atau role..."
                value="<?= e($cari) ?>"
            >

            <button class="btn btn-primary" type="submit">
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
                    <th>Nama</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Pelanggan</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>
            <?php if (mysqli_num_rows($data) > 0): ?>
                <?php $no = $mulai + 1; ?>

                <?php while ($row = mysqli_fetch_assoc($data)): ?>
                    <tr>
                        <td><?= $no++ ?></td>

                        <td><?= e($row['nama']) ?></td>

                        <td><?= e($row['username']) ?></td>

                        <td>
                            <span class="badge">
                                <?= e(ucfirst($row['role'])) ?>
                            </span>
                        </td>

                        <td>
                            <?= e($row['nama_pelanggan'] ?? '-') ?>
                        </td>

                        <td>
                            <div class="action-buttons">
                                <a
                                    href="edit.php?id=<?= (int)$row['id_user'] ?>"
                                    class="btn btn-warning btn-sm"
                                >
                                    Edit
                                </a>

                                <?php if ((int)$row['id_user'] !== (int)$_SESSION['id_user']): ?>
                                    <form
                                        method="POST"
                                        action="hapus.php"
                                        onsubmit="return confirm('Yakin ingin menghapus akun ini?')"
                                    >
                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$row['id_user'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-danger btn-sm"
                                        >
                                            Hapus
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>

            <?php else: ?>
                <tr>
                    <td colspan="6" class="text-center">
                        Akun tidak ditemukan.
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
                dari <?= $total ?> akun
            </div>

            <div class="pagination">

                <!-- Sebelumnya -->
                <?php if ($halaman > 1): ?>
                    <a
                        href="<?= e(linkHalaman($halaman - 1, $cari)) ?>"
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

                <!-- Halaman pertama -->
                <?php if ($awal > 1): ?>
                    <a
                        href="<?= e(linkHalaman(1, $cari)) ?>"
                        class="page-link"
                    >
                        1
                    </a>

                    <?php if ($awal > 2): ?>
                        <span class="page-dots">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Nomor halaman -->
                <?php for ($i = $awal; $i <= $akhir; $i++): ?>
                    <a
                        href="<?= e(linkHalaman($i, $cari)) ?>"
                        class="page-link <?= $i == $halaman ? 'active' : '' ?>"
                    >
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <!-- Halaman terakhir -->
                <?php if ($akhir < $totalHalaman): ?>
                    <?php if ($akhir < $totalHalaman - 1): ?>
                        <span class="page-dots">...</span>
                    <?php endif; ?>

                    <a
                        href="<?= e(linkHalaman($totalHalaman, $cari)) ?>"
                        class="page-link"
                    >
                        <?= $totalHalaman ?>
                    </a>
                <?php endif; ?>

                <!-- Selanjutnya -->
                <?php if ($halaman < $totalHalaman): ?>
                    <a
                        href="<?= e(linkHalaman($halaman + 1, $cari)) ?>"
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

<?php require_once "../../includes/footer.php"; ?>
