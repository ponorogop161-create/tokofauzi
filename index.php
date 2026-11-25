<?php
require_once "config/auth.php";

if (!isset($_SESSION['id_user'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$role = $_SESSION['role'] ?? '';

switch ($role) {

    case 'admin':
        header("Location: " . BASE_URL . "admin/index.php");
        exit;

    case 'kasir':
        header("Location: " . BASE_URL . "kasir/index.php");
        exit;

    case 'user':
        header("Location: " . BASE_URL . "user/index.php");
        exit;

    default:
        session_unset();
        session_destroy();

        header("Location: " . BASE_URL . "login.php");
        exit;
}