<?php

$host     = "gateway01.ap-southeast-1.prod.aws.tidbcloud.com"; 
$user     = "2zXScFbXaRUvTgy.root"; 
$pass     = "SgzJutF5d9jOGZUL";           
$dbname   = "MauFood"; 
$port     = 4000;

$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);

if (!mysqli_real_connect($conn, $host, $user, $pass, $dbname, $port, NULL, MYSQLI_CLIENT_SSL)) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>