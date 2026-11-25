
<?php
require_once "config/auth.php";

// Jika sudah login, arahkan ke dashboard
if (isset($_SESSION['id_user'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$error = '';
$rfid_uid = '';

// =====================================
// PROSES LOGIN RFID
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $rfid_uid = strtoupper(trim($_POST['rfid_uid'] ?? ''));

    // Hilangkan spasi, titik dua, dan tanda hubung
    $rfid_uid = preg_replace('/[\s:-]/', '', $rfid_uid);

    if ($rfid_uid === '') {

        $error = "Silakan tempelkan kartu RFID terlebih dahulu.";

    } elseif (!preg_match('/^[A-Z0-9]{4,40}$/', $rfid_uid)) {

        $error = "UID RFID tidak valid.";

    } else {

        // Cari akun berdasarkan UID RFID
        $stmt = $conn->prepare("
            SELECT
                id_user,
                id_pelanggan,
                nama,
                username,
                role
            FROM tbl_users
            WHERE UPPER(rfid_uid) = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $rfid_uid);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc();

            // Regenerasi session untuk keamanan
            session_regenerate_id(true);

            // Simpan session sesuai sistem TokoKu
            $_SESSION['id_user'] = (int)$user['id_user'];
            $_SESSION['id_pelanggan'] = $user['id_pelanggan'];
            $_SESSION['nama'] = $user['nama'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            $stmt->close();

            // Arahkan ke dashboard sesuai role
            header("Location: " . BASE_URL . "index.php");
            exit;

        } else {

            $error = "Kartu RFID tidak terdaftar. Hubungi Admin.";

        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login RFID - TokoKu</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 35px;
            background: white;
            border-radius: 14px;
            box-shadow: 0 8px 30px rgba(0,0,0,.08);
            text-align: center;
        }

        .logo {
            width: 65px;
            height: 65px;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 15px;
            background: #2563eb;
            color: white;
            font-size: 25px;
            font-weight: bold;
        }

        h2 {
            margin: 0 0 8px;
            color: #1e293b;
        }

        .description {
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .rfid-icon {
            font-size: 55px;
            margin: 15px 0;
        }

        input {
            width: 100%;
            padding: 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
            text-align: center;
            outline: none;
        }

        input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,.12);
        }

        button {
            width: 100%;
            padding: 13px;
            margin-top: 15px;
            border: none;
            border-radius: 8px;
            background: #2563eb;
            color: white;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .alert {
            padding: 12px;
            margin-bottom: 18px;
            border-radius: 7px;
            font-size: 13px;
            text-align: left;
        }

        .alert-danger {
            color: #991b1b;
            background: #fee2e2;
        }

        .back-link {
            display: inline-block;
            margin-top: 22px;
            color: #2563eb;
            font-size: 14px;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .status {
            margin-top: 15px;
            color: #64748b;
            font-size: 12px;
        }
    </style>
</head>

<body>

    <div class="login-card">

        <div class="logo">TK</div>

        <h2>Login RFID TokoKu</h2>

        <p class="description">
            Tempelkan kartu RFID pada reader untuk masuk
            ke akun TokoKu.
        </p>

        <div class="rfid-icon">📶</div>

        <?php if ($error !== ''): ?>

            <div class="alert alert-danger">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST" id="formRFID">

            <input
                type="text"
                name="rfid_uid"
                id="rfid_uid"
                placeholder="Tempelkan kartu RFID..."
                autocomplete="off"
                autocapitalize="characters"
                spellcheck="false"
                maxlength="40"
                autofocus
                required
            >

            <button type="submit">
                Login dengan RFID
            </button>

        </form>

        <p class="status" id="status">
            Reader siap. Silakan tempelkan kartu.
        </p>

        <a href="<?= BASE_URL ?>login.php" class="back-link">
            Login menggunakan Username dan Password
        </a>

    </div>

    <script>
        const inputRFID = document.getElementById('rfid_uid');
        const statusRFID = document.getElementById('status');
        const formRFID = document.getElementById('formRFID');

        // Fokus otomatis pada kolom RFID
        window.addEventListener('load', function () {
            inputRFID.focus();
        });

        // Jika reader mengirim Enter, form langsung diproses
        inputRFID.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();

                if (inputRFID.value.trim() !== '') {
                    statusRFID.textContent = 'Memeriksa kartu RFID...';
                    formRFID.requestSubmit();
                }
            }
        });

        // Kembalikan fokus jika pengguna mengeklik area kosong
        document.addEventListener('click', function (event) {
            if (event.target.tagName !== 'A' &&
                event.target.tagName !== 'BUTTON') {
                inputRFID.focus();
            }
        });
    </script>

</body>
</html>
