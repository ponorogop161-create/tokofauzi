
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
$metode = strtoupper(trim($_POST['metode_pembayaran'] ?? ''));

$emailPelanggan = trim(
    $_POST['email_pelanggan'] ?? $_POST['email'] ?? ''
);
$duitkuPaymentCode = strtoupper(
    trim($_POST['duitku_payment_code'] ?? '')
);

$metodeDuitkuDiizinkan = [
    'BR', 'BC', 'M2', 'I1', 'B1', 'BT', 'VA', 'BV',
    'NQ', 'DA', 'OV', 'SA', 'LF', 'IR', 'FT', 'VC',
    'DN', 'AT'
];

$idCabang = null;
$bukti = null;
$fileTersimpan = null;
$transaksiDimulai = false;

function kembaliCheckout(string $pesan): never
{
    header("Location: checkout.php?error=" . urlencode($pesan));
    exit;
}

// =====================================
// VALIDASI DATA
// =====================================

if (!$idUser || !$nama || !$noHp || empty($keranjang)) {
    kembaliCheckout("Data pesanan belum lengkap.");
}

if (!preg_match('/^[0-9+ -]{9,20}$/', $noHp)) {
    kembaliCheckout("Nomor HP tidak valid.");
}

if (!in_array($metode, ['QRIS', 'COD', 'KASIR', 'DUITKU'], true)) {
    kembaliCheckout("Metode pembayaran tidak valid.");
}

// Validasi email khusus Duitku.
if ($metode === 'DUITKU') {
    if (
        !$emailPelanggan ||
        !filter_var($emailPelanggan, FILTER_VALIDATE_EMAIL)
    ) {
        kembaliCheckout("Alamat email Duitku tidak valid.");
    }

    if (strlen($emailPelanggan) > 150) {
        kembaliCheckout("Alamat email maksimal 150 karakter.");
    }

    if (!in_array($duitkuPaymentCode, $metodeDuitkuDiizinkan, true)) {
        kembaliCheckout("Silakan pilih metode pembayaran Duitku.");
    }
}

// =====================================
// VALIDASI CABANG KASIR
// =====================================

if ($metode === 'KASIR') {
    $idCabang = (int)($_POST['id_cabang'] ?? 0);

    if ($idCabang <= 0) {
        kembaliCheckout("Silakan pilih cabang toko.");
    }

    $stmt = $conn->prepare(
        "SELECT id_cabang, nama_cabang, kota
         FROM tbl_cabang
         WHERE id_cabang = ?"
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

// =====================================
// UPLOAD BUKTI PEMBAYARAN QRIS
// =====================================

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
        kembaliCheckout(
            "Bukti pembayaran harus berupa JPG, PNG, atau WEBP."
        );
    }

    $folder = __DIR__ . "/../../assets/uploads/pembayaran/";

    if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
        kembaliCheckout(
            "Folder upload bukti pembayaran gagal dibuat."
        );
    }

    $namaFile = "qris_" . $idUser . "_" .
                bin2hex(random_bytes(8)) . "." .
                $ekstensiDiizinkan[$mime];

    $fileTersimpan = $folder . $namaFile;

    if (!move_uploaded_file($file['tmp_name'], $fileTersimpan)) {
        $fileTersimpan = null;
        kembaliCheckout("Gagal menyimpan bukti pembayaran.");
    }

    $bukti = "assets/uploads/pembayaran/" . $namaFile;
}

// =====================================
// STATUS AWAL PEMBAYARAN
// =====================================

if ($metode === 'QRIS') {
    $statusPembayaran = 'menunggu_verifikasi';
} else {
    // COD, KASIR, dan DUITKU menunggu pembayaran.
    $statusPembayaran = 'belum_bayar';
}

$statusPesanan = 'menunggu';

// =====================================
// PROSES TRANSAKSI
// =====================================

