
<?php
$menu = [
    ['Dashboard', 'admin/index.php', '⌂'],
    ['Produk', 'admin/produk/index.php', '▣'],
    ['Kategori', 'admin/kategori/index.php', '▦'],
    ['Pelanggan', 'admin/pelanggan/index.php', '♙'],
    ['Cabang', 'admin/cabang/index.php', '⌖'],
    ['Akun Pengguna', 'admin/akun/index.php', '♧'],
    ['Transaksi', 'admin/transaksi/index.php', '▤'],
    ['Laporan', 'admin/laporan/index.php', '▥'],
    ['Aktivitas', 'admin/aktivitas/index.php', '◷']
];

$currentPage = $_SERVER['REQUEST_URI'];
?>

<aside class="sidebar">
    <div class="logo">Toko<span>Ku.</span></div>
    <div class="menu-label">MENU ADMIN</div>

    <?php foreach ($menu as $item): ?>
        <?php
        $url = BASE_URL . $item[1];
        $active = strpos($currentPage, $item[1]) !== false;
        ?>
        <a class="menu-item <?= $active ? 'active' : '' ?>"
           href="<?= e($url) ?>">
            <span><?= $item[2] ?></span>
            <?= e($item[0]) ?>
        </a>
    <?php endforeach; ?>

    <div class="sidebar-bottom">
        <a class="menu-item" href="<?= BASE_URL ?>logout.php">
            <span>↪</span>
            Logout
        </a>
    </div>
</aside>
