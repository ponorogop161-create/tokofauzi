
<?php
require_once __DIR__ . "/../../config/auth.php";
wajib_admin();

require_once __DIR__ . "/../../config/koneksi.php";

$dari = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');

function tanggalValidExport($tanggal) {
    $date = DateTime::createFromFormat('Y-m-d', $tanggal);
    return $date && $date->format('Y-m-d') === $tanggal;
}

if (
    !tanggalValidExport($dari) ||
    !tanggalValidExport($sampai) ||
    $dari > $sampai
) {
    die("Periode tanggal tidak valid.");
}

$stmt = $conn->prepare("
    SELECT
        t.id_transaksi,
        t.tanggal,
        p.nama_pelanggan,
        u.nama AS nama_kasir,
        t.total_belanja
    FROM tbl_transaksi t
    LEFT JOIN tbl_pelanggan p
        ON p.id_pelanggan = t.id_pelanggan
    LEFT JOIN tbl_users u
        ON u.id_user = t.id_user
    WHERE DATE(t.tanggal) BETWEEN ? AND ?
    ORDER BY t.tanggal DESC
");

$stmt->bind_param("ss", $dari, $sampai);
$stmt->execute();
$result = $stmt->get_result();

$namaFile = "laporan-penjualan-" . $dari . "-sampai-" . $sampai . ".csv";

header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$namaFile\"");

$output = fopen("php://output", "w");

// BOM agar karakter Indonesia terbaca dengan baik di Excel
fwrite($output, "\xEF\xBB\xBF");

fputcsv($output, [
    "ID Transaksi",
    "Tanggal",
    "Pelanggan",
    "Kasir / Pengguna",
    "Total Belanja"
], ";");

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id_transaksi'],
        $row['tanggal'],
        $row['nama_pelanggan'] ?? 'Umum',
        $row['nama_kasir'] ?? '-',
        $row['total_belanja']
    ], ";");
}

fclose($output);
$stmt->close();
exit;