try {
    $conn->begin_transaction();
    $transaksiDimulai = true;

    // =====================================
    // AMBIL DATA AKUN PEMBELI
    // =====================================

    $stmt = $conn->prepare(
        "SELECT id_pelanggan
         FROM tbl_users
         WHERE id_user = ?
         LIMIT 1"
    );

    $stmt->bind_param("i", $idUser);
    $stmt->execute();

    $akun = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$akun) {
        throw new Exception("Akun pembeli tidak ditemukan.");
    }

    $idPelanggan = (int)($akun['id_pelanggan'] ?? 0);

    // =====================================
    // DATA PELANGGAN
    // =====================================

    if ($idPelanggan <= 0) {
        $stmt = $conn->prepare(
            "INSERT INTO tbl_pelanggan
             (nama_pelanggan, kota)
             VALUES (?, ?)"
        );

        $stmt->bind_param("ss", $nama, $kota);
        $stmt->execute();

        $idPelanggan = $conn->insert_id;
        $stmt->close();

        $stmt = $conn->prepare(
            "UPDATE tbl_users
             SET id_pelanggan = ?
             WHERE id_user = ?"
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

    // =====================================
    // CEK STOK DAN HITUNG TOTAL
    // =====================================

    $items = [];
    $subtotalBelanja = 0;

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
                "Stok " .
                ($produk['nama_produk'] ?? 'produk') .
                " tidak mencukupi."
            );
        }

        $harga = (float)$produk['harga'];
        $subtotal = $harga * $jumlah;

        $subtotalBelanja += $subtotal;

        $items[] = [
            'id_produk' => (int)$produk['id_produk'],
            'nama_produk' => $produk['nama_produk'],
            'harga' => $harga,
            'jumlah' => $jumlah,
            'subtotal' => $subtotal
        ];
    }

    if ($subtotalBelanja <= 0) {
        throw new Exception("Total belanja tidak valid.");
    }

    $ppn = round($subtotalBelanja * 0.11, 2);
    $total = $subtotalBelanja + $ppn;

    // Duitku menggunakan nominal Rupiah berupa bilangan bulat.
    if (
        $metode === 'DUITKU' &&
        abs($total - round($total)) > 0.0001
    ) {
        throw new Exception(
            "Total pembayaran Duitku harus berupa nominal Rupiah bulat."
        );
    }

    // =====================================
    // SIMPAN TRANSAKSI
    // =====================================

    $emailDatabase = $metode === 'DUITKU'
        ? $emailPelanggan
        : null;

    $stmt = $conn->prepare(
        "INSERT INTO tbl_transaksi
        (
            tanggal,
            id_pelanggan,
            total_belanja,
            ppn,
            id_user,
            alamat_pengiriman,
            no_hp,
            metode_pembayaran,
            status_pembayaran,
            status_pesanan,
            bukti_pembayaran,
            email_pelanggan,
            id_cabang
        )
        VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
        "iddisssssssi",
        $idPelanggan,
        $total,
        $ppn,
        $idUser,
        $alamat,
        $noHp,
        $metode,
        $statusPembayaran,
        $statusPesanan,
        $bukti,
        $emailDatabase,
        $idCabang
    );

    $stmt->execute();

    $idTransaksi = $conn->insert_id;
    $stmt->close();

    // =====================================
    // SIMPAN DETAIL TRANSAKSI DAN UPDATE STOK
    // =====================================

    $stmtDetail = $conn->prepare(
        "INSERT INTO tbl_detail_transaksi
        (
            id_transaksi,
            nama_produk,
            jumlah,
            harga,
            id_produk,
            subtotal
        )
        VALUES (?, ?, ?, ?, ?, ?)"
    );

    $stmtStok = $conn->prepare(
        "UPDATE tbl_produk
         SET stok = stok - ?
         WHERE id_produk = ?
         AND stok >= ?"
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
            throw new Exception(
                "Gagal memperbarui stok produk."
            );
        }
    }

    $stmtDetail->close();
    $stmtStok->close();

    // =====================================
    // INTEGRASI DUITKU POP SANDBOX
    // =====================================

    if ($metode === 'DUITKU') {
        $configDuitkuPath = __DIR__ . "/../../config/duitku.php";

        if (!is_file($configDuitkuPath)) {
            throw new Exception(
                "File konfigurasi Duitku tidak ditemukan."
            );
        }

        $duitku = require $configDuitkuPath;

        $merchantCode = trim($duitku['merchant_code'] ?? '');
        $apiKey = trim($duitku['api_key'] ?? '');
        $sandbox = (bool)($duitku['sandbox'] ?? true);

        $callbackUrl = trim($duitku['callback_url'] ?? '');
        $returnUrl = trim($duitku['return_url'] ?? '');

        $expiryPeriod = (int)($duitku['expiry_period'] ?? 60);

        if (
            !$merchantCode ||
            !$apiKey ||
            $apiKey === 'ISI_API_KEY_BARU_DI_SINI'
        ) {
            throw new Exception(
                "Konfigurasi Merchant Code atau API Key Duitku belum benar."
            );
        }

        if (
            !$callbackUrl ||
            strpos($callbackUrl, 'DOMAIN-NGROK') !== false ||
            !filter_var($callbackUrl, FILTER_VALIDATE_URL) ||
            !str_starts_with($callbackUrl, 'https://')
        ) {
            throw new Exception(
                "URL callback Duitku belum diatur menggunakan URL publik HTTPS."
            );
        }

        if (!filter_var($returnUrl, FILTER_VALIDATE_URL)) {
            throw new Exception(
                "URL kembali Duitku belum diatur dengan benar."
            );
        }

        if ($expiryPeriod < 5 || $expiryPeriod > 1440) {
            $expiryPeriod = 60;
        }

        // ID pesanan unik untuk setiap invoice.
        $merchantOrderId = 'TK' . $idTransaksi . '-' .
                           strtoupper(bin2hex(random_bytes(5)));

        // URL kembali ke halaman detail transaksi.
        $separator = strpos($returnUrl, '?') === false ? '?' : '&';

        $returnUrlTransaksi = $returnUrl . $separator .
            'id=' . $idTransaksi . '&duitku_return=1';

        $paymentAmount = (int)round($total);

        $productDetails = 'Pembayaran pesanan TokoKu #' .
                          $idTransaksi;

        // Timestamp dalam milidetik.
        $timestamp = (string)round(microtime(true) * 1000);

        // Signature Create Invoice Duitku POP.
        $stringToSign = $merchantCode . $timestamp;

        $signature = hash_hmac(
            'sha256',
            $stringToSign,
            $apiKey
        );

        $payload = [
            'paymentAmount' => $paymentAmount,
            'merchantOrderId' => $merchantOrderId,
            'productDetails' => $productDetails,
            'email' => $emailPelanggan,
            'phoneNumber' => $noHp,
            'customerVaName' => substr($nama, 0, 20),
            'paymentMethod' => $duitkuPaymentCode,
            'callbackUrl' => $callbackUrl,
            'returnUrl' => $returnUrlTransaksi,
            'expiryPeriod' => $expiryPeriod
        ];

        $endpoint = $sandbox
            ? 'https://api-sandbox.duitku.com/api/merchant/createInvoice'
            : 'https://api-prod.duitku.com/api/merchant/createInvoice';

        $ch = curl_init($endpoint);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES
            ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'x-duitku-merchantcode: ' . $merchantCode,
                'x-duitku-timestamp: ' . $timestamp,
                'x-duitku-signature: ' . $signature
            ]
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($response === false || $curlError) {
            throw new Exception(
                "Tidak dapat menghubungi Duitku. Periksa koneksi internet dan ekstensi cURL XAMPP."
            );
        }

        $hasilDuitku = json_decode($response, true);

        if (
            $httpCode !== 200 ||
            !is_array($hasilDuitku) ||
            ($hasilDuitku['statusCode'] ?? '') !== '00' ||
            empty($hasilDuitku['reference']) ||
            empty($hasilDuitku['paymentUrl'])
        ) {
            $pesanDuitku = is_array($hasilDuitku)
                ? ($hasilDuitku['statusMessage'] ?? 'Respons Duitku tidak valid.')
                : 'Respons Duitku tidak dapat dibaca.';

            throw new Exception(
                "Gagal membuat invoice Duitku: " . $pesanDuitku
            );
        }

        $reference = trim($hasilDuitku['reference']);
        $paymentUrl = trim($hasilDuitku['paymentUrl']);

        if (!str_starts_with($paymentUrl, 'https://')) {
            throw new Exception(
                "URL pembayaran yang diterima dari Duitku tidak valid."
            );
        }

        // Simpan waktu kedaluwarsa invoice.
        $tanggalKedaluwarsa = date(
            'Y-m-d H:i:s',
            time() + ($expiryPeriod * 60)
        );

        // Simpan data invoice Duitku ke database.
        $stmtUpdate = $conn->prepare(
            "UPDATE tbl_transaksi
             SET email_pelanggan = ?,
                 merchant_order_id = ?,
                 duitku_reference = ?,
                 duitku_payment_code = ?,
                 duitku_payment_url = ?,
                 duitku_expiry = ?
             WHERE id_transaksi = ?
               AND id_user = ?
               AND metode_pembayaran = 'DUITKU'"
        );

        $stmtUpdate->bind_param(
            "ssssssii",
            $emailPelanggan,
            $merchantOrderId,
            $reference,
            $duitkuPaymentCode,
            $paymentUrl,
            $tanggalKedaluwarsa,
            $idTransaksi,
            $idUser
        );

        $stmtUpdate->execute();

        if ($stmtUpdate->affected_rows < 0) {
            throw new Exception(
                "Gagal menyimpan informasi invoice Duitku."
            );
        }

        $stmtUpdate->close();
    }

    // =====================================
    // COMMIT TRANSAKSI
    // =====================================

    $conn->commit();
    $transaksiDimulai = false;

    unset($_SESSION['keranjang']);

    header(
        "Location: detail.php?id=" .
        $idTransaksi . "&berhasil=1"
    );

    exit;

} catch (Throwable $e) {
    if ($transaksiDimulai) {
        $conn->rollback();
    }

    if ($fileTersimpan && is_file($fileTersimpan)) {
        unlink($fileTersimpan);
    }

    // Catat detail error di log server, bukan menampilkan API Key.
    error_log(
        "[TokoKu Checkout] " .
        $e->getMessage()
    );

    kembaliCheckout($e->getMessage());
}
