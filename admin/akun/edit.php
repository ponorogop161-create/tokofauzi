
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";
wajib_admin();

$judul = "Edit Akun";
$id = (int)($_GET['id'] ?? 0);

$stmt = mysqli_prepare(
    $conn,
    "SELECT * FROM tbl_users WHERE id_user = ?"
);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$akun = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$akun) {
    header("Location: index.php?pesan=" . urlencode("Akun tidak ditemukan."));
    exit;
}

$pelanggan = mysqli_query(
    $conn,
    "SELECT id_pelanggan, nama_pelanggan
     FROM tbl_pelanggan ORDER BY nama_pelanggan ASC"
);

$error = '';
$isSelf = $id === (int)$_SESSION['id_user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $id_pelanggan = !empty($_POST['id_pelanggan'])
        ? (int)$_POST['id_pelanggan'] : null;

    if ($nama === '' || $username === '') {
        $error = "Nama dan username wajib diisi.";
    } elseif (!in_array($role, ['admin', 'kasir', 'user'], true)) {
        $error = "Role tidak valid.";
    } elseif ($password !== '' && strlen($password) < 6) {
        $error = "Password minimal 6 karakter.";
    } elseif ($isSelf && $role !== 'admin') {
        $error = "Anda tidak dapat mengubah role akun sendiri.";
    } else {
        $cek = mysqli_prepare(
            $conn,
            "SELECT id_user FROM tbl_users
             WHERE username = ? AND id_user != ?"
        );
        mysqli_stmt_bind_param($cek, "si", $username, $id);
        mysqli_stmt_execute($cek);

        if (mysqli_stmt_get_result($cek)->num_rows > 0) {
            $error = "Username sudah digunakan.";
        } else {
            // Lindungi agar Admin terakhir tidak kehilangan role Admin.
            if ($akun['role'] === 'admin' && $role !== 'admin') {
                $jumlah = mysqli_query(
                    $conn,
                    "SELECT COUNT(*) AS total
                     FROM tbl_users WHERE role = 'admin'"
                );
                $totalAdmin = (int)mysqli_fetch_assoc($jumlah)['total'];

                if ($totalAdmin <= 1) {
                    $error = "Role Admin terakhir tidak dapat diubah.";
                }
            }

            if ($error === '') {
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);

                    $update = mysqli_prepare(
                        $conn,
                        "UPDATE tbl_users
                         SET nama = ?, username = ?, password = ?,
                             role = ?, id_pelanggan = ?
                         WHERE id_user = ?"
                    );
                    mysqli_stmt_bind_param(
                        $update, "ssssii",
                        $nama, $username, $hash, $role,
                        $id_pelanggan, $id
                    );
                } else {
                    $update = mysqli_prepare(
                        $conn,
                        "UPDATE tbl_users
                         SET nama = ?, username = ?, role = ?,
                             id_pelanggan = ?
                         WHERE id_user = ?"
                    );
                    mysqli_stmt_bind_param(
                        $update, "sssii",
                        $nama, $username, $role,
                        $id_pelanggan, $id
                    );


catat_log(
    $conn,
    "Edit Akun",
    "Admin mengubah data akun: " . $nama .
    " (Username: " . $username . ", Role: " . $role . ")."
);

                }

                if (mysqli_stmt_execute($update)) {
                    if ($isSelf) {
                        $_SESSION['nama'] = $nama;
                        $_SESSION['username'] = $username;
                    }

                    header("Location: index.php?pesan=" .
                        urlencode("Akun berhasil diperbarui."));
                    exit;
                }

                

                $error = "Gagal memperbarui akun. Periksa data pelanggan yang dipilih.";
            }
        }
    }
}

require_once "../header.php";
?>

<div class="page-header">
    <div>
        <h1>Edit Akun</h1>
        <p>Perbarui informasi akun pengguna.</p>
    </div>
    <a href="index.php" class="btn btn-secondary">Kembali</a>
</div>

<div class="content-card form-card">
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Nama Lengkap</label>
            <input type="text" name="nama"
                   value="<?= e($_POST['nama'] ?? $akun['nama']) ?>"
                   required>
        </div>

        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username"
                   value="<?= e($_POST['username'] ?? $akun['username']) ?>"
                   required>
        </div>

        <div class="form-group">
            <label>Password Baru</label>
            <input type="password" name="password" minlength="6"
                   placeholder="Kosongkan jika tidak diganti">
        </div>

        <div class="form-group">
            <label>Role Pengguna</label>
            <select name="role" required>
                <?php foreach (['admin', 'kasir', 'user'] as $role): ?>
                    <option value="<?= $role ?>"
                        <?= (($_POST['role'] ?? $akun['role']) === $role) ? 'selected' : '' ?>>
                        <?= e(ucfirst($role)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Hubungkan dengan Pelanggan (Opsional)</label>
            <select name="id_pelanggan">
                <option value="">Tidak dihubungkan</option>
                <?php while ($p = mysqli_fetch_assoc($pelanggan)): ?>
                    <?php $pilihan = $_POST['id_pelanggan'] ?? $akun['id_pelanggan']; ?>
                    <option value="<?= (int)$p['id_pelanggan'] ?>"
                        <?= ((string)$pilihan === (string)$p['id_pelanggan']) ? 'selected' : '' ?>>
                        <?= e($p['nama_pelanggan']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">
            Simpan Perubahan
        </button>
        <a href="index.php" class="btn btn-secondary">Batal</a>
    </form>
</div>

<?php require_once "../../includes/footer.php"; ?>
