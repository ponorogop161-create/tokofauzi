
<?php
/**
 * TokoKu - Duitku Payment Callback
 * Lokasi: duitku/callback.php
 */

require_once __DIR__ . '/../config/koneksi.php';

$config = require __DIR__ . '/../config/duitku.php';

// =====================================
// VALIDASI REQUEST
// =====================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$merchantCodeConfig = $config['merchant_code'] ?? '';
$apiKey = $config['api_key'] ?? '';

if (
    $merchantCodeConfig === '' ||
    $apiKey === '' ||
    $apiKey === 'ISI_API_KEY_BARU_DI_SINI'
) {
    error_log('Duitku: Konfigurasi API belum lengkap.');
    http_response_code(500);
    exit('Configuration Error');
}

// =====================================
// AMBIL DATA CALLBACK
// =====================================

$merchantCode = trim($_POST['merchantCode'] ?? '');
$amount = trim($_POST['amount'] ?? '');
$merchantOrderId = trim($_POST['merchantOrderId'] ?? '');
$resultCode = trim($_POST['resultCode'] ?? '');
$reference = trim($_POST['reference'] ?? '');
$paymentCode = strtoupper(trim($_POST['paymentCode'] ?? ''));
$signature = trim($_POST['signature'] ?? '');

if (
    $merchantCode === '' ||
    $amount === '' ||
    $merchantOrderId === '' ||
    $resultCode === '' ||
    $reference === '' ||
    $signature === ''
) {
    error_log('Duitku: Parameter callback tidak lengkap.');
    http_response_code(400);
    exit('Invalid Parameters');
}

// =====================================
// VERIFIKASI MERCHANT
// =====================================

if (!hash_equals($merchantCodeConfig, $merchantCode)) {
    http_response_code(403);
    exit('Invalid Merchant');
}

// =====================================
// VERIFIKASI NOMINAL
// =====================================

if (!ctype_digit($amount)) {
    http_response_code(400);
    exit('Invalid Amount');
}

// =====================================
// VERIFIKASI SIGNATURE DUITKU
// =====================================

$stringToSign = $merchantCode . $amount . $merchantOrderId;

$signatureValid = hash_hmac(
    'sha256',
    $stringToSign,
    $apiKey
);

if (!hash_equals($signatureValid, $signature)) {
    error_log(
        'Duitku: Signature tidak valid untuk order ' .
        $merchantOrderId
    );

    http_response_code(403);
    exit('Invalid Signature');
}

// =====================================
// PROSES CALLBACK
// =====================================

try {

    $conn->begin_transaction();

    // Cari transaksi berdasarkan merchant order ID

    $stmt = $conn->prepare("
        SELECT
            id_transaksi,
            total_belanja,
            status_pembayaran,
            duitku_reference
        FROM tbl_transaksi
        WHERE merchant_order_id = ?
          AND metode_pembayaran = 'DUITKU'
        LIMIT 1
        FOR UPDATE
    ");

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param("s", $merchantOrderId);
    $stmt->execute();

    $transaksi = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$transaksi) {

        $conn->rollback();

        error_log(
            'Duitku: Transaksi tidak ditemukan: ' .
            $merchantOrderId
        );

        http_response_code(404);
        exit('Transaction Not Found');
    }

    // =====================================
    // COCOKKAN NOMINAL
    // =====================================

    $totalDatabase = (float)$transaksi['total_belanja'];

    if (abs($totalDatabase - (float)$amount) > 0.001) {

        $conn->rollback();

        error_log(
            'Duitku: Nominal tidak cocok untuk order ' .
            $merchantOrderId
        );

        http_response_code(400);
        exit('Amount Mismatch');
    }

    // =====================================
    // COCOKKAN REFERENCE
    // =====================================

    $referenceDatabase = trim(
        $transaksi['duitku_reference'] ?? ''
    );

    if (
        $referenceDatabase !== '' &&
        !hash_equals($referenceDatabase, $reference)
    ) {

        $conn->rollback();

        error_log(
            'Duitku: Reference tidak cocok untuk order ' .
            $merchantOrderId
        );

        http_response_code(400);
        exit('Reference Mismatch');
    }

    // =====================================
    // PEMBAYARAN BERHASIL
    // =====================================

    if ($resultCode === '00') {

        $idTransaksi = (int)$transaksi['id_transaksi'];

        /*
         * NULLIF mencegah paymentCode kosong
         * menimpa kode metode yang sudah tersimpan.
         *
         * Callback berulang tetap bisa memperbarui
         * kode metode jika sebelumnya belum tersimpan.
         */

        $stmtUpdate = $conn->prepare("
            UPDATE tbl_transaksi
            SET
                status_pembayaran = 'lunas',
                duitku_reference = ?,
                duitku_payment_code = COALESCE(
                    NULLIF(?, ''),
                    duitku_payment_code
                )
            WHERE id_transaksi = ?
              AND merchant_order_id = ?
              AND metode_pembayaran = 'DUITKU'
        ");

        if (!$stmtUpdate) {
            throw new Exception($conn->error);
        }

        $stmtUpdate->bind_param(
            "ssis",
            $reference,
            $paymentCode,
            $idTransaksi,
            $merchantOrderId
        );

        if (!$stmtUpdate->execute()) {
            throw new Exception($stmtUpdate->error);
        }

        $stmtUpdate->close();

        error_log(
            'Duitku: Pembayaran LUNAS. ' .
            'Transaksi: ' . $idTransaksi .
            ', Payment Code: ' . $paymentCode
        );

    } else {

        // Jangan mengubah status menjadi lunas
        // jika pembayaran belum berhasil.

        error_log(
            'Duitku: Pembayaran belum berhasil. ' .
            'Order: ' . $merchantOrderId .
            ', Result Code: ' . $resultCode
        );
    }

    // =====================================
    // SIMPAN PERUBAHAN
    // =====================================

    $conn->commit();

    http_response_code(200);
    exit('SUCCESS');

} catch (Throwable $e) {

    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
        error_log(
            'Duitku rollback error: ' .
            $rollbackError->getMessage()
        );
    }

    error_log(
        'Duitku callback error: ' .
        $e->getMessage()
    );

    http_response_code(500);
    exit('Internal Server Error');
}
