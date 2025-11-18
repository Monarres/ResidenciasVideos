<?php
session_start();
require_once("../conexion.php");

// Verificar si es admin
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// 🔹 Obtener áreas (para el select del video)
$stmt = $pdo->query("SELECT * FROM areas ORDER BY nombre ASC");
$areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------------- VIDEO ----------------
// Insertar nuevo video
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['accion'])) {
    // Agregar video
    if ($_POST['accion'] === "agregar_video") {
        $titulo = trim($_POST['titulo']);
        $ruta = trim($_POST['ruta']);
        $id_area = (int) $_POST['id_area'];
        if (!empty($titulo) && !empty($ruta)) {
            $stmt = $pdo->prepare("INSERT INTO videos (titulo, ruta, id_area) VALUES (:titulo, :ruta, :id_area)");
            $stmt->execute(['titulo'=>$titulo,'ruta'=>$ruta,'id_area'=>$id_area]);
            $lastVideoId = $pdo->lastInsertId();
            header("Location: form_video_cuestionario.php?video=$lastVideoId");
            exit;
        }
    }

    // Editar video
    if ($_POST['accion'] === "editar_video") {
        $id_video = (int) $_POST['id_video'];
        $titulo = trim($_POST['titulo']);
        $ruta = trim($_POST['ruta']);
        $id_area = (int) $_POST['id_area'];
        if (!empty($titulo) && !empty($ruta)) {
            $stmt = $pdo->prepare("UPDATE videos SET titulo=:titulo, ruta=:ruta, id_area=:id_area WHERE id_video=:id");
            $stmt->execute(['titulo'=>$titulo,'ruta'=>$ruta,'id_area'=>$id_area,'id'=>$id_video]);
            header("Location: form_video_cuestionario.php");
            exit;
        }
    }

    // Eliminar video
    if ($_POST['accion'] === "eliminar_video") {
        $id_video = (int) $_POST['id_video'];
        $pdo->prepare("DELETE FROM cuestionarios WHERE id_video=:id_video")->execute(['id_video'=>$id_video]);
        $pdo->prepare("DELETE FROM videos WHERE id_video=:id")->execute(['id'=>$id_video]);
        header("Location: form_video_cuestionario.php");
        exit;
    }

    // Agregar pregunta
    if ($_POST['accion'] === "agregar_pregunta") {
        $id_video = (int) $_POST['id_video'];
        $pregunta = trim($_POST['pregunta']);
        $opcion_a = trim($_POST['opcion_a']);
        $opcion_b = trim($_POST['opcion_b']);
        $opcion_c = trim($_POST['opcion_c']);
        $respuesta_correcta = $_POST['respuesta_correcta'];
        if (!empty($pregunta) && !empty($respuesta_correcta)) {
            $stmt = $pdo->prepare("INSERT INTO cuestionarios (id_video, pregunta, opcion_a, opcion_b, opcion_c, respuesta_correcta) 
                                   VALUES (:id_video, :pregunta, :opcion_a, :opcion_b, :opcion_c, :respuesta_correcta)");
            $stmt->execute([
                'id_video'=>$id_video,
                'pregunta'=>$pregunta,
                'opcion_a'=>$opcion_a,
                'opcion_b'=>$opcion_b,
                'opcion_c'=>$opcion_c,
                'respuesta_correcta'=>$respuesta_correcta
            ]);
            header("Location: form_video_cuestionario.php?video=$id_video");
            exit;
        }
    }

    // Editar pregunta
    if ($_POST['accion'] === "editar_pregunta") {
        $id_cuestionario = (int) $_POST['id_cuestionario'];
        $pregunta = trim($_POST['pregunta']);
        $opcion_a = trim($_POST['opcion_a']);
        $opcion_b = trim($_POST['opcion_b']);
        $opcion_c = trim($_POST['opcion_c']);
        $respuesta_correcta = $_POST['respuesta_correcta'];
        if (!empty($pregunta) && !empty($respuesta_correcta)) {
            $stmt = $pdo->prepare("UPDATE cuestionarios SET pregunta=:pregunta, opcion_a=:opcion_a, opcion_b=:opcion_b, opcion_c=:opcion_c, respuesta_correcta=:respuesta_correcta WHERE id_cuestionario=:id");
            $stmt->execute([
                'pregunta'=>$pregunta,
                'opcion_a'=>$opcion_a,
                'opcion_b'=>$opcion_b,
                'opcion_c'=>$opcion_c,
                'respuesta_correcta'=>$respuesta_correcta,
                'id'=>$id_cuestionario
            ]);
            header("Location: form_video_cuestionario.php?video=".$_POST['id_video']);
            exit;
        }
    }

    // Eliminar pregunta
    if ($_POST['accion'] === "eliminar_pregunta") {
        $id_cuestionario = (int) $_POST['id_cuestionario'];
        $id_video = (int) $_POST['id_video'];
        $pdo->prepare("DELETE FROM cuestionarios WHERE id_cuestionario=:id")->execute(['id'=>$id_cuestionario]);
        header("Location: form_video_cuestionario.php?video=$id_video");
        exit;
    }
}

// ---------------- LISTADO ----------------
$sql = "SELECT v.*, a.nombre AS area_nombre FROM videos v LEFT JOIN areas a ON v.id_area=a.id_area ORDER BY v.id_video DESC";
$stmt = $pdo->query($sql);
$videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Preguntas por video
$preguntasPorVideo = [];
foreach($videos as $video){
    $stmt = $pdo->prepare("SELECT * FROM cuestionarios WHERE id_video=:id_video ORDER BY id_cuestionario ASC");
    $stmt->execute(['id_video'=>$video['id_video']]);
    $preguntasPorVideo[$video['id_video']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Video + Cuestionario</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="bg-light">
<div class="container mt-4">
<h1 class="mb-4 text-center">📹 Agregar Video + Cuestionario</h1>

<!-- FORM NUEVO VIDEO -->
<div class="card mb-4 shadow">
<div class="card-header bg-primary text-white">➕ Nuevo Video</div>
<div class="card-body">
<form method="POST">
<input type="hidden" name="accion" value="agregar_video">
<div class="row g-3">
<div class="col-md-4"><input type="text" name="titulo" class="form-control" placeholder="Título del video" required></div>
<div class="col-md-4"><input type="text" name="ruta" class="form-control" placeholder="Ruta o URL del video" required></div>
<div class="col-md-3">
<select name="id_area" class="form-select" required>
<option value="">Selecciona un área</option>
<?php foreach($areas as $area): ?>
<option value="<?= $area['id_area'] ?>"><?= htmlspecialchars($area['nombre']) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-1"><button class="btn btn-success w-100" type="submit">Guardar</button></div>
</div>
</form>
</div>
</div>

<!-- LISTADO DE VIDEOS -->
<div class="card shadow mb-4">
<div class="card-header bg-dark text-white">📋 Videos y Cuestionarios</div>
<div class="card-body">
<?php foreach($videos as $video): ?>
<div class="mb-4 p-3 border rounded">
<div class="d-flex justify-content-between align-items-center mb-2">
<h5><?= htmlspecialchars($video['titulo']) ?> <small class="text-muted">(<?= htmlspecialchars($video['area_nombre']) ?>)</small></h5>
<div>
<button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalEditarVideo<?= $video['id_video'] ?>">Editar</button>
<form style="display:inline;" method="POST">
<input type="hidden" name="accion" value="eliminar_video">
<input type="hidden" name="id_video" value="<?= $video['id_video'] ?>">
<button class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar este video y todas sus preguntas?')">Eliminar</button>
</form>
<button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalAgregarPregunta<?= $video['id_video'] ?>">+ Pregunta</button>
</div>
</div>
<p><strong>URL:</strong> <?= htmlspecialchars($video['ruta']) ?></p>

<!-- LISTADO DE PREGUNTAS -->
<?php if(!empty($preguntasPorVideo[$video['id_video']])): ?>
<ul class="list-group mb-2">
<?php foreach($preguntasPorVideo[$video['id_video']] as $p): ?>
<li class="list-group-item d-flex justify-content-between align-items-start">
<div>
<strong><?= htmlspecialchars($p['pregunta']) ?></strong><br>
A) <?= htmlspecialchars($p['opcion_a']) ?> | B) <?= htmlspecialchars($p['opcion_b']) ?> | C) <?= htmlspecialchars($p['opcion_c']) ?><br>
<span class="badge bg-success">Correcta: <?= $p['respuesta_correcta'] ?></span>
</div>
<div>
<button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalEditarPregunta<?= $p['id_cuestionario'] ?>">Editar</button>
<form style="display:inline;" method="POST">
<input type="hidden" name="accion" value="eliminar_pregunta">
<input type="hidden" name="id_cuestionario" value="<?= $p['id_cuestionario'] ?>">
<input type="hidden" name="id_video" value="<?= $video['id_video'] ?>">
<button class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar esta pregunta?')">Eliminar</button>
</form>
</div>
</li>
<?php endforeach; ?>
</ul>
<?php else: ?>
<p class="text-muted">No hay preguntas para este video.</p>
<?php endif; ?>
</div>

