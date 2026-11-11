
<?php
require_once "config/auth.php";

if (isset($_SESSION['id_user'])) {
    header("Location: index.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $kota = trim($_POST['kota'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $konfirmasi = $_POST['konfirmasi'] ?? '';

    if ($nama === '' || $kota === '' || $username === '') {
        $error = "Semua data wajib diisi.";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter.";
    } elseif ($password !== $konfirmasi) {
        $error = "Konfirmasi password tidak sama.";
    } else {
        $check = $conn->prepare(
            "SELECT id_user FROM tbl_users WHERE username = ?"
        );
        $check->bind_param("s", $username);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $error = "Username sudah digunakan.";
        } else {
            $conn->begin_transaction();

            try {
                $pelanggan = $conn->prepare(
                    "INSERT INTO tbl_pelanggan
                    (nama_pelanggan, kota) VALUES (?, ?)"
                );
                $pelanggan->bind_param("ss", $nama, $kota);
                $pelanggan->execute();

                $id_pelanggan = $conn->insert_id;
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $role = "user";

                $akun = $conn->prepare(
                    "INSERT INTO tbl_users
                    (id_pelanggan, nama, username, password, role)
                    VALUES (?, ?, ?, ?, ?)"
                );

                $akun->bind_param(
                    "issss",
                    $id_pelanggan,
                    $nama,
                    $username,
                    $hash,
                    $role
                );
                $akun->execute();

                $conn->commit();

                header("Location: login.php?daftar=berhasil");
                exit;
            } catch (Throwable $e) {
                $conn->rollback();
                $error = "Registrasi gagal. Silakan coba lagi.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar Pembeli | TokoKu</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-logo">Toko<span>Ku.</span></div>
        <h2>Buat Akun Pembeli</h2>
        <p class="muted">Daftar untuk mulai berbelanja.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label>Nama Lengkap</label>
            <input type="text" name="nama" required>

            <label>Kota</label>
            <input type="text" name="kota" required>

            <label>Username</label>
            <input type="text" name="username" required>

            <label>Password</label>
            <input type="password" name="password" minlength="6" required>

            <label>Konfirmasi Password</label>
            <input type="password" name="konfirmasi" minlength="6" required>

            <button class="btn btn-primary btn-full" type="submit">
                Daftar Sekarang
            </button>
        </form>

        <p class="auth-bottom">
            Sudah punya akun? <a href="login.php">Login</a>
        </p>
    </div>
</div>

</body>
</html>
