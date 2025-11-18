<?php
session_start();
require_once("../conexion.php");

// Verificar que sea admin
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$id_usuario = $_GET['id_usuario'] ?? null;

if (!$id_usuario) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de usuario no proporcionado']);
    exit;
}

try {
    // Contar respuestas de cuestionarios
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM respuestas_usuario WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    $total_incisos = $stmt->fetchColumn();
    
    // Contar respuestas de archivos
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM respuestas_archivo WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    $total_archivos = $stmt->fetchColumn();
    
    $tiene_respuestas = ($total_incisos > 0 || $total_archivos > 0);
    
    header('Content-Type: application/json');
    echo json_encode([
        'tiene_respuestas' => $tiene_respuestas,
        'total_incisos' => (int)$total_incisos,
        'total_archivos' => (int)$total_archivos
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en la base de datos']);
}
?>