<!-- MODAL EDITAR VIDEO -->
<div class="modal fade" id="modalEditarVideo<?= $video['id_video'] ?>" tabindex="-1" aria-hidden="true">
<div class="modal-dialog">
<form method="POST" class="modal-content">
<input type="hidden" name="accion" value="editar_video">
<input type="hidden" name="id_video" value="<?= $video['id_video'] ?>">
<div class="modal-header">
<h5 class="modal-title">Editar Video</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="mb-3"><label>Título</label><input type="text" name="titulo" class="form-control" value="<?= htmlspecialchars($video['titulo']) ?>" required></div>
<div class="mb-3"><label>URL/Ruta</label><input type="text" name="ruta" class="form-control" value="<?= htmlspecialchars($video['ruta']) ?>" required></div>
<div class="mb-3">
<label>Área</label>
<select name="id_area" class="form-select" required>
<?php foreach($areas as $area): ?>
<option value="<?= $area['id_area'] ?>" <?= $area['id_area']==$video['id_area']?'selected':'' ?>><?= htmlspecialchars($area['nombre']) ?></option>
<?php endforeach; ?>
</select>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
<button type="submit" class="btn btn-warning">Guardar</button>
</div>
</form>
</div>
</div>

<!-- MODAL AGREGAR PREGUNTA -->
<div class="modal fade" id="modalAgregarPregunta<?= $video['id_video'] ?>" tabindex="-1" aria-hidden="true">
<div class="modal-dialog">
<form method="POST" class="modal-content">
<input type="hidden" name="accion" value="agregar_pregunta">
<input type="hidden" name="id_video" value="<?= $video['id_video'] ?>">
<div class="modal-header">
<h5 class="modal-title">Agregar Pregunta</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="mb-2"><label>Pregunta</label><input type="text" name="pregunta" class="form-control" required></div>
<div class="mb-2"><label>Opción A</label><input type="text" name="opcion_a" class="form-control"></div>
<div class="mb-2"><label>Opción B</label><input type="text" name="opcion_b" class="form-control"></div>
<div class="mb-2"><label>Opción C</label><input type="text" name="opcion_c" class="form-control"></div>
<div class="mb-2">
<label>Respuesta Correcta</label>
<select name="respuesta_correcta" class="form-select" required>
<option value="">Selecciona</option>
<option value="A">A</option>
<option value="B">B</option>
<option value="C">C</option>
</select>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
<button type="submit" class="btn btn-success">Agregar</button>
</div>
</form>
</div>
</div>

