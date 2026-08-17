<?php
session_start();

if (isset($_SESSION['user'])) {
    if ($_SESSION['user']['role'] == 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: pelayan/menu.php");
    }
} else {
    header("Location: auth/login.php");
}
?>