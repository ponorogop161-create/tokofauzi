
<?php
$currentPage = basename($_SERVER['PHP_SELF']);
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
        <a href="<?= BASE_URL ?>kasir/index.php"
           class="<?= $currentPage === 'index.php' && strpos($_SERVER['REQUEST_URI'], '/transaksi/') === false ? 'active' : '' ?>">
            <span>⌂</span> Dashboard
        </a>

        <a href="<?= BASE_URL ?>kasir/transaksi/index.php"
           class="<?= $currentPage === 'index.php' && strpos($_SERVER['REQUEST_URI'], '/transaksi/') !== false ? 'active' : '' ?>">
            <span>＋</span> Transaksi Baru
        </a>

        <a href="<?= BASE_URL ?>kasir/transaksi/riwayat.php"
           class="<?= $currentPage === 'riwayat.php' ? 'active' : '' ?>">
            <span>▤</span> Riwayat Transaksi
        </a>
    </nav>

    <div class="kasir-sidebar-bottom">
        <a href="<?= BASE_URL ?>logout.php">
            <span>↪</span> Keluar
        </a>
    </div>
</aside>
