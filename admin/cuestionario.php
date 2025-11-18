<?php
session_start();
require_once("../conexion.php");

// Verificar admin
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$id_video = $_GET['id'] ?? null;
if (!$id_video) { header("Location: dashboard.php"); exit; }

// Obtener info del video
$stmt = $pdo->prepare("SELECT * FROM videos WHERE id_video = ?");
$stmt->execute([$id_video]);
$video = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$video) die("Video no encontrado");

// Eliminar pregunta
if (isset($_GET['eliminar'])) {
    $id_q = $_GET['eliminar'];
    $stmt = $pdo->prepare("DELETE FROM cuestionarios WHERE id_cuestionario = ?");
    $stmt->execute([$id_q]);
    header("Location: cuestionario.php?id=$id_video");
    exit;
}

// Insertar nueva pregunta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'nueva') {
    $pregunta = trim($_POST['pregunta']);
    $tipo_pregunta = $_POST['tipo_pregunta'] ?? 'opciones';

    if ($tipo_pregunta == 'opciones') {
        $opA = trim($_POST['opcion_a']);
        $opB = trim($_POST['opcion_b']);
        $opC = trim($_POST['opcion_c']);
        $correcta = $_POST['respuesta_correcta'];
        $instrucciones = null;
    } else {
        $opA = null;
        $opB = null;
        $opC = null;
        $correcta = null;
        $instrucciones = trim($_POST['intrucciones_archivo']);
    }

    $stmt = $pdo->prepare("INSERT INTO cuestionarios (id_video, pregunta, opcion_a, opcion_b, opcion_c, respuesta_correcta, tipo_pregunta, intrucciones_archivo) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$id_video, $pregunta, $opA, $opB, $opC, $correcta, $tipo_pregunta, $instrucciones]);

    header("Location: cuestionario.php?id=$id_video");
    exit;
}

// Editar pregunta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar') {
    $id_q = $_POST['id_cuestionario'];
    $pregunta = trim($_POST['pregunta']);
    $tipo_pregunta = $_POST['tipo_pregunta'] ?? 'opciones';

    if ($tipo_pregunta == 'opciones') {
        $opA = trim($_POST['opcion_a']);
        $opB = trim($_POST['opcion_b']);
        $opC = trim($_POST['opcion_c']);
        $correcta = $_POST['respuesta_correcta'];
        $instrucciones = null;
    } else {
        $opA = null;
        $opB = null;
        $opC = null;
        $correcta = null;
        $instrucciones = trim($_POST['intrucciones_archivo']);
    }

    $stmt = $pdo->prepare("UPDATE cuestionarios SET pregunta=?, opcion_a=?, opcion_b=?, opcion_c=?, respuesta_correcta=?, tipo_pregunta=?, intrucciones_archivo=? WHERE id_cuestionario=?");
    $stmt->execute([$pregunta, $opA, $opB, $opC, $correcta, $tipo_pregunta, $instrucciones, $id_q]);

    header("Location: cuestionario.php?id=$id_video");
    exit;
}

