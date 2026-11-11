
<?php
require_once __DIR__ . "/../config/auth.php";
wajib_user();

$judul = $judul ?? "Area Pembeli";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($judul) ?> | TokoKu</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">


<style>
/* SIDEBAR PEMBELI */
.user-sidebar {
    position: fixed !important;
    top: 0;
    left: 0;
    bottom: 0;
    width: 250px !important;
    height: 100vh;
    padding: 24px 16px;
    box-sizing: border-box;
    background: #172554 !important;
    color: #fff;
    display: flex !important;
    flex-direction: column !important;
    z-index: 9999;
    overflow-y: auto;
}

.user-sidebar-brand {
    display: flex !important;
    align-items: center;
    gap: 12px;
    padding: 5px 10px 25px;
    border-bottom: 1px solid rgba(255,255,255,.15);
}

.user-brand-icon {
    width: 43px;
    height: 43px;
    min-width: 43px;
    border-radius: 12px;
    background: #3b82f6;
    display: flex !important;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 23px;
    font-weight: bold;
}

.user-sidebar-brand h2 {
    margin: 0;
    color: white;
    font-size: 21px;
}

.user-sidebar-brand span {
    display: block;
    margin-top: 4px;
    color: #a5b4fc;
    font-size: 12px;
}

.user-menu-label {
    padding: 28px 12px 12px;
    color: #94a3b8;
    font-size: 10px;
    font-weight: bold;
    letter-spacing: 1.3px;
}

.user-sidebar-menu {
    display: flex !important;
    flex-direction: column !important;
    gap: 7px;
}

.user-sidebar-menu a,
.user-sidebar-bottom a {
    display: flex !important;
    align-items: center;
    gap: 13px;
    width: 100%;
    min-height: 47px;
    padding: 0 13px;
    box-sizing: border-box;
    border-radius: 9px;
    color: #cbd5e1 !important;
    text-decoration: none !important;
    font-size: 14px;
    transition: .2s;
}

.user-sidebar-menu a:hover,
.user-sidebar-bottom a:hover {
    background: rgba(255,255,255,.12);
    color: white !important;
}

.user-sidebar-menu a.active {
    background: #2563eb;
    color: white !important;
    font-weight: 600;
}

.user-menu-icon {
    width: 23px;
    min-width: 23px;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    font-size: 19px;
}

.user-sidebar-bottom {
    margin-top: auto;
    padding-top: 20px;
    border-top: 1px solid rgba(255,255,255,.15);
}

.user-sidebar-bottom a {
    color: #fca5a5 !important;
}

/* POSISI KONTEN */
.app {
    display: block !important;
}

.user-sidebar ~ .main-content {
    margin-left: 250px !important;
    width: calc(100% - 250px) !important;
    box-sizing: border-box;
    min-width: 0;
}

@media (max-width: 768px) {
    .user-sidebar {
        width: 210px !important;
    }

    .user-sidebar ~ .main-content {
        margin-left: 210px !important;
        width: calc(100% - 210px) !important;
    }
}

@media (max-width: 520px) {
    .user-sidebar {
        width: 68px !important;
        padding: 18px 8px;
    }

    .user-sidebar-brand {
        justify-content: center;
        padding: 5px 0 22px;
    }

    .user-sidebar-brand h2,
    .user-sidebar-brand span,
    .user-menu-label,
    .user-sidebar-menu a span:not(.user-menu-icon),
    .user-sidebar-bottom a span:not(.user-menu-icon) {
        display: none !important;
    }

    .user-sidebar-menu a,
    .user-sidebar-bottom a {
        justify-content: center;
        padding: 0;
    }

    .user-sidebar ~ .main-content {
        margin-left: 68px !important;
        width: calc(100% - 68px) !important;
    }
}
</style>
</head>

</head>
<body>
<div class="app">
    <?php require __DIR__ . "/sidebar.php"; ?>

    <main class="main-content">
        <header class="topbar">
            <div>
                <strong><?= e($judul) ?></strong>
            </div>
            <div>
                Halo, <?= e($_SESSION['nama'] ?? 'Pembeli') ?>
            </div>
        </header>

        <section class="content">
