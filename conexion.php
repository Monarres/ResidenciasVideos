<?php
// conexion.php

$host = 'localhost';
$db   = 'basebbm';
$user = 'root'; 
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





//$host = 'localhost';
//$db   = 'pitiful_go_tei_basebbm';
//$user = 'pitiful-go-tei'; 
//$pass = 'mZ3(BdX7+m3By41Y-h';