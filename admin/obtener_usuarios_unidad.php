<?php
session_start();
require_once("../conexion.php");

// Verificar si es admin
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$id_unidad = isset($_GET['id_unidad']) ? (int)$_GET['id_unidad'] : 0;

if ($id_unidad > 0) {
    $stmt = $pdo->prepare("SELECT u.id_usuario, u.nombre, u.email, u.rol, c.nombre as area_nombre
                           FROM usuarios u
                           LEFT JOIN carpetas c ON u.id_carpeta = c.id_carpeta
                           WHERE u.id_unidad = ? AND u.rol = 'usuario'
                           ORDER BY u.nombre ASC");
    $stmt->execute([$id_unidad]);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['usuarios' => $usuarios]);
} else {
    echo json_encode(['usuarios' => []]);
}
?>