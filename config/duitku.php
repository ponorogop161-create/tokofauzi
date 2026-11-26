
<?php
// Konfigurasi Duitku POP
// Jangan unggah file ini ke GitHub.

return [
    'merchant_code' => 'DS38598',

    // Isi dengan API Key BARU dari dashboard Duitku.
    'api_key' => 'ec800640d4715afbf65062e260bd0cd2',

    // true = Sandbox, false = Production.
    'sandbox' => true,

    // Duitku mensyaratkan callbackUrl saat membuat invoice.
    // Status aplikasi diverifikasi melalui transactionStatus saat pelanggan kembali.
    'callback_url' => 'https://sterida.id/fauzi/duitku/callback.php',

    // Browser pelanggan kembali ke halaman ini setelah pembayaran.
    // Sesuaikan jika TokoKu dipasang di domain atau folder lain.
    'return_url' => 'http://localhost/tokoku/user/pesanan/detail.php',

    // Masa berlaku invoice dalam menit.
    'expiry_period' => 60,
];
