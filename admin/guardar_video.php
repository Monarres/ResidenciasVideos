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
    error_log("Iniciando guardado de video de YouTube");
    
    // Validar datos recibidos
    if (!isset($_POST['id_carpeta']) || !isset($_POST['titulo']) || !isset($_POST['url_youtube'])) {
        throw new Exception('Datos incompletos: faltan campos obligatorios');
    }

    $id_carpeta = intval($_POST['id_carpeta']);
    $titulo = trim($_POST['titulo']);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $url_youtube = trim($_POST['url_youtube']);
$instrucciones_cuestionario = trim($_POST['instrucciones_cuestionario'] ?? '');

// DEBUG
error_log("=== DEBUG URL ===");
error_log("Datos recibidos - Carpeta: $id_carpeta, Título: $titulo");
error_log("URL recibida: '" . $url_youtube . "'");
error_log("Longitud URL: " . strlen($url_youtube));
error_log("Contiene youtube.com: " . (strpos($url_youtube, 'youtube.com') !== false ? 'SI' : 'NO'));
error_log("Contiene youtu.be: " . (strpos($url_youtube, 'youtu.be') !== false ? 'SI' : 'NO'));

// Probar extracción
$video_id_test = extraerIdYoutube($url_youtube);
error_log("Video ID extraído: " . ($video_id_test ? $video_id_test : "NULL/FALSE"));
error_log("=================");

    // Validar título
    if (empty($titulo)) {
        throw new Exception('El título es obligatorio');
    }

    // Validar URL de YouTube
    if (empty($url_youtube)) {
        throw new Exception('La URL de YouTube es obligatoria');
    }

    // Extraer ID del video de YouTube y convertir a formato embed
    $video_id = extraerIdYoutube($url_youtube);
    if (!$video_id) {
        throw new Exception('URL de YouTube inválida. Usa formatos como: https://www.youtube.com/watch?v=... o https://youtu.be/...');
    }

    // Convertir a URL embed
    $url_embed = "https://www.youtube.com/embed/" . $video_id;
    
    error_log("Video ID extraído: $video_id, URL embed: $url_embed");

    // Iniciar transacción
    $pdo->beginTransaction();

    try {
        // Insertar video con URL de YouTube
        $stmt = $pdo->prepare("INSERT INTO videos (id_carpeta, titulo, descripcion, ruta, tipo_video, instrucciones_cuestionario, fecha_creacion) 
                              VALUES (?, ?, ?, ?, 'youtube', ?, NOW())");
        $stmt->execute([$id_carpeta, $titulo, $descripcion, $url_embed, $instrucciones_cuestionario]);
        $id_video = $pdo->lastInsertId();

        error_log("Video insertado con ID: $id_video");

        // Procesar preguntas del cuestionario
        if (isset($_POST['preguntas'])) {
            $preguntas_json = $_POST['preguntas'];
            error_log("Preguntas recibidas: $preguntas_json");
            
            $preguntas = json_decode($preguntas_json, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Error al decodificar las preguntas: ' . json_last_error_msg());
            }
            
            if (!is_array($preguntas)) {
                throw new Exception('Formato de preguntas inválido');
            }

            if (count($preguntas) === 0) {
                throw new Exception('Debes agregar al menos una pregunta');
            }

            error_log("Procesando " . count($preguntas) . " preguntas");

            foreach ($preguntas as $index => $p) {
                if (!isset($p['tipo'])) {
                    throw new Exception("Tipo de pregunta no especificado en pregunta " . ($index + 1));
                }

                if ($p['tipo'] === 'incisos') {
                    // Validar campos obligatorios
                    if (empty($p['pregunta'])) {
                        throw new Exception("La pregunta " . ($index + 1) . " está vacía");
                    }
                    
                    // Validar opciones dinámicas
                    if (!isset($p['opciones']) || !is_array($p['opciones']) || count($p['opciones']) < 2) {
                        throw new Exception("La pregunta " . ($index + 1) . " debe tener al menos 2 opciones");
                    }
                    
                    if (empty($p['respuesta_correcta'])) {
                        throw new Exception("La pregunta " . ($index + 1) . " no tiene respuesta correcta");
                    }
                    
                    // Validar que la respuesta correcta exista en las opciones
                    if (!isset($p['opciones'][$p['respuesta_correcta']])) {
                        throw new Exception("La respuesta correcta de la pregunta " . ($index + 1) . " no corresponde a ninguna opción");
                    }

                    // Guardar opciones como JSON
                    $opciones_json = json_encode($p['opciones'], JSON_UNESCAPED_UNICODE);
    
                    $stmt = $pdo->prepare("INSERT INTO cuestionarios 
                        (id_video, tipo_pregunta, pregunta, opciones_json, respuesta_correcta) 
                        VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $id_video, 
                        'incisos', 
                        $p['pregunta'],
                        $opciones_json,
                        $p['respuesta_correcta']
                    ]);

                    error_log("Pregunta incisos " . ($index + 1) . " guardada con " . count($p['opciones']) . " opciones");

                } elseif ($p['tipo'] === 'archivo') {
                    // Validar campos obligatorios
                    if (empty($p['pregunta'])) {
                        throw new Exception("La pregunta " . ($index + 1) . " está vacía");
                    }
                    if (empty($p['instrucciones'])) {
                        throw new Exception("La pregunta " . ($index + 1) . " no tiene instrucciones");
                    }

                    $stmt = $pdo->prepare("INSERT INTO cuestionarios 
                        (id_video, tipo_pregunta, pregunta, instrucciones_archivo) 
                        VALUES (?, 'archivo', ?, ?)");
                    
                    $stmt->execute([
                        $id_video,
                        $p['pregunta'],
                        $p['instrucciones']
                    ]);

                    error_log("Pregunta archivo " . ($index + 1) . " guardada");
                    
                } else {
                    throw new Exception("Tipo de pregunta no válido en pregunta " . ($index + 1));
                }
            }
        }

        // Confirmar transacción
        $pdo->commit();
        
        error_log("Transacción completada exitosamente");

        echo json_encode([
            'success' => true,
            'message' => 'Video de YouTube y cuestionario guardados correctamente',
            'id_video' => $id_video
        ]);

    } catch (Exception $e) {
        // Revertir transacción
        $pdo->rollBack();
        
        error_log("Error en transacción: " . $e->getMessage());
        
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error general: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Extrae el ID del video de YouTube de diferentes formatos de URL
 * Soporta:
 * - https://www.youtube.com/watch?v=VIDEO_ID
 * - https://youtu.be/VIDEO_ID
 * - https://www.youtube.com/embed/VIDEO_ID
 */
function extraerIdYoutube($url) {
    $patron = '/(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/';
    
    if (preg_match($patron, $url, $coincidencias)) {
        return $coincidencias[1];
    }
    
    return false;
}
?>