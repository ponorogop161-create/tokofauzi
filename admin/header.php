
<?php
require_once __DIR__ . "/../config/auth.php";
wajib_admin();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= isset($judul) ? e($judul) : 'Admin' ?> | TokoKu</title>

    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
<div class="app">

    <?php require __DIR__ . "/sidebar.php"; ?>

    <main class="main-content">

        <header class="topbar">
            <div style="display:flex;align-items:center;gap:15px">
                <button class="btn btn-secondary"
                        type="button"
                        data-menu-toggle>☰</button>

                <h2><?= isset($judul) ? e($judul) : 'Dashboard' ?></h2>
            </div>

            <div class="profile">
                <div class="avatar">A</div>
                <div>
                    <strong><?= e($_SESSION['nama']) ?></strong>
                    <div class="muted">Administrator</div>
                </div>
            </div>
        </header>

        <section class="content">
