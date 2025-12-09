<?php
session_start();
require_once("../conexion.php");

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../index.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$id_video = $_POST['id_video'] ?? null;
$id_carpeta = $_POST['id_carpeta'] ?? null;
$respuestas = $_POST['resp'] ?? [];

if (!$id_video) {
    $_SESSION['error'] = "Datos incompletos";
    header("Location: videos_usuario.php");
    exit;
}

try {
    $errores = 0;
    $correctas = 0;
    $total = 0;
    $archivos_subidos = 0;
    
    // ==========================================
    // PROCESAR RESPUESTAS DE OPCIÓN MÚLTIPLE
    // ==========================================
    foreach ($respuestas as $id_cuestionario => $respuesta_usuario) {
        $stmt = $pdo->prepare("SELECT respuesta_correcta, tipo_pregunta FROM cuestionarios WHERE id_cuestionario = ?");
        $stmt->execute([$id_cuestionario]);
        $pregunta = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pregunta && $pregunta['tipo_pregunta'] === 'incisos') {
            $total++;
            
            // Limpiar respuesta a solo 1 carácter
            $respuesta_limpia = strtoupper(substr(trim($respuesta_usuario), 0, 1));
            $respuesta_correcta_limpia = strtoupper(substr(trim($pregunta['respuesta_correcta']), 0, 1));
            
            $es_correcta = ($respuesta_limpia === $respuesta_correcta_limpia) ? 1 : 0;
            
            if ($es_correcta) {
                $correctas++;
            } else {
                $errores++;
            }
            
            // Verificar si ya existe una respuesta
            $stmt = $pdo->prepare("SELECT id_respuesta FROM respuestas_usuario 
                                  WHERE id_usuario = ? AND id_cuestionario = ?");
            $stmt->execute([$id_usuario, $id_cuestionario]);
            $existe = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existe) {
                // Actualizar respuesta existente
                $stmt = $pdo->prepare("UPDATE respuestas_usuario 
                                      SET respuesta = ?, correcta = ?, fecha_creacion = NOW()
                                      WHERE id_usuario = ? AND id_cuestionario = ?");
                $stmt->execute([$respuesta_limpia, $es_correcta, $id_usuario, $id_cuestionario]);
            } else {
                // Insertar nueva respuesta
                $stmt = $pdo->prepare("INSERT INTO respuestas_usuario 
                                      (id_usuario, id_cuestionario, respuesta, correcta, fecha_creacion) 
                                      VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$id_usuario, $id_cuestionario, $respuesta_limpia, $es_correcta]);
            }
        }
    }
    
    // ==========================================
    // PROCESAR ARCHIVOS SUBIDOS
    // ==========================================
    if (isset($_FILES['archivo']) && !empty($_FILES['archivo'])) {
        $carpeta_uploads = "../uploads/respuestas/";
        
        // Crear carpeta si no existe
        if (!file_exists($carpeta_uploads)) {
            mkdir($carpeta_uploads, 0777, true);
        }
        
        // Detectar idioma del servidor (español o inglés)
        $key_name = isset($_FILES['archivo']['nombre']) ? 'nombre' : 'name';
        $key_tmp = 'tmp_name';
        $key_error = 'error';
        
        $archivos = $_FILES['archivo'];
        
        // Verificar si es un array de archivos múltiples
        if (is_array($archivos[$key_name])) {
            foreach ($archivos[$key_name] as $id_cuestionario => $nombre_archivo) {
                
                // Validar que el archivo se haya subido correctamente
                if (empty($nombre_archivo) || $archivos[$key_error][$id_cuestionario] !== UPLOAD_ERR_OK) {
                    continue;
                }
                
                // Verificar que sea una pregunta tipo archivo
                $stmt = $pdo->prepare("SELECT tipo_pregunta FROM cuestionarios WHERE id_cuestionario = ?");
                $stmt->execute([$id_cuestionario]);
                $pregunta = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($pregunta && $pregunta['tipo_pregunta'] === 'archivo') {
                    $total++;
                    
                    $tmp_name = $archivos[$key_tmp][$id_cuestionario];
                    $extension = pathinfo($nombre_archivo, PATHINFO_EXTENSION);
                    $nombre_unico = "usuario_{$id_usuario}_cuestionario_{$id_cuestionario}_" . time() . "." . $extension;
                    $ruta_destino = $carpeta_uploads . $nombre_unico;
                    
                    if (move_uploaded_file($tmp_name, $ruta_destino)) {
                        
                        // Verificar si ya existe una respuesta
                        $stmt = $pdo->prepare("SELECT id_respuesta_archivo, ruta_archivo FROM respuestas_archivo 
                                              WHERE id_usuario = ? AND id_cuestionario = ?");
                        $stmt->execute([$id_usuario, $id_cuestionario]);
                        $existe = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($existe) {
                            // Eliminar archivo anterior si existe
                            if (file_exists($existe['ruta_archivo'])) {
                                unlink($existe['ruta_archivo']);
                            }
                            
                            // Actualizar registro
                            $stmt = $pdo->prepare("UPDATE respuestas_archivo 
                                                  SET ruta_archivo = ?, 
                                                      fecha_subida = NOW(),
                                                      calificacion = NULL,
                                                      comentario_calificacion = NULL,
                                                      fecha_calificacion = NULL,
                                                      calificado_por = NULL
                                                  WHERE id_usuario = ? AND id_cuestionario = ?");
                            $stmt->execute([$ruta_destino, $id_usuario, $id_cuestionario]);
                        } else {
                            // Insertar nuevo registro
                            $stmt = $pdo->prepare("INSERT INTO respuestas_archivo 
                                                  (id_usuario, id_cuestionario, ruta_archivo, fecha_subida) 
                                                  VALUES (?, ?, ?, NOW())");
                            $stmt->execute([$id_usuario, $id_cuestionario, $ruta_destino]);
                        }
                        
                        $archivos_subidos++;
                    }
                }
            }
        }
    }
    
    // ==========================================
    // DETERMINAR TIPO DE RESULTADO
    // ==========================================
    $stmt_check_archivo = $pdo->prepare("SELECT COUNT(*) as tiene_archivo 
                                          FROM cuestionarios 
                                          WHERE id_video = ? AND tipo_pregunta = 'archivo'");
    $stmt_check_archivo->execute([$id_video]);
    $result_archivo = $stmt_check_archivo->fetch(PDO::FETCH_ASSOC);
    $tiene_preguntas_archivo = ($result_archivo['tiene_archivo'] > 0);

    if ($tiene_preguntas_archivo) {
        $_SESSION['resultado_cuestionario'] = [
            'tipo' => 'archivo',
            'mensaje' => 'Tu calificación será revisada',
            'archivos_subidos' => $archivos_subidos
        ];
    } else {
        $porcentaje = ($total > 0) ? round(($correctas / $total) * 100, 2) : 0;
        
        $_SESSION['resultado_cuestionario'] = [
            'tipo' => 'incisos',
            'correctas' => $correctas,
            'incorrectas' => $errores,
            'total' => $total,
            'porcentaje' => $porcentaje,
            'aprobado' => ($porcentaje >= 70)
        ];
    }
    
    // Redirigir
    if ($id_carpeta) {
        header("Location: videos_usuario.php?carpeta=" . $id_carpeta);
    } else {
        header("Location: videos_usuario.php");
    }
    exit;
    
} catch (Exception $e) {
    error_log("Error en guardar_respuestas.php: " . $e->getMessage());
    $_SESSION['error'] = "Error al guardar las respuestas: " . $e->getMessage();
    
    if ($id_carpeta) {
        header("Location: videos_usuario.php?carpeta=" . $id_carpeta);
    } else {
        header("Location: videos_usuario.php");
    }
    exit;
}
?>