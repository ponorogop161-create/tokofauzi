
<?php
require_once __DIR__ . "/../../config/auth.php";
wajib_admin();

require_once __DIR__ . "/../../config/koneksi.php";

$judul = "Riwayat Aktivitas";

$kataKunci = trim($_GET['cari'] ?? '');
$dari = $_GET['dari'] ?? '';
$sampai = $_GET['sampai'] ?? '';

// Pengaturan pagination
$batas = 10;
$halaman = max(1, (int)($_GET['halaman'] ?? 1));

function tanggalAktivitasValid($tanggal) {
    $date = DateTime::createFromFormat('Y-m-d', $tanggal);
    return $date && $date->format('Y-m-d') === $tanggal;
}

if (
    ($dari !== '' && !tanggalAktivitasValid($dari)) ||
    ($sampai !== '' && !tanggalAktivitasValid($sampai)) ||
    ($dari !== '' && $sampai !== '' && $dari > $sampai)
) {
    $dari = '';
    $sampai = '';
    $halaman = 1;
}

$aktivitas = [];

// Ambil log transaksi dan aktivitas umum
$sql = "
    SELECT
        id_log,
        aktivitas,
        keterangan,
        waktu,
        'Sistem / Transaksi' AS sumber
    FROM tbl_log
    WHERE 1=1
";

$types = "";
$params = [];

if ($kataKunci !== '') {
    $sql .= " AND (aktivitas LIKE ? OR keterangan LIKE ?)";
    $cari = "%$kataKunci%";
    $types .= "ss";
    $params[] = $cari;
    $params[] = $cari;
}

if ($dari !== '') {
    $sql .= " AND DATE(waktu) >= ?";
    $types .= "s";
    $params[] = $dari;
}

if ($sampai !== '') {
    $sql .= " AND DATE(waktu) <= ?";
    $types .= "s";
    $params[] = $sampai;
}

$stmt = $conn->prepare($sql);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $aktivitas[] = $row;
}

$stmt->close();

// Ambil log pelanggan
$sql = "
    SELECT
        l.id_log,
        l.aktivitas,
        CONCAT(
            'Pelanggan: ',
            COALESCE(l.nama_lama, l.nama_baru, '-'),
            CASE
                WHEN l.nama_lama IS NOT NULL
                     AND l.nama_baru IS NOT NULL
                     AND l.nama_lama <> l.nama_baru
                THEN CONCAT(' → ', l.nama_baru)
                ELSE ''
            END
        ) AS keterangan,
        l.waktu,
        'Pelanggan' AS sumber
    FROM log_pelanggan l
    WHERE 1=1
";

$types = "";
$params = [];

if ($kataKunci !== '') {
    $sql .= " AND (
        l.aktivitas LIKE ?
        OR l.nama_lama LIKE ?
        OR l.nama_baru LIKE ?
    )";

    $cari = "%$kataKunci%";
    $types .= "sss";
    $params[] = $cari;
    $params[] = $cari;
    $params[] = $cari;
}

if ($dari !== '') {
    $sql .= " AND DATE(l.waktu) >= ?";
    $types .= "s";
    $params[] = $dari;
}

if ($sampai !== '') {
    $sql .= " AND DATE(l.waktu) <= ?";
    $types .= "s";
    $params[] = $sampai;
}

$stmt = $conn->prepare($sql);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $aktivitas[] = $row;
}

$stmt->close();

// Urutkan seluruh aktivitas berdasarkan waktu terbaru
usort($aktivitas, function ($a, $b) {
    $waktu = strtotime($b['waktu']) <=> strtotime($a['waktu']);

    if ($waktu !== 0) {
        return $waktu;
    }

    return (int)$b['id_log'] <=> (int)$a['id_log'];
});

// Statistik seluruh hasil filter
$totalAktivitas = count($aktivitas);

$jumlahSistem = count(array_filter(
    $aktivitas,
    fn($item) => $item['sumber'] === 'Sistem / Transaksi'
));

$jumlahPelanggan = count(array_filter(
    $aktivitas,
    fn($item) => $item['sumber'] === 'Pelanggan'
));

// Menghitung halaman
$totalHalaman = max(1, (int)ceil($totalAktivitas / $batas));
$halaman = min($halaman, $totalHalaman);
$mulai = ($halaman - 1) * $batas;

// Mengambil aktivitas sesuai halaman
$aktivitasHalaman = array_slice($aktivitas, $mulai, $batas);

// Membuat URL pagination
function linkHalaman($nomor, $kataKunci, $dari, $sampai) {
    return "index.php?" . http_build_query([
        'cari' => $kataKunci,
        'dari' => $dari,
        'sampai' => $sampai,
        'halaman' => $nomor
    ]);
}

require_once __DIR__ . "/../header.php";
?>

<div class="page-header">
    <div>
        <h2>Riwayat Aktivitas</h2>
        <p>Pantau aktivitas transaksi dan perubahan data pelanggan.</p>
    </div>
</div>

<!-- Statistik -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-info">
            <span>Total Aktivitas</span>
            <h3><?= number_format($totalAktivitas) ?></h3>
        </div>
        <div class="stat-icon">🕒</div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span>Log Transaksi / Sistem</span>
            <h3><?= number_format($jumlahSistem) ?></h3>
        </div>
        <div class="stat-icon">🧾</div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span>Log Pelanggan</span>
            <h3><?= number_format($jumlahPelanggan) ?></h3>
        </div>
        <div class="stat-icon">👥</div>
    </div>
