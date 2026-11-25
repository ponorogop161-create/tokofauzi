<?php
require_once "config/auth.php";
require_once "config/koneksi.php";

if (isset($_SESSION['id_user'])) {
    header("Location: index.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare(
        "SELECT * FROM tbl_users WHERE username = ? LIMIT 1"
    );

    $stmt->bind_param("s", $username);
    $stmt->execute();

    $akun = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($akun && password_verify($password, $akun['password'])) {

        session_regenerate_id(true);

        $_SESSION['id_user'] = $akun['id_user'];
        $_SESSION['id_pelanggan'] = $akun['id_pelanggan'];
        $_SESSION['nama'] = $akun['nama'];
        $_SESSION['username'] = $akun['username'];
        $_SESSION['role'] = $akun['role'];

        // Catat aktivitas login
        catat_log(
            $conn,
            "Login",
            "Pengguna " . $akun['nama'] .
            " (" . $akun['role'] . ") berhasil login."
        );

        header("Location: index.php");
        exit;

    } else {
        $error = "Username atau password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | TokoKu</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="auth-body">

<div class="auth-container">
    <div class="auth-card">

        <div class="auth-logo">Toko<span>Ku.</span></div>

        <h2>Selamat Datang</h2>
        <p class="muted">Silakan masuk ke akun TokoKu Anda.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <!-- LOGIN USERNAME DAN PASSWORD -->
        <form method="POST">

            <label>Username</label>
            <input
                type="text"
                name="username"
                placeholder="Masukkan username"
                autocomplete="username"
                required
            >

            <label>Password</label>
            <input
                type="password"
                name="password"
                placeholder="Masukkan password"
                autocomplete="current-password"
                required
            >

            <button class="btn btn-primary btn-full" type="submit">
                Masuk
            </button>

        </form>

        <!-- LOGIN RFID -->
        <a href="<?= BASE_URL ?>login_rfid.php"
           class="btn btn-secondary btn-full"
           style="display:block;text-align:center;margin-top:12px;box-sizing:border-box;">
            🪪 Login dengan RFID
        </a>

        <!-- REGISTER -->
        <p class="auth-bottom">
            Belum punya akun?
            <a href="register.php">Daftar sebagai pembeli</a>
        </p>

    </div>
</div>

</body>
</html>