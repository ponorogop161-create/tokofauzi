
<?php
require_once __DIR__ . "/koneksi.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define("BASE_URL", "/tokoku/");

function wajib_login()
{
    if (!isset($_SESSION['id_user'])) {
        header("Location: " . BASE_URL . "login.php");
        exit;
    }
}

function wajib_admin()
{
    wajib_login();

    if ($_SESSION['role'] !== 'admin') {
        header("Location: " . BASE_URL . "index.php");
        exit;
    }
}

function wajib_kasir()
{
    wajib_login();

    if (!in_array($_SESSION['role'], ['admin', 'kasir'], true)) {
        header("Location: " . BASE_URL . "index.php");
        exit;
    }
}

function wajib_user()
{
    wajib_login();

    if ($_SESSION['role'] !== 'user') {
        header("Location: " . BASE_URL . "index.php");
        exit;
    }
}
