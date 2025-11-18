<?php
session_start();
require_once("../conexion.php");

// Verificar que sea admin
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$id_usuario = $_POST['id_usuario'] ?? null;
$id_modulo = $_POST['id_modulo'] ?? null;

if (!$id_usuario || !$id_modulo) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos incompletos']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    $total_eliminadas = 0;
    
    // Obtener IDs de videos del módulo
    $stmt = $pdo->prepare("SELECT id_video FROM videos WHERE id_carpeta = ?");
    $stmt->execute([$id_modulo]);
    $videos = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($videos)) {
        throw new Exception('No se encontraron videos en este módulo');
    }
    
    $placeholders = implode(',', array_fill(0, count($videos), '?'));
    
    // 1. Eliminar respuestas de archivos
    // Primero obtener las rutas de los archivos
    $sql = "SELECT ra.ruta_archivo 
            FROM respuestas_archivo ra
            JOIN cuestionarios q ON ra.id_cuestionario = q.id_cuestionario
            WHERE ra.id_usuario = ? AND q.id_video IN ($placeholders)";
    $params = array_merge([$id_usuario], $videos);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $archivos = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Eliminar archivos físicos del servidor
    foreach ($archivos as $ruta) {
        if (file_exists($ruta)) {
            unlink($ruta);
        }
    }
    
    // Eliminar registros de respuestas_archivo
    $sql = "DELETE ra FROM respuestas_archivo ra
            JOIN cuestionarios q ON ra.id_cuestionario = q.id_cuestionario
            WHERE ra.id_usuario = ? AND q.id_video IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $total_eliminadas += $stmt->rowCount();
    
    // 2. Eliminar respuestas de cuestionarios (incisos)
    $sql = "DELETE ru FROM respuestas_usuario ru
            JOIN cuestionarios q ON ru.id_cuestionario = q.id_cuestionario
            WHERE ru.id_usuario = ? AND q.id_video IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $total_eliminadas += $stmt->rowCount();
    
    $pdo->commit();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'total_eliminadas' => $total_eliminadas,
        'message' => 'Respuestas eliminadas correctamente'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>