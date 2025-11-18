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
    // Obtener módulos donde el usuario tiene respuestas
    $sql = "SELECT DISTINCT 
                c.id_carpeta,
                c.nombre,
                (SELECT COUNT(*) 
                 FROM respuestas_usuario ru
                 JOIN cuestionarios q ON ru.id_cuestionario = q.id_cuestionario
                 JOIN videos v ON q.id_video = v.id_video
                 WHERE ru.id_usuario = ? AND v.id_carpeta = c.id_carpeta
                ) as total_incisos,
                (SELECT COUNT(*) 
                 FROM respuestas_archivo ra
                 JOIN cuestionarios q ON ra.id_cuestionario = q.id_cuestionario
                 JOIN videos v ON q.id_video = v.id_video
                 WHERE ra.id_usuario = ? AND v.id_carpeta = c.id_carpeta
                ) as total_archivos
            FROM carpetas c
            WHERE c.id_padre IS NOT NULL
            HAVING (total_incisos > 0 OR total_archivos > 0)
            ORDER BY c.nombre ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_usuario, $id_usuario]);
    $modulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular total de respuestas por módulo
    foreach ($modulos as &$modulo) {
        $modulo['total_respuestas'] = $modulo['total_incisos'] + $modulo['total_archivos'];
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'modulos' => $modulos
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>