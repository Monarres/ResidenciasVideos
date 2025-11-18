<?php
session_start();
header('Content-Type: application/json');
require_once("../conexion.php");

// Verificar admin
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    if (!isset($_GET['id'])) {
        throw new Exception('ID de video no especificado');
    }

    $id_video = intval($_GET['id']);

    // Obtener datos del video
    $stmt = $pdo->prepare("SELECT * FROM videos WHERE id_video = ?");
    $stmt->execute([$id_video]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$video) {
        throw new Exception('Video no encontrado');
    }

    // Obtener preguntas
    $stmt = $pdo->prepare("SELECT * FROM cuestionarios WHERE id_video = ? ORDER BY id_cuestionario ASC");
    $stmt->execute([$id_video]);
    $preguntas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'video' => $video,
        'preguntas' => $preguntas
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>