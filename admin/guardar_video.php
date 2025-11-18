<?php
session_start();
header('Content-Type: application/json');
require_once("../conexion.php");

// Aumentar tiempo de ejecución
set_time_limit(300); // 5 minutos
ini_set('memory_limit', '512M');

// Verificar admin
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    // Log para debug
    error_log("Iniciando subida de video");
    
    // Validar datos recibidos
    if (!isset($_POST['id_carpeta']) || !isset($_POST['titulo'])) {
        throw new Exception('Datos incompletos: faltan campos obligatorios');
    }

    if (!isset($_FILES['video']) || $_FILES['video']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception('No se recibió ningún archivo de video');
    }

    $id_carpeta = intval($_POST['id_carpeta']);
    $titulo = trim($_POST['titulo']);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $instrucciones_cuestionario = trim($_POST['instrucciones_cuestionario'] ?? '');
    
    error_log("Datos recibidos - Carpeta: $id_carpeta, Título: $titulo");

    // Validar título
    if (empty($titulo)) {
        throw new Exception('El título es obligatorio');
    }

    // Validar archivo de video
    $archivo = $_FILES['video'];
    
    error_log("Archivo recibido - Nombre: {$archivo['name']}, Tamaño: {$archivo['size']}, Error: {$archivo['error']}");
    
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        $errores = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede upload_max_filesize en php.ini',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en disco',
            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida'
        ];
        $mensaje = $errores[$archivo['error']] ?? "Error desconocido ({$archivo['error']})";
        throw new Exception("Error al subir el archivo: $mensaje");
    }

    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $extensiones_permitidas = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm'];

    if (!in_array($extension, $extensiones_permitidas)) {
        throw new Exception('Formato de video no permitido. Solo: ' . implode(', ', $extensiones_permitidas));
    }

    // Validar tamaño (500MB)
    $max_size = 500 * 1024 * 1024;
    if ($archivo['size'] > $max_size) {
        throw new Exception('El video es demasiado grande. Máximo 500MB');
    }

    // Crear directorio si no existe
    $carpeta_destino = "../uploads/videos/";
    if (!file_exists($carpeta_destino)) {
        if (!mkdir($carpeta_destino, 0755, true)) {
            throw new Exception('No se pudo crear el directorio de videos');
        }
    }

    // Verificar permisos de escritura
    if (!is_writable($carpeta_destino)) {
        throw new Exception('No hay permisos de escritura en el directorio de videos');
    }

    // Generar nombre único para el archivo
    $nombre_archivo = uniqid('video_') . '_' . time() . '.' . $extension;
    $ruta_completa = $carpeta_destino . $nombre_archivo;
    $ruta_bd = "uploads/videos/" . $nombre_archivo;

    error_log("Intentando mover archivo a: $ruta_completa");

    // Mover archivo
    if (!move_uploaded_file($archivo['tmp_name'], $ruta_completa)) {
        throw new Exception('Error al guardar el archivo de video en el servidor');
    }

    error_log("Archivo movido exitosamente");

    // Iniciar transacción
    $pdo->beginTransaction();

    try {
        // Insertar video CON instrucciones del cuestionario
        $stmt = $pdo->prepare("INSERT INTO videos (id_carpeta, titulo, descripcion, ruta, instrucciones_cuestionario) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$id_carpeta, $titulo, $descripcion, $ruta_bd, $instrucciones_cuestionario]);
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
                    
                    // NUEVO: Validar opciones dinámicas
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
            'message' => 'Video y cuestionario guardados correctamente',
            'id_video' => $id_video
        ]);

    } catch (Exception $e) {
        // Revertir transacción
        $pdo->rollBack();
        
        error_log("Error en transacción: " . $e->getMessage());
        
        // Eliminar archivo de video si se subió
        if (file_exists($ruta_completa)) {
            unlink($ruta_completa);
            error_log("Archivo de video eliminado por error en transacción");
        }
        
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error general: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>