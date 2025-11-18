<?php
session_start();
require_once("../conexion.php");

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../index.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$id_video = $_POST['id_video'] ?? null;
$id_cuestionario = $_POST['id_cuestionario'] ?? null;

// Validación básica
if (!$id_video || !$id_cuestionario) {
    $_SESSION['error'] = "Datos incompletos";
    header("Location: videos_usuario.php");
    exit;
}

// Verificar que el archivo fue subido
if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['error'] = "Debes subir un archivo";
    header("Location: videos_usuario.php");
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Configurar carpeta de uploads
    $carpeta_uploads = "../uploads/respuestas/";
    if (!file_exists($carpeta_uploads)) {
        mkdir($carpeta_uploads, 0777, true);
    }
    
    // Obtener información del archivo
    $archivo = $_FILES['archivo'];
    $nombre_original = $archivo['name'];
    $extension = pathinfo($nombre_original, PATHINFO_EXTENSION);
    
    // Generar nombre único para el archivo
    $nombre_unico = "usuario_{$id_usuario}_cuestionario_{$id_cuestionario}_" . time() . "." . $extension;
    $ruta_destino = $carpeta_uploads . $nombre_unico;
    
    // Mover el archivo a la carpeta de destino
    if (!move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
        throw new Exception("Error al guardar el archivo");
    }
    
    // Verificar si ya existe una respuesta de archivo para este cuestionario
    $stmt = $pdo->prepare("SELECT id_respuesta_archivo FROM respuestas_archivo 
                          WHERE id_usuario = ? AND id_cuestionario = ?");
    $stmt->execute([$id_usuario, $id_cuestionario]);
    $existe = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existe) {
        // Eliminar archivo anterior si existe
        $stmt = $pdo->prepare("SELECT ruta_archivo FROM respuestas_archivo 
                              WHERE id_respuesta_archivo = ?");
        $stmt->execute([$existe['id_respuesta_archivo']]);
        $archivo_anterior = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($archivo_anterior && file_exists($archivo_anterior['ruta_archivo'])) {
            unlink($archivo_anterior['ruta_archivo']);
        }
        
        // Actualizar respuesta existente
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
    
    $pdo->commit();
    
    $_SESSION['mensaje'] = "Archivo subido correctamente. Pendiente de calificación.";
    header("Location: videos_usuario.php");
    exit;
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Eliminar archivo si se subió pero hubo error en BD
    if (isset($ruta_destino) && file_exists($ruta_destino)) {
        unlink($ruta_destino);
    }
    
    error_log("Error en guardar_archivo.php: " . $e->getMessage());
    $_SESSION['error'] = "Error al guardar el archivo: " . $e->getMessage();
    header("Location: videos_usuario.php");
    exit;
}
?>