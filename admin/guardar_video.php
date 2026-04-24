<?php
session_start();
header('Content-Type: application/json');
require_once("../conexion.php");

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    if (!isset($_POST['id_carpeta']) || !isset($_POST['titulo'])) {
        throw new Exception('Datos incompletos: faltan campos obligatorios');
    }

    $id_carpeta = intval($_POST['id_carpeta']);
    $titulo = trim($_POST['titulo']);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $url_youtube = trim($_POST['url_youtube'] ?? '');
    $instrucciones_cuestionario = trim($_POST['instrucciones_cuestionario'] ?? '');

    if (empty($titulo)) throw new Exception('El título es obligatorio');

    $url_embed = null;
    if (!empty($url_youtube)) {
        $video_id = extraerIdYoutube($url_youtube);
        if (!$video_id) throw new Exception('URL de YouTube inválida.');
        $url_embed = "https://www.youtube.com/embed/" . $video_id;
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("INSERT INTO videos (id_carpeta, titulo, descripcion, ruta, tipo_video, instrucciones_cuestionario, fecha_creacion) 
                               VALUES (?, ?, ?, ?, 'youtube', ?, NOW())");
        $stmt->execute([$id_carpeta, $titulo, $descripcion, $url_embed, $instrucciones_cuestionario]);
        $id_video = $pdo->lastInsertId();

        if (isset($_POST['preguntas'])) {
            $preguntas = json_decode($_POST['preguntas'], true);

            if (json_last_error() !== JSON_ERROR_NONE) throw new Exception('Error al decodificar las preguntas');

            if (is_array($preguntas) && count($preguntas) > 0) {
                foreach ($preguntas as $index => $p) {
                    if (!isset($p['tipo'])) throw new Exception("Tipo de pregunta no especificado en pregunta " . ($index + 1));

                    if ($p['tipo'] === 'incisos') {
                        if (empty($p['pregunta'])) throw new Exception("La pregunta " . ($index + 1) . " está vacía");
                        if (!isset($p['opciones']) || count($p['opciones']) < 2) throw new Exception("La pregunta " . ($index + 1) . " debe tener al menos 2 opciones");
                        if (empty($p['respuesta_correcta'])) throw new Exception("La pregunta " . ($index + 1) . " no tiene respuesta correcta");
                        if (!isset($p['opciones'][$p['respuesta_correcta']])) throw new Exception("La respuesta correcta no corresponde a ninguna opción");

                        $opciones_json = json_encode($p['opciones'], JSON_UNESCAPED_UNICODE);
                        $stmt = $pdo->prepare("INSERT INTO cuestionarios (id_video, tipo_pregunta, pregunta, opciones_json, respuesta_correcta) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$id_video, 'incisos', $p['pregunta'], $opciones_json, $p['respuesta_correcta']]);

                    } elseif ($p['tipo'] === 'archivo') {
                        if (empty($p['pregunta'])) throw new Exception("La pregunta " . ($index + 1) . " está vacía");
                        if (empty($p['instrucciones'])) throw new Exception("La pregunta " . ($index + 1) . " no tiene instrucciones");

                        $stmt = $pdo->prepare("INSERT INTO cuestionarios (id_video, tipo_pregunta, pregunta, instrucciones_archivo) VALUES (?, 'archivo', ?, ?)");
                        $stmt->execute([$id_video, $p['pregunta'], $p['instrucciones']]);

                    } else {
                        throw new Exception("Tipo de pregunta no válido en pregunta " . ($index + 1));
                    }
                }
            }
        }

        $pdo->commit();

        // Guardar diapositivas
        $debug_info = "sin archivos";
        if (isset($_FILES['diapositivas']) && !empty($_FILES['diapositivas']['name'][0])) {
            $dir = '../uploads/diapositivas/' . $id_video . '/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $debug_info = "dir=$dir exists=" . (is_dir($dir) ? 'SI' : 'NO');

            $extensiones_permitidas = ['pdf', 'pptx', 'ppt', 'jpg', 'jpeg', 'png'];

            foreach ($_FILES['diapositivas']['name'] as $i => $nombre) {
                if ($_FILES['diapositivas']['error'][$i] !== UPLOAD_ERR_OK) continue;

                $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
                if (!in_array($ext, $extensiones_permitidas)) continue;

                if ($ext === 'pdf') {
                    $tipo = 'pdf';
                } elseif (in_array($ext, ['pptx', 'ppt'])) {
                    $tipo = 'pptx';
                } else {
                    $tipo = 'imagen';
                }

                $nombre_unico = uniqid('slide_') . '.' . $ext;
                $ruta_destino = $dir . $nombre_unico;

                if (move_uploaded_file($_FILES['diapositivas']['tmp_name'][$i], $ruta_destino)) {
                    $ruta_db = 'uploads/diapositivas/' . $id_video . '/' . $nombre_unico;
                    $stmt_s = $pdo->prepare("INSERT INTO diapositivas (id_video, archivo, tipo, orden) VALUES (?, ?, ?, ?)");
                    $stmt_s->execute([$id_video, $ruta_db, $tipo, $i]);
                    $debug_info .= " | archivo=$nombre_unico guardado";
                } else {
                    $debug_info .= " | ERROR moviendo $nombre";
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Video guardado correctamente',
            'id_video' => $id_video
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function extraerIdYoutube($url) {
    $patron = '/(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/';
    if (preg_match($patron, $url, $coincidencias)) return $coincidencias[1];
    return false;
}
?>