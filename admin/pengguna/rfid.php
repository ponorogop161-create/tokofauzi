
<?php
require_once "../../config/koneksi.php";
require_once "../../config/auth.php";

wajib_admin();

$judul = "Pengaturan Kartu RFID";

$error = '';
$pesan = '';

if (empty($_SESSION['csrf_rfid'])) {
    $_SESSION['csrf_rfid'] = bin2hex(random_bytes(32));
}

function normalisasi_uid_rfid($uid)
{
    $uid = strtoupper(trim($uid));
    return preg_replace('/[\s:-]/', '', $uid);
}

// =====================================
// PROSES PENDAFTARAN / PENGHAPUSAN RFID
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_rfid'], $token)) {

        $error = "Permintaan tidak valid. Silakan muat ulang halaman.";

    } else {

        $aksi = $_POST['aksi'] ?? '';
        $id_user = (int)($_POST['id_user'] ?? 0);

        if ($id_user <= 0) {

            $error = "Akun pengguna tidak valid.";

        } elseif ($aksi === 'simpan') {

            $rfid_uid = normalisasi_uid_rfid(
                $_POST['rfid_uid'] ?? ''
            );

            if (!preg_match('/^[A-Z0-9]{4,40}$/', $rfid_uid)) {

                $error = "UID RFID harus terdiri dari 4 sampai 40 karakter huruf dan angka.";

            } else {

                // Pastikan akun tersedia
                $cekUser = $conn->prepare("
                    SELECT id_user
                    FROM tbl_users
                    WHERE id_user = ?
                    LIMIT 1
                ");

                $cekUser->bind_param("i", $id_user);
                $cekUser->execute();

                $userAda = $cekUser->get_result()->num_rows > 0;
                $cekUser->close();

                if (!$userAda) {

                    $error = "Akun pengguna tidak ditemukan.";

                } else {

                    // Cek apakah kartu sudah dipakai akun lain
                    $cekRFID = $conn->prepare("
                        SELECT id_user
                        FROM tbl_users
                        WHERE UPPER(rfid_uid) = ?
                        AND id_user <> ?
                        LIMIT 1
                    ");

                    $cekRFID->bind_param(
                        "si",
                        $rfid_uid,
                        $id_user
                    );

                    $cekRFID->execute();

                    $sudahDipakai = $cekRFID->get_result()->num_rows > 0;
                    $cekRFID->close();

                    if ($sudahDipakai) {

                        $error = "Kartu RFID sudah terdaftar pada akun lain.";

                    } else {

                        // Simpan UID ke akun
                        $stmt = $conn->prepare("
                            UPDATE tbl_users
                            SET rfid_uid = ?
                            WHERE id_user = ?
                        ");

                        $stmt->bind_param(
                            "si",
                            $rfid_uid,
                            $id_user
                        );

                        if ($stmt->execute()) {

                            if (function_exists('catat_log')) {
                                catat_log(
                                    $conn,
                                    "Pengaturan RFID",
                                    "Admin menghubungkan kartu RFID "
                                    . $rfid_uid
                                    . " ke akun ID "
                                    . $id_user
                                );
                            }

                            $stmt->close();

                            header(
                                "Location: rfid.php?pesan="
                                . urlencode("Kartu RFID berhasil didaftarkan.")
                            );
                            exit;

                        } else {

                            $error = "Gagal menyimpan kartu RFID: " . $stmt->error;
                            $stmt->close();

                        }
                    }
                }
            }

        } elseif ($aksi === 'hapus') {

            // Lepaskan kartu dari akun
            $stmt = $conn->prepare("
                UPDATE tbl_users
                SET rfid_uid = NULL
                WHERE id_user = ?
            ");

            $stmt->bind_param("i", $id_user);

            if ($stmt->execute()) {

                if (function_exists('catat_log')) {
                    catat_log(
                        $conn,
                        "Pengaturan RFID",
                        "Admin menghapus kartu RFID dari akun ID " . $id_user
                    );
                }

                $stmt->close();

                header(
                    "Location: rfid.php?pesan="
                    . urlencode("Kartu RFID berhasil dilepaskan dari akun.")
                );
                exit;

            } else {

                $error = "Gagal menghapus kartu RFID.";
                $stmt->close();

            }

        } else {

            $error = "Aksi tidak dikenali.";

        }
    }
}

// =====================================
// PESAN NOTIFIKASI
// =====================================

if (isset($_GET['pesan'])) {
    $pesan = trim($_GET['pesan']);
}

// =====================================
// AMBIL DATA PENGGUNA
// =====================================

$users = $conn->query("
    SELECT
        id_user,
        nama,
        username,
        role,
        rfid_uid
    FROM tbl_users
    ORDER BY
        FIELD(role, 'admin', 'kasir', 'user'),
        nama ASC
");

require_once "../header.php";
?>

<div class="page-header">

    <div>
        <h1>Pengaturan Kartu RFID</h1>
        <p class="muted">
            Daftarkan dan kelola kartu RFID untuk seluruh akun TokoKu.
        </p>
    </div>

    <a href="index.php" class="btn btn-secondary">
        Kembali
    </a>

</div>

<div class="panel">

    <div class="card-header">
        <div>
            <h3>Daftarkan Kartu RFID</h3>
            <p class="muted">
                Pilih akun, lalu tempelkan kartu ke reader USB.
            </p>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($pesan !== ''): ?>
        <div class="alert alert-success">
            <?= e($pesan) ?>
        </div>
    <?php endif; ?>

    <form method="POST" style="margin-bottom:25px;">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e($_SESSION['csrf_rfid']) ?>"
        >

        <input type="hidden" name="aksi" value="simpan">

        <div class="form-group">

            <label for="id_user">
                Pilih Akun Pengguna
            </label>

            <select
                name="id_user"
                id="id_user"
                required
            >

                <option value="">
                    -- Pilih Akun --
                </option>

                <?php
                $users->data_seek(0);
                while ($user = $users->fetch_assoc()):
                ?>

                    <option value="<?= (int)$user['id_user'] ?>">
                        <?= e($user['nama']) ?>
                        (<?= e(ucfirst($user['role'])) ?> -
                        <?= e($user['username']) ?>)
                    </option>

                <?php endwhile; ?>

            </select>

        </div>

        <div class="form-group">

            <label for="rfid_uid">
                UID Kartu RFID
            </label>

            <input
                type="text"
                name="rfid_uid"
                id="rfid_uid"
                maxlength="40"
                pattern="[A-Za-z0-9]{4,40}"
                placeholder="Tempelkan kartu RFID..."
                autocomplete="off"
                required
            >

            <small class="muted">
                Tempelkan kartu pada reader USB. UID akan terisi otomatis
                jika reader berfungsi seperti keyboard.
            </small>

        </div>

        <button type="submit" class="btn btn-primary">
            Simpan Kartu RFID
        </button>

    </form>

</div>

<div class="panel">

    <div class="card-header">
        <div>
            <h3>Daftar Kartu RFID Pengguna</h3>
            <p class="muted">
                Daftar akun dan status pendaftaran kartu RFID.
            </p>
        </div>
    </div>

    <div class="table-responsive">

        <table>

            <thead>
                <tr>
                    <th>No.</th>
                    <th>Nama</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>UID RFID</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>

                <?php
                $users->data_seek(0);
                $no = 1;
                ?>

                <?php while ($row = $users->fetch_assoc()): ?>

                    <tr>

                        <td><?= $no++ ?></td>

                        <td>
                            <strong><?= e($row['nama']) ?></strong>
                        </td>

                        <td><?= e($row['username']) ?></td>

                        <td>
                            <?= e(ucfirst($row['role'])) ?>
                        </td>

                        <td>
                            <?php if (!empty($row['rfid_uid'])): ?>
                                <code><?= e($row['rfid_uid']) ?></code>
                            <?php else: ?>
                                <span class="muted">Belum terdaftar</span>
                            <?php endif; ?>
                        </td>

                        <td>

                            <?php if (!empty($row['rfid_uid'])): ?>

                                <span class="badge badge-success">
                                    Terdaftar
                                </span>

                            <?php else: ?>

                                <span class="badge badge-danger">
                                    Belum Terdaftar
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <?php if (!empty($row['rfid_uid'])): ?>

                                <form
                                    method="POST"
                                    onsubmit="return confirm('Yakin ingin melepaskan kartu RFID dari akun ini?')"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($_SESSION['csrf_rfid']) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="aksi"
                                        value="hapus"
                                    >

                                    <input
                                        type="hidden"
                                        name="id_user"
                                        value="<?= (int)$row['id_user'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-danger"
                                    >
                                        Hapus RFID
                                    </button>

                                </form>

                            <?php else: ?>

                                <span class="muted">Belum ada kartu</span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endwhile; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
