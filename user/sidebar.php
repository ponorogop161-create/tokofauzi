
<?php
$halamanAktif = $_SERVER['PHP_SELF'];
?>

<aside class="user-sidebar">
    <div class="user-sidebar-brand">
        <div class="user-brand-icon">T</div>
        <div>
            <h2>TokoKu</h2>
            <span>Area Pembeli</span>
        </div>
    </div>

    <div class="user-menu-label">MENU UTAMA</div>

    <nav class="user-sidebar-menu">
        <a href="<?= BASE_URL ?>user/index.php"
           class="<?= basename($halamanAktif) === 'index.php' && str_contains($halamanAktif, '/user/') && !str_contains($halamanAktif, '/produk/') && !str_contains($halamanAktif, '/keranjang/') && !str_contains($halamanAktif, '/pesanan/') ? 'active' : '' ?>">
            <span class="user-menu-icon">⌂</span>
            <span>Dashboard</span>
        </a>

        <a href="<?= BASE_URL ?>user/produk/index.php"
           class="<?= str_contains($halamanAktif, '/produk/') ? 'active' : '' ?>">
            <span class="user-menu-icon">▣</span>
            <span>Katalog Produk</span>
        </a>

        <a href="<?= BASE_URL ?>user/keranjang/index.php"
           class="<?= str_contains($halamanAktif, '/keranjang/') ? 'active' : '' ?>">
            <span class="user-menu-icon">🛒</span>
            <span>Keranjang</span>
        </a>

        <a href="<?= BASE_URL ?>user/pesanan/index.php"
           class="<?= str_contains($halamanAktif, '/pesanan/') ? 'active' : '' ?>">
            <span class="user-menu-icon">▤</span>
            <span>Pesanan Saya</span>
        </a>
    </nav>

    <div class="user-sidebar-bottom">
        <a href="<?= BASE_URL ?>logout.php">
            <span class="user-menu-icon">↪</span>
            <span>Logout</span>
        </a>
    </div>
</aside>