// Obtener cuestionarios del video
$stmt = $pdo->prepare("SELECT * FROM cuestionarios WHERE id_video = ? ORDER BY id_cuestionario ASC");
$stmt->execute([$id_video]);
$cuestionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si se quiere editar
$editando = null;
if (isset($_GET['editar'])) {
    $id_q = $_GET['editar'];
    $stmt = $pdo->prepare("SELECT * FROM cuestionarios WHERE id_cuestionario = ?");
    $stmt->execute([$id_q]);
    $editando = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Cuestionario de <?= htmlspecialchars($video['titulo']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/estilos.css">
  <style>
    .tipo-pregunta-opciones { display: block; }
    .tipo-pregunta-archivo { display: none; }
  </style>
</head>
<body>
<div class="container mt-4">
  <h2>📝 Cuestionario para el video: <b><?= htmlspecialchars($video['titulo']) ?></b></h2>
  <a href="carpeta.php?id=<?= $video['id_carpeta'] ?>" class="btn btn-secondary mb-3">⬅ Volver</a>

  <!-- Formulario -->
  <div class="card mb-4">
    <div class="card-body">
      <?php if ($editando): ?>
        <h5>✏ Editar pregunta</h5>
        <form method="post">
          <input type="hidden" name="accion" value="editar">
          <input type="hidden" name="id_cuestionario" value="<?= $editando['id_cuestionario'] ?>">
          
          <div class="mb-3">
            <label>Tipo de pregunta:</label>
            <select name="tipo_pregunta" class="form-select" onchange="toggleTipoPregunta(this.value, 'editar')">
              <option value="opciones" <?= $editando['tipo_pregunta']=='opciones'?'selected':'' ?>>Pregunta con opciones (A, B, C)</option>
              <option value="archivo" <?= $editando['tipo_pregunta']=='archivo'?'selected':'' ?>>Pregunta con archivo (Mapa mental, etc.)</option>
            </select>
          </div>

          <div class="mb-2">
            <textarea name="pregunta" class="form-control" placeholder="Escribe la pregunta" required><?= htmlspecialchars($editando['pregunta']) ?></textarea>
          </div>

          <div id="opciones-editar" style="display: <?= $editando['tipo_pregunta']=='opciones'?'block':'none' ?>">
            <div class="mb-2">
              <input type="text" name="opcion_a" class="form-control" placeholder="Opción A" value="<?= htmlspecialchars($editando['opcion_a'] ?? '') ?>">
            </div>
            <div class="mb-2">
              <input type="text" name="opcion_b" class="form-control" placeholder="Opción B" value="<?= htmlspecialchars($editando['opcion_b'] ?? '') ?>">
            </div>
            <div class="mb-2">
              <input type="text" name="opcion_c" class="form-control" placeholder="Opción C" value="<?= htmlspecialchars($editando['opcion_c'] ?? '') ?>">
            </div>
            <div class="mb-2">
              <label>Respuesta correcta:</label>
              <select name="respuesta_correcta" class="form-select">
                <option value="A" <?= $editando['respuesta_correcta']=='A'?'selected':'' ?>>Opción A</option>
                <option value="B" <?= $editando['respuesta_correcta']=='B'?'selected':'' ?>>Opción B</option>
                <option value="C" <?= $editando['respuesta_correcta']=='C'?'selected':'' ?>>Opción C</option>
              </select>
            </div>
          </div>

          <div id="archivo-editar" style="display: <?= $editando['tipo_pregunta']=='archivo'?'block':'none' ?>">
            <div class="mb-2">
              <label>Instrucciones para el archivo:</label>
              <textarea name="intrucciones_archivo" class="form-control" rows="3" placeholder="Ej: Crea un mapa mental sobre..."><?= htmlspecialchars($editando['intrucciones_archivo'] ?? '') ?></textarea>
            </div>
          </div>

          <button class="btn btn-warning">Actualizar</button>
          <a href="cuestionario.php?id=<?= $id_video ?>" class="btn btn-secondary">Cancelar</a>
        </form>
      <?php else: ?>
        <h5>➕ Agregar nueva pregunta</h5>
        <form method="post">
          <input type="hidden" name="accion" value="nueva">
          
          <div class="mb-3">
            <label>Tipo de pregunta:</label>
            <select name="tipo_pregunta" class="form-select" onchange="toggleTipoPregunta(this.value, 'nueva')">
              <option value="opciones">Pregunta con opciones (A, B, C)</option>
              <option value="archivo">Pregunta con archivo (Mapa mental, etc.)</option>
            </select>
          </div>

          <div class="mb-2">
            <textarea name="pregunta" class="form-control" placeholder="Escribe la pregunta" required></textarea>
          </div>

          <div id="opciones-nueva">
            <div class="mb-2">
              <input type="text" name="opcion_a" class="form-control" placeholder="Opción A">
            </div>
            <div class="mb-2">
              <input type="text" name="opcion_b" class="form-control" placeholder="Opción B">
            </div>
            <div class="mb-2">
              <input type="text" name="opcion_c" class="form-control" placeholder="Opción C">
            </div>
            <div class="mb-2">
              <label>Respuesta correcta:</label>
              <select name="respuesta_correcta" class="form-select">
                <option value="A">Opción A</option>
                <option value="B">Opción B</option>
                <option value="C">Opción C</option>
              </select>
            </div>
          </div>

          <div id="archivo-nueva" style="display: none;">
            <div class="mb-2">
              <label>Instrucciones para el archivo:</label>
              <textarea name="intrucciones_archivo" class="form-control" rows="3" placeholder="Ej: Crea un mapa mental sobre el tema visto en el video"></textarea>
            </div>
          </div>

          <button class="btn btn-primary">Guardar pregunta</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <!-- Lista de preguntas -->
  <h4>Preguntas agregadas</h4>
  <?php foreach ($cuestionarios as $q): ?>
    <div class="card mb-2 p-3 shadow-sm">
      <p><b>Pregunta:</b> <?= htmlspecialchars($q['pregunta']) ?></p>
      <p><b>Tipo:</b> <?= $q['tipo_pregunta'] == 'opciones' ? '📝 Opciones múltiples' : '📎 Archivo' ?></p>
      
      <?php if ($q['tipo_pregunta'] == 'opciones'): ?>
        <ul>
          <li>A) <?= htmlspecialchars($q['opcion_a']) ?></li>
          <li>B) <?= htmlspecialchars($q['opcion_b']) ?></li>
          <li>C) <?= htmlspecialchars($q['opcion_c']) ?></li>
        </ul>
        <p><b>Respuesta correcta:</b> <?= $q['respuesta_correcta'] ?></p>
      <?php else: ?>
        <p><b>Instrucciones:</b> <?= htmlspecialchars($q['intrucciones_archivo']) ?></p>
      <?php endif; ?>
      
      <a href="cuestionario.php?id=<?= $id_video ?>&editar=<?= $q['id_cuestionario'] ?>" class="btn btn-warning btn-sm">✏ Editar</a>
      <a href="cuestionario.php?id=<?= $id_video ?>&eliminar=<?= $q['id_cuestionario'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar esta pregunta?')">🗑 Eliminar</a>
    </div>
  <?php endforeach; ?>
</div>

<script>
function toggleTipoPregunta(tipo, contexto) {
  const opcionesDiv = document.getElementById('opciones-' + contexto);
  const archivoDiv = document.getElementById('archivo-' + contexto);
  
  if (tipo === 'opciones') {
    opcionesDiv.style.display = 'block';
    archivoDiv.style.display = 'none';
  } else {
    opcionesDiv.style.display = 'none';
    archivoDiv.style.display = 'block';
  }
}
</script>
</body>
</html>