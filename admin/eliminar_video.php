<?php
session_start();
require_once("../conexion.php");

header('Content-Type: application/json');

// Verificar admin
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $id_video = $_POST['id'] ?? null;
    $id_carpeta = $_POST['carpeta'] ?? null;

    if (!$id_video) {
        throw new Exception('ID de video no especificado');
    }

    // Obtener datos del video antes de eliminar
    $stmt = $pdo->prepare("SELECT * FROM videos WHERE id_video = ?");
    $stmt->execute([$id_video]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$video) {
        throw new Exception('Video no encontrado');
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    try {
        // 1. Obtener IDs de cuestionarios del video
        $stmt = $pdo->prepare("SELECT id_cuestionario FROM cuestionarios WHERE id_video = ?");
        $stmt->execute([$id_video]);
        $cuestionarios = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 2. Eliminar respuestas de archivo asociadas a los cuestionarios
        if (!empty($cuestionarios)) {
            $placeholders = implode(',', array_fill(0, count($cuestionarios), '?'));
            
            // Obtener archivos para eliminarlos del servidor
            $stmt = $pdo->prepare("SELECT ruta_archivo FROM respuestas_archivo WHERE id_cuestionario IN ($placeholders)");
            $stmt->execute($cuestionarios);
            $archivos = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Eliminar archivos físicos del servidor
            foreach ($archivos as $archivo) {
                if ($archivo && file_exists("../" . $archivo)) {
                    unlink("../" . $archivo);
                }
            }
            
            // Eliminar registros de respuestas_archivo
            $stmt = $pdo->prepare("DELETE FROM respuestas_archivo WHERE id_cuestionario IN ($placeholders)");
            $stmt->execute($cuestionarios);
        }

        // 3. Eliminar cuestionarios del video
        $stmt = $pdo->prepare("DELETE FROM cuestionarios WHERE id_video = ?");
        $stmt->execute([$id_video]);

        // 4. Eliminar archivo de video del servidor (solo si es local)
        if (isset($video['tipo_video']) && $video['tipo_video'] === 'local' && !empty($video['ruta'])) {
            $ruta_completa = "../" . $video['ruta'];
            if (file_exists($ruta_completa)) {
                unlink($ruta_completa);
            }
        }

        // 5. Eliminar registro del video
        $stmt = $pdo->prepare("DELETE FROM videos WHERE id_video = ?");
        $stmt->execute([$id_video]);

        // Confirmar transacción
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Video eliminado correctamente'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error en eliminar_video.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>