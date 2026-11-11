
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";
wajib_admin();

$judul = "Akun Pengguna";
$cari = trim($_GET['cari'] ?? '');

$sql = "SELECT u.id_user, u.nama, u.username, u.role,
               u.created_at, p.nama_pelanggan
        FROM tbl_users u
        LEFT JOIN tbl_pelanggan p ON p.id_pelanggan = u.id_pelanggan";

if ($cari !== '') {
    $sql .= " WHERE u.nama LIKE ? OR u.username LIKE ? OR u.role LIKE ?";
}
$sql .= " ORDER BY u.id_user DESC";

$stmt = mysqli_prepare($conn, $sql);

if ($cari !== '') {
    $keyword = "%$cari%";
    mysqli_stmt_bind_param($stmt, "sss", $keyword, $keyword, $keyword);
}

mysqli_stmt_execute($stmt);
$data = mysqli_stmt_get_result($stmt);
$total = mysqli_num_rows($data);

require_once "../header.php";
?>

<div class="page-header">
    <div>
        <h1>Akun Pengguna</h1>
        <p>Kelola akun Admin, Kasir, dan User TokoKu.</p>
    </div>
    <a href="tambah.php" class="btn btn-primary">+ Tambah Akun</a>
</div>

<?php if (isset($_GET['pesan'])): ?>
    <div class="alert alert-success"><?= e($_GET['pesan']) ?></div>
<?php endif; ?>

<div class="content-card">
    <div class="card-header">
        <div>
            <h3>Daftar Akun</h3>
            <p>Total <?= $total ?> akun terdaftar</p>
        </div>

        <form method="GET" class="search-form">
            <input type="text" name="cari"
                   placeholder="Cari nama, username, atau role..."
                   value="<?= e($cari) ?>">
            <button class="btn btn-primary" type="submit">Cari</button>
            <a href="index.php" class="btn btn-secondary">Reset</a>
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
            <?php if ($total > 0): ?>
                <?php $no = 1; ?>
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
                        <td><?= e($row['nama_pelanggan'] ?? '-') ?></td>
                        <td>
                            <div class="action-buttons">
                                <a href="edit.php?id=<?= (int)$row['id_user'] ?>"
                                   class="btn btn-warning btn-sm">Edit</a>

                                <?php if ((int)$row['id_user'] !== (int)$_SESSION['id_user']): ?>
                                    <form method="POST" action="hapus.php"
                                          onsubmit="return confirm('Yakin ingin menghapus akun ini?')">
                                        <input type="hidden" name="id"
                                               value="<?= (int)$row['id_user'] ?>">
                                        <button type="submit"
                                                class="btn btn-danger btn-sm">
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
</div>

<?php require_once "../../includes/footer.php"; ?>
