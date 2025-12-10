<?php
// Copia este archivo como 'conexion.php' y edita con tus datos reales

$host = 'localhost';
$db   = 'nombre_base_datos';
$user = 'usuario'; 
$pass = 'contraseña';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log("Error conexión BD: " . $e->getMessage());
    die("Error de conexión a la base de datos");
}