
<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "db_tokoku";

$conn = mysqli_connect(
    $host,
    $username,
    $password,
    $database
);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

function rupiah($angka)
{
    return "Rp " . number_format((float)$angka, 0, ',', '.');
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}


function catat_log(mysqli $conn, string $aktivitas, string $keterangan = ''): void
{
    $stmt = $conn->prepare("
        INSERT INTO tbl_log (aktivitas, keterangan, waktu)
        VALUES (?, ?, NOW())
    ");

    if ($stmt) {
        $stmt->bind_param("ss", $aktivitas, $keterangan);
        $stmt->execute();
        $stmt->close();
    }
}
