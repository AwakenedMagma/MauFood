<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host     = "gateway01.ap-southeast-1.prod.aws.tidbcloud.com"; 
$user     = "2zXScFbXaRUvTgy.root"; 
$pass     = "SgzJutF5d9jOGZUL";           
$dbname   = "MauFood"; 
$port     = 4000;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = mysqli_init();
    mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);
    mysqli_real_connect($conn, $host, $user, $pass, $dbname, $port, NULL, MYSQLI_CLIENT_SSL);
} catch (mysqli_sql_exception $e) {
    die("<h3 style='color:red;'>Gagal Terhubung ke Database TiDB:</h3> <b>" . $e->getMessage() . "</b>");
}
?>
