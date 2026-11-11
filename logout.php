
<?php
require_once __DIR__ . "/config/koneksi.php";
require_once __DIR__ . "/config/auth.php";

if (isset($_SESSION['id_user'])) {
    catat_log(
        $conn,
        "Logout",
        "Pengguna " . ($_SESSION['nama'] ?? '-') .
        " (" . ($_SESSION['role'] ?? '-') . ") telah logout."
    );
}

$_SESSION = [];

session_destroy();

header("Location: " . BASE_URL . "login.php");
exit;