<?php
// MODALES DE EDITAR PREGUNTA
foreach($preguntasPorVideo[$video['id_video']] as $p):
?>
<div class="modal fade" id="modalEditarPregunta<?= $p['id_cuestionario'] ?>" tabindex="-1" aria-hidden="true">
<div class="modal-dialog">
<form method="POST" class="modal-content">
<input type="hidden" name="accion" value="editar_pregunta">
<input type="hidden" name="id_cuestionario" value="<?= $p['id_cuestionario'] ?>">
<input type="hidden" name="id_video" value="<?= $video['id_video'] ?>">
<div class="modal-header">
<h5 class="modal-title">Editar Pregunta</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="mb-2"><label>Pregunta</label><input type="text" name="pregunta" class="form-control" value="<?= htmlspecialchars($p['pregunta']) ?>" required></div>
<div class="mb-2"><label>Opción A</label><input type="text" name="opcion_a" class="form-control" value="<?= htmlspecialchars($p['opcion_a']) ?>"></div>
<div class="mb-2"><label>Opción B</label><input type="text" name="opcion_b" class="form-control" value="<?= htmlspecialchars($p['opcion_b']) ?>"></div>
<div class="mb-2"><label>Opción C</label><input type="text" name="opcion_c" class="form-control" value="<?= htmlspecialchars($p['opcion_c']) ?>"></div>
<div class="mb-2">
<label>Respuesta Correcta</label>
<select name="respuesta_correcta" class="form-select" required>
<option value="A" <?= $p['respuesta_correcta']=='A'?'selected':'' ?>>A</option>
<option value="B" <?= $p['respuesta_correcta']=='B'?'selected':'' ?>>B</option>
<option value="C" <?= $p['respuesta_correcta']=='C'?'selected':'' ?>>C</option>
</select>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
<button type="submit" class="btn btn-warning">Guardar</button>
</div>
</form>
</div>
</div>
<?php endforeach; ?>

<?php endforeach; ?>
</div>
</div>

<a href="dashboard.php" class="btn btn-secondary mt-3">⬅ Volver al Dashboard</a>
</div>
</body>
</html>
