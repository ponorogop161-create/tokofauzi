
<?php
require_once "config/auth.php";

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

switch ($_SESSION['role']) {
    case 'admin':
        header("Location: admin/index.php");
        break;

    case 'kasir':
        header("Location: kasir/index.php");
        break;

    case 'user':
        header("Location: user/index.php");
        break;

    default:
        session_destroy();
        header("Location: login.php");
        break;
}
exit;
