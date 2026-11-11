
<?php
require_once __DIR__ . "/../config/auth.php";
wajib_kasir();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($judul ?? 'Kasir') ?> | TokoKu</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/kasir.css">
</head>
<body>

<div class="kasir-app">
    <?php require __DIR__ . "/sidebar.php"; ?>

    <main class="kasir-main">
        <header class="kasir-topbar">
            <div>
                <h2><?= e($judul ?? 'Dashboard Kasir') ?></h2>
                <p>Kelola penjualan TokoKu</p>
            </div>

            <div class="kasir-profile">
                <div class="kasir-avatar">
                    <?= strtoupper(substr($_SESSION['nama'] ?? 'K', 0, 1)) ?>
                </div>
                <div>
                    <strong><?= e($_SESSION['nama'] ?? 'Kasir') ?></strong>
                    <small>Petugas Kasir</small>
                </div>
            </div>
        </header>

        <section class="kasir-content">
