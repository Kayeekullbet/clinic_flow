<?php
$host = 'localhost';
$db   = 'clinicflow_db';   // <‑ persis seperti di phpMyAdmin
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

    date_default_timezone_set('Asia/Makassar'); // atau 'Asia/Jakarta' kalau WIB

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

$pdo = new PDO($dsn, $user, $pass, $options);

$JWT_SECRET = 'ganti_dengan_kunci_rahasia_yang_panjang';