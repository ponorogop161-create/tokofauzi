
<?php
require_once __DIR__ . "/../../config/koneksi.php";
require_once __DIR__ . "/../../config/auth.php";
wajib_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: checkout.php");
    exit;
}

$idUser = (int)($_SESSION['id_user'] ?? 0);
$keranjang = $_SESSION['keranjang'] ?? [];

$nama = trim($_POST['nama_penerima'] ?? '');
$noHp = trim($_POST['no_hp'] ?? '');
$kota = trim($_POST['kota'] ?? '');
$alamat = trim($_POST['alamat_pengiriman'] ?? '');
$metode = $_POST['metode_pembayaran'] ?? '';
$idCabang = null;
$bukti = null;
$fileTersimpan = null;

function kembaliCheckout(string $pesan): never {
    header("Location: checkout.php?error=" . urlencode($pesan));
    exit;
}

if (!$idUser || !$nama || !$noHp || empty($keranjang)) {
    kembaliCheckout("Data pesanan belum lengkap.");
}

if (!preg_match('/^[0-9+ -]{9,20}$/', $noHp)) {
    kembaliCheckout("Nomor HP tidak valid.");
}

if (!in_array($metode, ['QRIS', 'COD', 'KASIR'], true)) {
    kembaliCheckout("Metode pembayaran tidak valid.");
}

if ($metode === 'KASIR') {
    $idCabang = (int)($_POST['id_cabang'] ?? 0);

    if ($idCabang <= 0) {
        kembaliCheckout("Silakan pilih cabang toko.");
    }

    $stmt = $conn->prepare(
        "SELECT id_cabang, nama_cabang, kota
         FROM tbl_cabang WHERE id_cabang = ?"
    );
    $stmt->bind_param("i", $idCabang);
    $stmt->execute();
    $cabang = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$cabang) {
        kembaliCheckout("Cabang toko tidak ditemukan.");
    }

    $kota = $cabang['kota'];
    $alamat = "Pembayaran langsung di kasir cabang " .
              $cabang['nama_cabang'];
} else {
    if (!$kota || !$alamat) {
        kembaliCheckout("Kota dan alamat pengiriman wajib diisi.");
    }
}

if ($metode === 'QRIS') {
    if (
        !isset($_FILES['bukti_pembayaran']) ||
        $_FILES['bukti_pembayaran']['error'] !== UPLOAD_ERR_OK
    ) {
        kembaliCheckout("Silakan upload bukti pembayaran QRIS.");
    }

    $file = $_FILES['bukti_pembayaran'];

    if ($file['size'] > 2 * 1024 * 1024) {
        kembaliCheckout("Ukuran bukti pembayaran maksimal 2 MB.");
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    $ekstensiDiizinkan = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($ekstensiDiizinkan[$mime])) {
        kembaliCheckout("Bukti pembayaran harus berupa JPG, PNG, atau WEBP.");
    }

    $folder = __DIR__ . "/../../assets/uploads/pembayaran/";

    if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
        kembaliCheckout("Folder upload bukti pembayaran gagal dibuat.");
    }

    $namaFile = "qris_" . $idUser . "_" .
                bin2hex(random_bytes(8)) . "." .
                $ekstensiDiizinkan[$mime];

    if (!move_uploaded_file($file['tmp_name'], $folder . $namaFile)) {
        kembaliCheckout("Gagal menyimpan bukti pembayaran.");
    }

    $bukti = "assets/uploads/pembayaran/" . $namaFile;
    $fileTersimpan = $folder . $namaFile;
}

