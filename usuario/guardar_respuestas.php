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

// Validación básica
if (!$id_video) {
    $_SESSION['error'] = "Datos incompletos";
    header("Location: videos_usuario.php");
    exit;
}

try {
    $pdo->beginTransaction();
    
    $errores = 0;
    $correctas = 0;
    $total = 0;
    $archivos_subidos = 0;
    
    // ==========================================
    // PROCESAR RESPUESTAS DE OPCIÓN MÚLTIPLE
    // ==========================================
    foreach ($respuestas as $id_cuestionario => $respuesta_usuario) {
        // Obtener la pregunta y verificar que sea tipo "incisos"
        $stmt = $pdo->prepare("SELECT respuesta_correcta, tipo_pregunta FROM cuestionarios WHERE id_cuestionario = ?");
        $stmt->execute([$id_cuestionario]);
        $pregunta = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pregunta && $pregunta['tipo_pregunta'] === 'incisos') {
            $total++;
            // Normalizar la comparación
            $es_correcta = (strtoupper(trim($respuesta_usuario)) === strtoupper(trim($pregunta['respuesta_correcta']))) ? 1 : 0;
            
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
                $stmt->execute([$respuesta_usuario, $es_correcta, $id_usuario, $id_cuestionario]);
            } else {
                // Insertar nueva respuesta
                $stmt = $pdo->prepare("INSERT INTO respuestas_usuario 
                                      (id_usuario, id_cuestionario, respuesta, correcta, fecha_creacion) 
                                      VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$id_usuario, $id_cuestionario, $respuesta_usuario, $es_correcta]);
            }
        }
    }
    
    // ==========================================
    // PROCESAR ARCHIVOS SUBIDOS
    // ==========================================
    if (isset($_FILES['archivo']) && is_array($_FILES['archivo']['name'])) {
        // Configurar carpeta de uploads
        $carpeta_uploads = "../uploads/respuestas/";
        if (!file_exists($carpeta_uploads)) {
            mkdir($carpeta_uploads, 0777, true);
        }
        
        foreach ($_FILES['archivo']['name'] as $id_cuestionario => $nombre_archivo) {
            // Verificar que el archivo se subió correctamente
            if ($_FILES['archivo']['error'][$id_cuestionario] !== UPLOAD_ERR_OK) {
                continue;
            }
            
            // Verificar que esta pregunta sea tipo "archivo"
            $stmt = $pdo->prepare("SELECT tipo_pregunta FROM cuestionarios WHERE id_cuestionario = ?");
            $stmt->execute([$id_cuestionario]);
            $pregunta = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($pregunta && $pregunta['tipo_pregunta'] === 'archivo') {
                $total++; // Contar esta pregunta en el total
                
                // Obtener información del archivo
                $tmp_name = $_FILES['archivo']['tmp_name'][$id_cuestionario];
                $extension = pathinfo($nombre_archivo, PATHINFO_EXTENSION);
                
                // Generar nombre único para el archivo
                $nombre_unico = "usuario_{$id_usuario}_cuestionario_{$id_cuestionario}_" . time() . "." . $extension;
                $ruta_destino = $carpeta_uploads . $nombre_unico;
                
                // Mover el archivo a la carpeta de destino
                if (move_uploaded_file($tmp_name, $ruta_destino)) {
                    // Verificar si ya existe una respuesta de archivo para este cuestionario
                    $stmt = $pdo->prepare("SELECT id_respuesta_archivo, ruta_archivo FROM respuestas_archivo 
                                          WHERE id_usuario = ? AND id_cuestionario = ?");
                    $stmt->execute([$id_usuario, $id_cuestionario]);
                    $existe = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existe) {
                        // Eliminar archivo anterior si existe
                        if (file_exists($existe['ruta_archivo'])) {
                            unlink($existe['ruta_archivo']);
                        }
                        
                        // Actualizar respuesta existente (resetear calificación)
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
                        // Insertar nueva respuesta de archivo
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
    
    $pdo->commit();
    
    // ==========================================
    // DETERMINAR TIPO DE RESULTADO A MOSTRAR
    // ==========================================
    // Verificar si hay preguntas tipo archivo en este video
    $stmt_check_archivo = $pdo->prepare("SELECT COUNT(*) as tiene_archivo 
                                          FROM cuestionarios 
                                          WHERE id_video = ? AND tipo_pregunta = 'archivo'");
    $stmt_check_archivo->execute([$id_video]);
    $result_archivo = $stmt_check_archivo->fetch(PDO::FETCH_ASSOC);
    $tiene_preguntas_archivo = ($result_archivo['tiene_archivo'] > 0);

    if ($tiene_preguntas_archivo) {
        
        // Si tiene preguntas de archivo, guardar mensaje especial
        $_SESSION['resultado_cuestionario'] = [
            'tipo' => 'archivo',
            'mensaje' => 'Tu calificación será revisada',
            'archivos_subidos' => $archivos_subidos
        ];
    } else {
        // Si solo tiene incisos, calcular calificación
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
    
    // Verificar si completó todos los videos
    $stmt = $pdo->query("SELECT v.id_video, v.titulo,
                        (SELECT COUNT(*) FROM cuestionarios WHERE id_video = v.id_video) as total_preguntas
                        FROM videos v 
                        ORDER BY v.id_video ASC");
    $todos_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hay_videos_pendientes = false;
    
    foreach($todos_videos as $video) {
        $total_preguntas_video = $video['total_preguntas'];
        
        // Si no tiene preguntas, se considera completado
        if ($total_preguntas_video == 0) {
            continue;
        }
        
        // Contar respuestas de opción múltiple
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT ru.id_cuestionario) as respondidas
                              FROM respuestas_usuario ru 
                              INNER JOIN cuestionarios q ON ru.id_cuestionario = q.id_cuestionario 
                              WHERE ru.id_usuario = ? AND q.id_video = ? AND q.tipo_pregunta = 'incisos'");
        $stmt->execute([$id_usuario, $video['id_video']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $respondidas_incisos = $result['respondidas'];
        
        // Contar respuestas de archivo
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT ra.id_cuestionario) as respondidas
                              FROM respuestas_archivo ra 
                              INNER JOIN cuestionarios q ON ra.id_cuestionario = q.id_cuestionario 
                              WHERE ra.id_usuario = ? AND q.id_video = ? AND q.tipo_pregunta = 'archivo'");
        $stmt->execute([$id_usuario, $video['id_video']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $respondidas_archivos = $result['respondidas'];
        
        // Contar total de preguntas de cada tipo
        $stmt = $pdo->prepare("SELECT 
                              SUM(CASE WHEN tipo_pregunta = 'incisos' THEN 1 ELSE 0 END) as total_incisos,
                              SUM(CASE WHEN tipo_pregunta = 'archivo' THEN 1 ELSE 0 END) as total_archivos
                              FROM cuestionarios WHERE id_video = ?");
        $stmt->execute([$video['id_video']]);
        $totales = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // El video está completo si respondió todas las preguntas de ambos tipos
        $completo_incisos = ($respondidas_incisos >= $totales['total_incisos']);
        $completo_archivos = ($respondidas_archivos >= $totales['total_archivos']);
        
        if (!$completo_incisos || !$completo_archivos) {
            $hay_videos_pendientes = true;
            break;
        }
    }
    
    // Redirigir según si hay más videos pendientes o no
    if ($hay_videos_pendientes) {
        header("Location: videos_usuario.php");
    } else {
        header("Location: videos_usuario.php?modulo_completado=1");
    }
    exit;
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error en guardar_respuestas.php: " . $e->getMessage());
    $_SESSION['error'] = "Error al guardar las respuestas: " . $e->getMessage();
    header("Location: videos_usuario.php");
    exit;
}
?>