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
    if (!isset($_POST['id_video'])) {
        throw new Exception('ID de video no especificado');
    }

    $id_video = intval($_POST['id_video']);
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $instrucciones_cuestionario = trim($_POST['instrucciones_cuestionario'] ?? '');

    if (empty($titulo)) {
        throw new Exception('El título es obligatorio');
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    try {
        // Actualizar datos del video CON instrucciones del cuestionario
        $stmt = $pdo->prepare("UPDATE videos SET titulo = ?, descripcion = ?, instrucciones_cuestionario = ? WHERE id_video = ?");
        $stmt->execute([$titulo, $descripcion, $instrucciones_cuestionario, $id_video]);

        // Procesar preguntas eliminadas
        if (isset($_POST['preguntas_eliminadas'])) {
            $eliminadas = json_decode($_POST['preguntas_eliminadas'], true);
            if (is_array($eliminadas) && count($eliminadas) > 0) {
                $placeholders = implode(',', array_fill(0, count($eliminadas), '?'));
                $stmt = $pdo->prepare("DELETE FROM cuestionarios WHERE id_cuestionario IN ($placeholders)");
                $stmt->execute($eliminadas);
            }
        }

        // Procesar preguntas actualizadas
        if (isset($_POST['preguntas_actualizadas'])) {
            $actualizadas = json_decode($_POST['preguntas_actualizadas'], true);
            if (is_array($actualizadas)) {
                foreach ($actualizadas as $p) {
                    if (!isset($p['id']) || !isset($p['tipo'])) {
                        continue;
                    }

                    if ($p['tipo'] === 'incisos') {
                        // NUEVO: Validar opciones dinámicas
                        if (empty($p['pregunta']) || !isset($p['opciones']) || !is_array($p['opciones']) || 
                            count($p['opciones']) < 2 || empty($p['respuesta_correcta'])) {
                            throw new Exception('Pregunta incompleta o con opciones inválidas');
                        }

                        // Convertir opciones a JSON
                        $opciones_json = json_encode($p['opciones'], JSON_UNESCAPED_UNICODE);

                        $stmt = $pdo->prepare("UPDATE cuestionarios SET 
                            pregunta = ?, opciones_json = ?, respuesta_correcta = ?
                            WHERE id_cuestionario = ?");
                        
                        $stmt->execute([
                            $p['pregunta'],
                            $opciones_json,
                            $p['respuesta_correcta'],
                            $p['id']
                        ]);

                    } elseif ($p['tipo'] === 'archivo') {
                        if (empty($p['pregunta']) || empty($p['instrucciones'])) {
                            throw new Exception('Pregunta de archivo incompleta');
                        }

                        $stmt = $pdo->prepare("UPDATE cuestionarios SET 
                            pregunta = ?, instrucciones_archivo = ?
                            WHERE id_cuestionario = ?");
                        
                        $stmt->execute([
                            $p['pregunta'],
                            $p['instrucciones'],
                            $p['id']
                        ]);
                    }
                }
            }
        }

        // Procesar preguntas nuevas
        if (isset($_POST['preguntas_nuevas'])) {
            $nuevas = json_decode($_POST['preguntas_nuevas'], true);
            if (is_array($nuevas)) {
                foreach ($nuevas as $p) {
                    if (!isset($p['tipo'])) {
                        continue;
                    }

                    if ($p['tipo'] === 'incisos') {
                        // NUEVO: Validar opciones dinámicas
                        if (empty($p['pregunta']) || !isset($p['opciones']) || !is_array($p['opciones']) || 
                            count($p['opciones']) < 2 || empty($p['respuesta_correcta'])) {
                            throw new Exception('Pregunta nueva incompleta o con opciones inválidas');
                        }

                        // Convertir opciones a JSON
                        $opciones_json = json_encode($p['opciones'], JSON_UNESCAPED_UNICODE);

                        $stmt = $pdo->prepare("INSERT INTO cuestionarios 
                            (id_video, tipo_pregunta, pregunta, opciones_json, respuesta_correcta) 
                            VALUES (?, 'incisos', ?, ?, ?)");
                        
                        $stmt->execute([
                            $id_video,
                            $p['pregunta'],
                            $opciones_json,
                            $p['respuesta_correcta']
                        ]);

                    } elseif ($p['tipo'] === 'archivo') {
                        if (empty($p['pregunta']) || empty($p['instrucciones'])) {
                            throw new Exception('Pregunta de archivo nueva incompleta');
                        }

                        $stmt = $pdo->prepare("INSERT INTO cuestionarios 
                            (id_video, tipo_pregunta, pregunta, instrucciones_archivo) 
                            VALUES (?, 'archivo', ?, ?)");
                        
                        $stmt->execute([
                            $id_video,
                            $p['pregunta'],
                            $p['instrucciones']
                        ]);
                    }
                }
            }
        }

        // Confirmar transacción
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Video actualizado correctamente'
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