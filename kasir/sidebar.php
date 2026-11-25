
<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$currentUrl = $_SERVER['REQUEST_URI'];
?>

<aside class="kasir-sidebar">

    <div class="kasir-brand">
        <div class="brand-icon">T</div>
        <div>
            <h2>TokoKu</h2>
            <span>Point of Sale</span>
        </div>
    </div>

    <div class="kasir-menu-label">MENU UTAMA</div>

    <nav class="kasir-menu">

        <!-- DASHBOARD -->
        <a href="<?= BASE_URL ?>kasir/index.php"
           class="<?= $currentPage === 'index.php' && strpos($currentUrl, '/transaksi/') === false && strpos($currentUrl, '/laporan/') === false ? 'active' : '' ?>">
            <span>⌂</span>
            Dashboard
        </a>

        <!-- TRANSAKSI BARU -->
        <a href="<?= BASE_URL ?>kasir/transaksi/index.php"
           class="<?= $currentPage === 'index.php' && strpos($currentUrl, '/transaksi/') !== false ? 'active' : '' ?>">
            <span>＋</span>
            Transaksi Baru
        </a>

        <!-- RIWAYAT TRANSAKSI -->
        <a href="<?= BASE_URL ?>kasir/transaksi/riwayat.php"
           class="<?= $currentPage === 'riwayat.php' ? 'active' : '' ?>">
            <span>▤</span>
            Riwayat Transaksi
        </a>

        <!-- LAPORAN PENJUALAN -->
        <a href="<?= BASE_URL ?>kasir/laporan/index.php"
           class="<?= strpos($currentUrl, '/laporan/') !== false ? 'active' : '' ?>">
            <span>▥</span>
            Laporan Penjualan
        </a>

    </nav>

    <div class="kasir-sidebar-bottom">
        <a href="<?= BASE_URL ?>logout.php">
            <span>↪</span>
            Keluar
        </a>
    </div>

</aside>