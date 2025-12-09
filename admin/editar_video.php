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

try {
    $id_video = $_POST['id_video'] ?? null;
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $url_youtube = trim($_POST['url_youtube'] ?? '');
    $instrucciones_cuestionario = trim($_POST['instrucciones_cuestionario'] ?? '');

    if (!$id_video || $titulo === '') {
        throw new Exception('Datos incompletos: título es obligatorio');
    }

    error_log("=== EDITANDO VIDEO ===");
    error_log("ID Video: $id_video");
    error_log("Título: $titulo");
    error_log("URL YouTube: $url_youtube");

    $pdo->beginTransaction();

    // Actualizar video (con o sin nueva URL de YouTube)
    if (!empty($url_youtube)) {
        // Extraer ID de YouTube y convertir a embed
        $video_id = extraerIdYoutube($url_youtube);
        if (!$video_id) {
            throw new Exception('URL de YouTube inválida');
        }
        $url_embed = "https://www.youtube.com/embed/" . $video_id;
        
        $stmt = $pdo->prepare("UPDATE videos SET titulo = ?, descripcion = ?, ruta = ?, tipo_video = 'youtube', instrucciones_cuestionario = ? WHERE id_video = ?");
        $stmt->execute([$titulo, $descripcion, $url_embed, $instrucciones_cuestionario, $id_video]);
        
        error_log("Video actualizado con nueva URL: $url_embed");
    } else {
        $stmt = $pdo->prepare("UPDATE videos SET titulo = ?, descripcion = ?, instrucciones_cuestionario = ? WHERE id_video = ?");
        $stmt->execute([$titulo, $descripcion, $instrucciones_cuestionario, $id_video]);
        
        error_log("Video actualizado sin cambiar URL");
    }

    // Procesar preguntas eliminadas
    if (isset($_POST['preguntas_eliminadas'])) {
        $eliminadas = json_decode($_POST['preguntas_eliminadas'], true);
        if (is_array($eliminadas) && count($eliminadas) > 0) {
            $placeholders = implode(',', array_fill(0, count($eliminadas), '?'));
            $stmt = $pdo->prepare("DELETE FROM cuestionarios WHERE id_cuestionario IN ($placeholders)");
            $stmt->execute($eliminadas);
            
            error_log("Preguntas eliminadas: " . count($eliminadas));
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
                    if (empty($p['pregunta']) || !isset($p['opciones']) || !is_array($p['opciones']) || 
                        count($p['opciones']) < 2 || empty($p['respuesta_correcta'])) {
                        throw new Exception('Pregunta incompleta o con opciones inválidas');
                    }

                    $opciones_json = json_encode($p['opciones'], JSON_UNESCAPED_UNICODE);

                    $stmt = $pdo->prepare("UPDATE cuestionarios SET 
                        pregunta = ?, tipo_pregunta = 'incisos', opciones_json = ?, respuesta_correcta = ?, instrucciones_archivo = NULL
                        WHERE id_cuestionario = ?");
                    
                    $stmt->execute([
                        $p['pregunta'],
                        $opciones_json,
                        $p['respuesta_correcta'],
                        $p['id']
                    ]);
                    
                    error_log("Pregunta incisos actualizada ID: " . $p['id']);

                } elseif ($p['tipo'] === 'archivo') {
                    if (empty($p['pregunta']) || empty($p['instrucciones'])) {
                        throw new Exception('Pregunta de archivo incompleta');
                    }

                    $stmt = $pdo->prepare("UPDATE cuestionarios SET 
                        pregunta = ?, tipo_pregunta = 'archivo', opciones_json = NULL, respuesta_correcta = NULL, instrucciones_archivo = ?
                        WHERE id_cuestionario = ?");
                    
                    $stmt->execute([
                        $p['pregunta'],
                        $p['instrucciones'],
                        $p['id']
                    ]);
                    
                    error_log("Pregunta archivo actualizada ID: " . $p['id']);
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
                    if (empty($p['pregunta']) || !isset($p['opciones']) || !is_array($p['opciones']) || 
                        count($p['opciones']) < 2 || empty($p['respuesta_correcta'])) {
                        throw new Exception('Pregunta nueva incompleta o con opciones inválidas');
                    }

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
                    
                    error_log("Pregunta incisos nueva agregada");

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
                    
                    error_log("Pregunta archivo nueva agregada");
                }
            }
        }
    }

    $pdo->commit();
    
    error_log("✅ Transacción completada exitosamente");

    echo json_encode([
        'success' => true,
        'message' => 'Video actualizado correctamente'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("❌ Error en editar_video.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}

/**
 * Extrae el ID del video de YouTube de diferentes formatos de URL
 */
function extraerIdYoutube($url) {
    $patron = '/(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/';
    
    if (preg_match($patron, $url, $coincidencias)) {
        return $coincidencias[1];
    }
    
    return false;
}
?>