<?php
session_start();
require_once("../conexion.php");

// Verificar si es admin
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// 🔹 Obtener todas las áreas (para el select)
$stmt = $pdo->query("SELECT * FROM areas ORDER BY nombre ASC");
$areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 🔹 Insertar nuevo video
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['accion']) && $_POST['accion'] === "agregar") {
    $titulo = trim($_POST['titulo']);
    $ruta = trim($_POST['ruta']);
    $id_area = (int) $_POST['id_area'];

    if (!empty($titulo) && !empty($ruta)) {
        $stmt = $pdo->prepare("INSERT INTO videos (titulo, ruta, id_area) 
                               VALUES (:titulo, :ruta, :id_area)");
        $stmt->execute([
            'titulo' => $titulo,
            'ruta' => $ruta,
            'id_area' => $id_area
        ]);
        header("Location: videos.php");
        exit;
    }
}

// 🔹 Editar video
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['accion']) && $_POST['accion'] === "editar") {
    $id = (int) $_POST['id_video'];
    $titulo = trim($_POST['titulo']);
    
    $ruta = trim($_POST['ruta']);
    $id_area = (int) $_POST['id_area'];

    if (!empty($titulo) && !empty($ruta)) {
        $stmt = $pdo->prepare("UPDATE videos 
                               SET titulo = :titulo, ruta = :ruta, id_area = :id_area
                               WHERE id_video = :id");
        $stmt->execute([
            'titulo' => $titulo,
            'ruta' => $ruta,
            'id_area' => $id_area,
            'id' => $id
        ]);
        header("Location: videos.php");
        exit;
    }
}

// 🔹 Eliminar video
if (isset($_GET['eliminar'])) {
    $id = (int) $_GET['eliminar'];
    $stmt = $pdo->prepare("DELETE FROM videos WHERE id_video = :id");
    $stmt->execute(['id' => $id]);
    header("Location: videos.php");
    exit;
}

// 🔹 Listar videos
$sql = "SELECT v.*, a.nombre AS area_nombre 
        FROM videos v 
        LEFT JOIN areas a ON v.id_area = a.id_area 
        ORDER BY v.id_video DESC";
$stmt = $pdo->query($sql);
$videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gestionar Videos</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="bg-light">
<div class="container mt-4">
  <h1 class="mb-4">Gestión de Videos</h1>

  <!-- Formulario para agregar video -->
  <form method="POST" class="mb-4">
    <input type="hidden" name="accion" value="agregar">
    <div class="row g-2">
      <div class="col-md-3">
        <input type="text" name="titulo" class="form-control" placeholder="Título" required>
      </div>

      <div class="col-md-3">
        <input type="text" name="ruta" class="form-control" placeholder="Ruta o URL" required>
      </div>
      <div class="col-md-2">
        <select name="id_area" class="form-select" required>
          <option value="">Área</option>
          <?php foreach ($areas as $area): ?>
            <option value="<?= $area['id_area'] ?>"><?= htmlspecialchars($area['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1">
        <button class="btn btn-success w-100" type="submit">➕</button>
      </div>
    </div>
  </form>

  <!-- Tabla de videos -->
  <table class="table table-bordered table-striped">
    <thead>
      <tr>
        <th>ID</th>
        <th>Título</th>
 
        <th>Ruta</th>
        <th>Área</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($videos as $video): ?>
      <tr>
        <td><?= $video['id_video'] ?></td>
        <td><?= htmlspecialchars($video['titulo']) ?></td>

        <td><small><?= htmlspecialchars($video['ruta']) ?></small></td>
        <td><?= htmlspecialchars($video['area_nombre']) ?></td>
        <td>
          <!-- Botón Editar con modal -->
          <button class="btn btn-sm btn-warning"
                  data-bs-toggle="modal"
                  data-bs-target="#modalEditar"
                  data-id="<?= $video['id_video'] ?>"
                  data-titulo="<?= htmlspecialchars($video['titulo']) ?>"

                  data-ruta="<?= htmlspecialchars($video['ruta']) ?>"
                  data-area="<?= $video['id_area'] ?>">
            Editar
          </button>
          <a href="videos.php?eliminar=<?= $video['id_video'] ?>" class="btn btn-danger btn-sm"
             onclick="return confirm('¿Eliminar este video?');">Eliminar</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <a href="dashboard.php" class="btn btn-secondary">⬅ Volver al Dashboard</a>
</div>
<!-- Modal para editar video -->
<div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="POST" class="modal-content">
      <input type="hidden" name="accion" value="editar">
      <input type="hidden" name="id_video" id="edit-id">
      <div class="modal-header">
        <h5 class="modal-title">Editar Video</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Título</label>
            <input type="text" class="form-control" name="titulo" id="edit-titulo" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Ruta o URL</label>
            <input type="text" class="form-control" name="ruta" id="edit-ruta" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Área</label>
            <select name="id_area" id="edit-area" class="form-select" required>
              <?php foreach ($areas as $area): ?>
                <option value="<?= $area['id_area'] ?>"><?= htmlspecialchars($area['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-warning">Actualizar</button>
      </div>
    </form>
  </div>
</div>

<script>
// Pasar datos al modal al hacer clic en Editar
const modalEditar = document.getElementById('modalEditar');
modalEditar.addEventListener('show.bs.modal', event => {
  const button = event.relatedTarget;
  document.getElementById('edit-id').value = button.getAttribute('data-id');
  document.getElementById('edit-titulo').value = button.getAttribute('data-titulo');
  document.getElementById('edit-ruta').value = button.getAttribute('data-ruta');
  document.getElementById('edit-area').value = button.getAttribute('data-area');
});
</script>
</body>
</html>
