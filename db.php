<?php
$host = "localhost"; // Host que usa DOMCloud
$user = "pitiful-go-tei"; // Usuario de tu base de datos en DOMCloud
$pass = "mZ3(BdX7+m3By41Y-h"; // La contraseña que creaste en DOMCloud
$dbname = "pitiful_go_tei_basebbm"; // Nombre REAL de tu base en DOMCloud

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
?>