try {
    $conn->begin_transaction();

    // Cari akun dan hubungan pelanggan.
    $stmt = $conn->prepare(
        "SELECT id_pelanggan FROM tbl_users WHERE id_user = ? LIMIT 1"
    );
    $stmt->bind_param("i", $idUser);
    $stmt->execute();
    $akun = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$akun) {
        throw new Exception("Akun pembeli tidak ditemukan.");
    }

    $idPelanggan = (int)($akun['id_pelanggan'] ?? 0);

    if ($idPelanggan <= 0) {
        $stmt = $conn->prepare(
            "INSERT INTO tbl_pelanggan (nama_pelanggan, kota)
             VALUES (?, ?)"
        );
        $stmt->bind_param("ss", $nama, $kota);
        $stmt->execute();
        $idPelanggan = $conn->insert_id;
        $stmt->close();

        $stmt = $conn->prepare(
            "UPDATE tbl_users SET id_pelanggan = ? WHERE id_user = ?"
        );
        $stmt->bind_param("ii", $idPelanggan, $idUser);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare(
            "UPDATE tbl_pelanggan
             SET nama_pelanggan = ?, kota = ?
             WHERE id_pelanggan = ?"
        );
        $stmt->bind_param("ssi", $nama, $kota, $idPelanggan);
        $stmt->execute();
        $stmt->close();
    }

    // Periksa stok terbaru dan hitung total dari database.
    $items = [];
    $total = 0;

    foreach ($keranjang as $id => $jumlah) {
        $id = (int)$id;
        $jumlah = (int)$jumlah;

        if ($id <= 0 || $jumlah <= 0) {
            throw new Exception("Isi keranjang tidak valid.");
        }

        $stmt = $conn->prepare(
            "SELECT id_produk, nama_produk, harga, stok
             FROM tbl_produk
             WHERE id_produk = ?
             FOR UPDATE"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $produk = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$produk || $jumlah > (int)$produk['stok']) {
            throw new Exception(
                "Stok " . ($produk['nama_produk'] ?? 'produk') .
                " tidak mencukupi."
            );
        }

        $harga = (float)$produk['harga'];
        $subtotal = $harga * $jumlah;
        $total += $subtotal;

        $items[] = [
            'id_produk' => (int)$produk['id_produk'],
            'nama_produk' => $produk['nama_produk'],
            'harga' => $harga,
            'jumlah' => $jumlah,
            'subtotal' => $subtotal
        ];
    }

    // Tentukan status awal pembayaran.
    if ($metode === 'QRIS') {
    $statusPembayaran = 'menunggu_verifikasi';
} elseif ($metode === 'KASIR') {
    $statusPembayaran = 'lunas';
} else {
    $statusPembayaran = 'belum_bayar';
}

    $statusPesanan = 'menunggu';

    $stmt = $conn->prepare(
        "INSERT INTO tbl_transaksi
        (
            tanggal, id_pelanggan, total_belanja, id_user,
            alamat_pengiriman, no_hp, metode_pembayaran,
            status_pembayaran, status_pesanan,
            bukti_pembayaran, id_cabang
        )
        VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
        "idissssssi",
        $idPelanggan,
        $total,
        $idUser,
        $alamat,
        $noHp,
        $metode,
        $statusPembayaran,
        $statusPesanan,
        $bukti,
        $idCabang
    );

    $stmt->execute();
    $idTransaksi = $conn->insert_id;
    $stmt->close();

    // Simpan detail dan kurangi stok produk.
    $stmtDetail = $conn->prepare(
        "INSERT INTO tbl_detail_transaksi
        (id_transaksi, nama_produk, jumlah, harga, id_produk, subtotal)
        VALUES (?, ?, ?, ?, ?, ?)"
    );

    $stmtStok = $conn->prepare(
        "UPDATE tbl_produk
         SET stok = stok - ?
         WHERE id_produk = ? AND stok >= ?"
    );

    foreach ($items as $item) {
        $stmtDetail->bind_param(
            "isidid",
            $idTransaksi,
            $item['nama_produk'],
            $item['jumlah'],
            $item['harga'],
            $item['id_produk'],
            $item['subtotal']
        );
        $stmtDetail->execute();

        $stmtStok->bind_param(
            "iii",
            $item['jumlah'],
            $item['id_produk'],
            $item['jumlah']
        );
        $stmtStok->execute();

        if ($stmtStok->affected_rows !== 1) {
            throw new Exception("Gagal memperbarui stok produk.");
        }
    }

    $stmtDetail->close();
    $stmtStok->close();

    $conn->commit();

    unset($_SESSION['keranjang']);

    header("Location: detail.php?id=" . $idTransaksi . "&berhasil=1");
    exit;

} catch (Throwable $e) {
    $conn->rollback();

    if ($fileTersimpan && is_file($fileTersimpan)) {
        unlink($fileTersimpan);
    }

    kembaliCheckout($e->getMessage());
}
