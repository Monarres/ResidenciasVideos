<?php
session_start();
require_once("../conexion.php");

header('Content-Type: application/json');

// Seguridad
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$id_video = $_POST['id_video'] ?? null;
$titulo = trim($_POST['titulo'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$preguntas = json_decode($_POST['preguntas'] ?? '[]', true);

if (!$id_video || $titulo === '') {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    $pdo->beginTransaction();

    // ✅ Actualizar el título y descripción del video
    $stmt = $pdo->prepare("UPDATE videos SET titulo = ?, descripcion = ? WHERE id_video = ?");
    $stmt->execute([$titulo, $descripcion, $id_video]);

    // ✅ Actualizar las preguntas si existen
    if (!empty($preguntas)) {
        foreach ($preguntas as $p) {
            $id_cuestionario = $p['id'];
            $pregunta = $p['pregunta'];

            if (isset($p['opcion_a'])) {
                // Pregunta tipo incisos
                $stmt = $pdo->prepare("
                    UPDATE cuestionarios 
                    SET pregunta = ?, opcion_a = ?, opcion_b = ?, opcion_c = ?, respuesta_correcta = ?
                    WHERE id_cuestionario = ? AND id_video = ?
                ");
                $stmt->execute([
                    $pregunta,
                    $p['opcion_a'],
                    $p['opcion_b'],
                    $p['opcion_c'],
                    $p['respuesta_correcta'],
                    $id_cuestionario,
                    $id_video
                ]);
            } else {
                // Pregunta tipo archivo
                $stmt = $pdo->prepare("
                    UPDATE cuestionarios 
                    SET pregunta = ?
                    WHERE id_cuestionario = ? AND id_video = ?
                ");
                $stmt->execute([$pregunta, $id_cuestionario, $id_video]);
            }
        }
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Video y cuestionario actualizados correctamente']);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