</div>

<!-- Filter Aktivitas -->
<div class="panel" style="margin-top:25px;">
    <div class="card-header">
        <div>
            <h3>Filter Aktivitas</h3>
            <p>Cari berdasarkan aktivitas atau tanggal.</p>
        </div>
    </div>

    <form method="GET"
          style="display:flex;gap:15px;align-items:end;flex-wrap:wrap;">

        <div style="flex:1;min-width:180px;">
            <label>Kata Kunci</label>
            <input
                type="text"
                name="cari"
                placeholder="Cari aktivitas..."
                value="<?= e($kataKunci) ?>"
            >
        </div>

        <div>
            <label>Dari Tanggal</label>
            <input
                type="date"
                name="dari"
                value="<?= e($dari) ?>"
            >
        </div>

        <div>
            <label>Sampai Tanggal</label>
            <input
                type="date"
                name="sampai"
                value="<?= e($sampai) ?>"
            >
        </div>

        <button type="submit" class="btn btn-primary">
            Cari
        </button>

        <a href="index.php" class="btn btn-secondary">
            Reset
        </a>
    </form>
</div>

<!-- Daftar Aktivitas -->
<div class="panel" style="margin-top:25px;">
    <div class="card-header">
        <div>
            <h3>Daftar Riwayat Aktivitas</h3>
            <p>Aktivitas ditampilkan dari yang terbaru.</p>
        </div>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Aktivitas</th>
                    <th>Keterangan</th>
                    <th>Sumber</th>
                    <th>Waktu</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($totalAktivitas > 0): ?>

                    <?php foreach ($aktivitasHalaman as $no => $row): ?>
                        <tr>
                            <td><?= $mulai + $no + 1 ?></td>

                            <td>
                                <strong>
                                    <?= e($row['aktivitas']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= e($row['keterangan'] ?? '-') ?>
                            </td>

                            <td>
                                <span class="badge">
                                    <?= e($row['sumber']) ?>
                                </span>
                            </td>

                            <td>
                                <?= date(
                                    'd/m/Y H:i:s',
                                    strtotime($row['waktu'])
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align:center;">
                            Belum ada aktivitas yang ditemukan.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalAktivitas > 0): ?>
        <div class="pagination-container">

            <div class="pagination-info">
                Menampilkan
                <?= $mulai + 1 ?>
                -
                <?= min($mulai + $batas, $totalAktivitas) ?>
                dari
                <?= number_format($totalAktivitas) ?>
                aktivitas
            </div>

            <div class="pagination">

                <!-- Sebelumnya -->
                <?php if ($halaman > 1): ?>
                    <a
                        href="<?= e(linkHalaman($halaman - 1, $kataKunci, $dari, $sampai)) ?>"
                        class="page-link"
                    >
                        &laquo; Sebelumnya
                    </a>
                <?php else: ?>
                    <span class="page-link disabled">
                        &laquo; Sebelumnya
                    </span>
                <?php endif; ?>

                <?php
                $awal = max(1, $halaman - 2);
                $akhir = min($totalHalaman, $halaman + 2);
                ?>

                <!-- Halaman pertama -->
                <?php if ($awal > 1): ?>
                    <a
                        href="<?= e(linkHalaman(1, $kataKunci, $dari, $sampai)) ?>"
                        class="page-link"
                    >
                        1
                    </a>

                    <?php if ($awal > 2): ?>
                        <span class="page-dots">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Nomor halaman -->
                <?php for ($i = $awal; $i <= $akhir; $i++): ?>
                    <a
                        href="<?= e(linkHalaman($i, $kataKunci, $dari, $sampai)) ?>"
                        class="page-link <?= $i == $halaman ? 'active' : '' ?>"
                    >
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <!-- Halaman terakhir -->
                <?php if ($akhir < $totalHalaman): ?>
                    <?php if ($akhir < $totalHalaman - 1): ?>
                        <span class="page-dots">...</span>
                    <?php endif; ?>

                    <a
                        href="<?= e(linkHalaman($totalHalaman, $kataKunci, $dari, $sampai)) ?>"
                        class="page-link"
                    >
                        <?= $totalHalaman ?>
                    </a>
                <?php endif; ?>

                <!-- Selanjutnya -->
                <?php if ($halaman < $totalHalaman): ?>
                    <a
                        href="<?= e(linkHalaman($halaman + 1, $kataKunci, $dari, $sampai)) ?>"
                        class="page-link"
                    >
                        Selanjutnya &raquo;
                    </a>
                <?php else: ?>
                    <span class="page-link disabled">
                        Selanjutnya &raquo;
                    </span>
                <?php endif; ?>

            </div>
        </div>
    <?php endif; ?>

</div>

<style>
.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 20px;
    padding: 15px 0;
}

.pagination-info {
    font-size: 13px;
    color: #64748b;
}

.pagination {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 5px;
}

.page-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    text-decoration: none;
    color: #334155;
    background: #fff;
    font-size: 13px;
    font-weight: 600;
}

.page-link:hover {
    background: #eff6ff;
    border-color: #2563eb;
    color: #2563eb;
}

.page-link.active {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}

.page-link.disabled {
    color: #94a3b8;
    background: #f8fafc;
    cursor: not-allowed;
}

.page-dots {
    padding: 0 4px;
    color: #64748b;
}

@media (max-width: 600px) {
    .pagination-container {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<?php require_once __DIR__ . "/../../includes/footer.php"; ?>
