<?php
// conexion.php
$host = 'mnz.domcloud.co';
$db   = 'pitiful-go-tei_basebbm';
$user = 'pitiful-go-tei';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Error conexión BD: " . $e->getMessage());
}
