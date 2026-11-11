
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";
wajib_admin();

$judul = "Tambah Akun";
$error = '';

$pelanggan = mysqli_query(
    $conn,
    "SELECT id_pelanggan, nama_pelanggan
     FROM tbl_pelanggan
     ORDER BY nama_pelanggan ASC"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';

    $id_pelanggan = !empty($_POST['id_pelanggan'])
        ? (int) $_POST['id_pelanggan']
        : null;

    if ($nama === '' || $username === '' || $password === '') {
        $error = "Semua kolom wajib diisi kecuali pelanggan.";

    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter.";

    } elseif (!in_array($role, ['admin', 'kasir', 'user'], true)) {
        $error = "Role tidak valid.";

    } else {
        // Periksa username
        $cek = mysqli_prepare(
            $conn,
            "SELECT id_user FROM tbl_users WHERE username = ?"
        );

        mysqli_stmt_bind_param($cek, "s", $username);
        mysqli_stmt_execute($cek);

        $hasilCek = mysqli_stmt_get_result($cek);

        if ($hasilCek->num_rows > 0) {
            $error = "Username sudah digunakan.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO tbl_users
                (id_pelanggan, nama, username, password, role)
                VALUES (?, ?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "issss",
                $id_pelanggan,
                $nama,
                $username,
                $hash,
                $role
            );

            if (mysqli_stmt_execute($stmt)) {

                // Catat aktivitas setelah akun berhasil dibuat
                catat_log(
                    $conn,
                    "Tambah Akun",
                    "Admin " . ($_SESSION['nama'] ?? '-') .
                    " menambahkan akun: " . $nama .
                    " (Username: " . $username .
                    ", Role: " . $role . ")."
                );

                mysqli_stmt_close($stmt);
                mysqli_stmt_close($cek);

                header(
                    "Location: index.php?pesan=" .
                    urlencode("Akun berhasil ditambahkan.")
                );
                exit;

            } else {
                $error = "Gagal menambahkan akun.";
            }

            mysqli_stmt_close($stmt);
        }

        mysqli_stmt_close($cek);
    }
}

require_once "../header.php";
?>

<div class="page-header">
    <div>
        <h1>Tambah Akun</h1>
        <p>Buat akun baru untuk pengguna TokoKu.</p>
    </div>

    <a href="index.php" class="btn btn-secondary">Kembali</a>
</div>

<div class="content-card form-card">

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <div class="form-group">
            <label>Nama Lengkap</label>
            <input
                type="text"
                name="nama"
                value="<?= e($_POST['nama'] ?? '') ?>"
                required
            >
        </div>

        <div class="form-group">
            <label>Username</label>
            <input
                type="text"
                name="username"
                value="<?= e($_POST['username'] ?? '') ?>"
                required
            >
        </div>

        <div class="form-group">
            <label>Password</label>
            <input
                type="password"
                name="password"
                minlength="6"
                required
            >
        </div>

        <div class="form-group">
            <label>Role Pengguna</label>
            <select name="role" required>
                <option value="user"
                    <?= ($_POST['role'] ?? 'user') === 'user' ? 'selected' : '' ?>>
                    User / Pembeli
                </option>

                <option value="kasir"
                    <?= ($_POST['role'] ?? '') === 'kasir' ? 'selected' : '' ?>>
                    Kasir
                </option>

                <option value="admin"
                    <?= ($_POST['role'] ?? '') === 'admin' ? 'selected' : '' ?>>
                    Admin
                </option>
            </select>
        </div>

        <div class="form-group">
            <label>Hubungkan dengan Pelanggan (Opsional)</label>

            <select name="id_pelanggan">
                <option value="">Tidak dihubungkan</option>

                <?php while ($p = mysqli_fetch_assoc($pelanggan)): ?>
                    <option
                        value="<?= (int) $p['id_pelanggan'] ?>"
                        <?= (($_POST['id_pelanggan'] ?? '') == $p['id_pelanggan']) ? 'selected' : '' ?>
                    >
                        <?= e($p['nama_pelanggan']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">
            Simpan Akun
        </button>

        <a href="index.php" class="btn btn-secondary">
            Batal
        </a>

    </form>
</div>

<?php require_once "../../includes/footer.php"; ?>
