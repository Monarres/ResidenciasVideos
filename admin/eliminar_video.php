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
    if (!isset($_POST['id'])) {
        throw new Exception('ID de video no especificado');
    }

    $id_video = intval($_POST['id']);

    // Obtener ruta del video antes de eliminarlo
    $stmt = $pdo->prepare("SELECT ruta FROM videos WHERE id_video = ?");
    $stmt->execute([$id_video]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$video) {
        throw new Exception('Video no encontrado');
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    try {
        // Eliminar preguntas asociadas
        $stmt = $pdo->prepare("DELETE FROM cuestionarios WHERE id_video = ?");
        $stmt->execute([$id_video]);

        // Eliminar video de la base de datos
        $stmt = $pdo->prepare("DELETE FROM videos WHERE id_video = ?");
        $stmt->execute([$id_video]);

        // Eliminar archivo físico del video
        if (!empty($video['ruta'])) {
            $ruta_archivo = "../" . $video['ruta'];
            if (file_exists($ruta_archivo)) {
                unlink($ruta_archivo);
            }
        }

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
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>