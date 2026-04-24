<?php
session_start();
require_once("../conexion.php");

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    if (isset($_GET['action']) || isset($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
    } else {
        header('Location: ../login.php');
    }
    exit;
}

// ============================================================
// ACCIÓN: OBTENER presentación (AJAX GET)
// ============================================================
if (isset($_GET['action']) && $_GET['action'] === 'obtener') {
    header('Content-Type: application/json');

    $id_presentacion = intval($_GET['id'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM presentaciones WHERE id_presentacion = ?");
    $stmt->execute([$id_presentacion]);
    $presentacion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$presentacion) {
        echo json_encode(['success' => false, 'message' => 'Presentación no encontrada']);
        exit;
    }

    $stmtA = $pdo->prepare("SELECT * FROM presentacion_archivos WHERE id_presentacion = ? ORDER BY orden ASC");
    $stmtA->execute([$id_presentacion]);
    $archivos = $stmtA->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'presentacion' => $presentacion, 'archivos' => $archivos]);
    exit;
}

// ============================================================
// ACCIÓN: ELIMINAR presentación (AJAX POST)
// ============================================================
if (isset($_POST['action']) && $_POST['action'] === 'eliminar') {
    header('Content-Type: application/json');

    try {
        $id_presentacion = intval($_POST['id_presentacion'] ?? 0);

        // Obtener archivos para eliminar físicamente
        $stmtA = $pdo->prepare("SELECT ruta FROM presentacion_archivos WHERE id_presentacion = ?");
        $stmtA->execute([$id_presentacion]);
        $archivos = $stmtA->fetchAll(PDO::FETCH_ASSOC);

        foreach ($archivos as $archivo) {
            $ruta = '../' . $archivo['ruta'];
            if (file_exists($ruta)) unlink($ruta);
        }

        // Eliminar carpeta si existe
        $dir = '../uploads/presentaciones/' . $id_presentacion . '/';
        if (is_dir($dir)) rmdir($dir);

        $pdo->prepare("DELETE FROM presentaciones WHERE id_presentacion = ?")->execute([$id_presentacion]);

        echo json_encode(['success' => true, 'message' => 'Presentación eliminada correctamente']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ============================================================
// ACCIÓN: GUARDAR nueva presentación (AJAX POST)
// ============================================================
if (isset($_POST['action']) && $_POST['action'] === 'guardar') {
    header('Content-Type: application/json');

    try {
        $id_carpeta  = intval($_POST['id_carpeta'] ?? 0);
        $titulo      = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $momento     = trim($_POST['momento'] ?? 'antes');

        if (empty($titulo)) throw new Exception('El título es obligatorio');
        if (!in_array($momento, ['antes', 'despues', 'al_terminar'])) throw new Exception('Momento no válido');
        if (!isset($_FILES['archivos']) || empty($_FILES['archivos']['name'][0])) throw new Exception('Debes subir al menos un archivo');

        $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'pptx', 'ppt'];

        $pdo->beginTransaction();

        // Obtener orden
        $stmtOrden = $pdo->prepare("SELECT COUNT(*) FROM presentaciones WHERE id_carpeta = ?");
        $stmtOrden->execute([$id_carpeta]);
        $orden = $stmtOrden->fetchColumn();

        $stmt = $pdo->prepare("INSERT INTO presentaciones (id_carpeta, titulo, descripcion, momento, orden) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$id_carpeta, $titulo, $descripcion, $momento, $orden]);
        $id_presentacion = $pdo->lastInsertId();

        $dir = '../uploads/presentaciones/' . $id_presentacion . '/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        foreach ($_FILES['archivos']['name'] as $i => $nombre) {
            if ($_FILES['archivos']['error'][$i] !== UPLOAD_ERR_OK) continue;

            $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
            if (!in_array($ext, $extensiones_permitidas)) continue;

            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) $tipo = 'imagen';
            elseif ($ext === 'pdf') $tipo = 'pdf';
            else $tipo = 'pptx';

            $nombre_unico = uniqid('pres_') . '.' . $ext;
            $ruta_destino = $dir . $nombre_unico;

            if (move_uploaded_file($_FILES['archivos']['tmp_name'][$i], $ruta_destino)) {
                $ruta_db = 'uploads/presentaciones/' . $id_presentacion . '/' . $nombre_unico;
                $stmtA = $pdo->prepare("INSERT INTO presentacion_archivos (id_presentacion, nombre_original, ruta, tipo, orden) VALUES (?, ?, ?, ?, ?)");
                $stmtA->execute([$id_presentacion, $nombre, $ruta_db, $tipo, $i]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Presentación guardada correctamente']);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ============================================================
// ACCIÓN: ACTUALIZAR presentación existente (AJAX POST)
// ============================================================
if (isset($_POST['action']) && $_POST['action'] === 'actualizar') {
    header('Content-Type: application/json');

    try {
        $id_presentacion = intval($_POST['id_presentacion'] ?? 0);
        $titulo          = trim($_POST['titulo'] ?? '');
        $descripcion     = trim($_POST['descripcion'] ?? '');
        $momento         = trim($_POST['momento'] ?? 'antes');
        $archivos_eliminar = json_decode($_POST['archivos_eliminar'] ?? '[]', true);

        if (empty($titulo)) throw new Exception('El título es obligatorio');
        if (!in_array($momento, ['antes', 'despues', 'al_terminar'])) throw new Exception('Momento no válido');

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE presentaciones SET titulo=?, descripcion=?, momento=? WHERE id_presentacion=?");
        $stmt->execute([$titulo, $descripcion, $momento, $id_presentacion]);

        // Eliminar archivos marcados
        foreach ($archivos_eliminar as $id_archivo) {
            $stmtR = $pdo->prepare("SELECT ruta FROM presentacion_archivos WHERE id_archivo = ?");
            $stmtR->execute([$id_archivo]);
            $arch = $stmtR->fetch(PDO::FETCH_ASSOC);
            if ($arch && file_exists('../' . $arch['ruta'])) unlink('../' . $arch['ruta']);
            $pdo->prepare("DELETE FROM presentacion_archivos WHERE id_archivo = ?")->execute([$id_archivo]);
        }

        // Agregar nuevos archivos
        if (isset($_FILES['archivos']) && !empty($_FILES['archivos']['name'][0])) {
            $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'pptx', 'ppt'];
            $dir = '../uploads/presentaciones/' . $id_presentacion . '/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $stmtOrden = $pdo->prepare("SELECT MAX(orden) FROM presentacion_archivos WHERE id_presentacion = ?");
            $stmtOrden->execute([$id_presentacion]);
            $ordenBase = ($stmtOrden->fetchColumn() ?? -1) + 1;

            foreach ($_FILES['archivos']['name'] as $i => $nombre) {
                if ($_FILES['archivos']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
                if (!in_array($ext, $extensiones_permitidas)) continue;

                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) $tipo = 'imagen';
                elseif ($ext === 'pdf') $tipo = 'pdf';
                else $tipo = 'pptx';

                $nombre_unico = uniqid('pres_') . '.' . $ext;
                if (move_uploaded_file($_FILES['archivos']['tmp_name'][$i], $dir . $nombre_unico)) {
                    $ruta_db = 'uploads/presentaciones/' . $id_presentacion . '/' . $nombre_unico;
                    $stmtA = $pdo->prepare("INSERT INTO presentacion_archivos (id_presentacion, nombre_original, ruta, tipo, orden) VALUES (?, ?, ?, ?, ?)");
                    $stmtA->execute([$id_presentacion, $nombre, $ruta_db, $tipo, $ordenBase + $i]);
                }
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Presentación actualizada correctamente']);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ============================================================
// VISTA PRINCIPAL (HTML)
// ============================================================
if (!isset($_GET['id'])) { header('Location: ../index.php'); exit; }

$id_carpeta = intval($_GET['id']);

$stmt = $pdo->prepare("SELECT * FROM carpetas WHERE id_carpeta = ?");
$stmt->execute([$id_carpeta]);
$carpeta = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$carpeta) { header('Location: ../index.php'); exit; }

$stmtP = $pdo->prepare("SELECT p.*, COUNT(a.id_archivo) as num_archivos FROM presentaciones p LEFT JOIN presentacion_archivos a ON p.id_presentacion = a.id_presentacion WHERE p.id_carpeta = ? GROUP BY p.id_presentacion ORDER BY p.orden ASC");
$stmtP->execute([$id_carpeta]);
$presentaciones = $stmtP->fetchAll(PDO::FETCH_ASSOC);

$momentos_labels = ['antes' => 'Antes de los videos', 'despues' => 'Después de los videos', 'al_terminar' => 'Al terminar el cuestionario'];
$momentos_colors = ['antes' => '#17a2b8', 'despues' => '#9b7cb8', 'al_terminar' => '#28a745'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presentaciones — <?= htmlspecialchars($carpeta['nombre']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      background: #f0d5e8;
      font-family: 'Poppins', sans-serif;
      min-height: 100vh;
      padding-top: 100px;
    }

    .top-header {
      position: fixed;
      top: 0; left: 0; right: 0;
      background: linear-gradient(135deg, #b893cc, #f5a3c7);
      box-shadow: 0 2px 10px rgba(0,0,0,0.15);
      z-index: 1000;
      padding: 20px 0;
      margin: 15px;
      border-radius: 20px;
    }

    .top-header .container-fluid {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0 30px;
      gap: 15px;
      flex-wrap: wrap;
    }

    .top-header h2 {
      color: white;
      font-weight: 600;
      margin: 0;
      font-size: 1.5rem;
      flex: 1 1 auto;
      text-align: center;
      min-width: 200px;
    }

    .header-right {
      display: flex;
      align-items: center;
      gap: 15px;
      flex-wrap: wrap;
    }

    .btn-volver {
      background: white;
      border: none;
      color: #9b7cb8;
      font-weight: 500;
      border-radius: 25px;
      padding: 8px 20px;
      transition: 0.3s;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      white-space: nowrap;
    }

    .btn-volver:hover {
      background: #f8f9fa;
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0,0,0,0.2);
      color: #9b7cb8;
    }

    .container { max-width: 1000px; padding: 20px 15px; }

    .card {
      border: none;
      border-radius: 15px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      background: white;
    }

    .btn-add-video-container {
      display: flex;
      justify-content: center;
      margin: 30px 0;
    }

    .btn-add-video {
      background: linear-gradient(135deg, #f5a3c7, #9b7cb8);
      border: none;
      color: white;
      border-radius: 25px;
      padding: 15px 40px;
      font-weight: 600;
      font-size: 1.1rem;
      box-shadow: 0 4px 15px rgba(155,124,184,0.3);
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      cursor: pointer;
    }

    .btn-add-video:hover {
      background: linear-gradient(135deg, #9b7cb8, #f5a3c7);
      transform: translateY(-3px);
      box-shadow: 0 6px 20px rgba(155,124,184,0.4);
    }

    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      flex-wrap: wrap;
      gap: 10px;
    }

    .section-header h4 { color: #9b7cb8; font-weight: 600; margin: 0; }

    .badge-count {
      background: linear-gradient(135deg, #f5a3c7, #9b7cb8);
      color: white;
      border-radius: 20px;
      padding: 8px 15px;
      font-size: 0.85rem;
      font-weight: 500;
    }

    .presentacion-card {
      background: white;
      border-radius: 15px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
      border: 1px solid #f0e4f3;
      transition: all 0.3s ease;
    }

    .presentacion-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 5px 20px rgba(155,124,184,0.2);
    }

    .presentacion-card h5 { color: #9b7cb8; font-weight: 600; margin-bottom: 8px; }

    .momento-badge {
      display: inline-block;
      padding: 4px 14px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 600;
      color: white;
      margin-bottom: 12px;
    }

    .archivo-chip {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      background: linear-gradient(135deg, rgba(245,163,199,0.15), rgba(155,124,184,0.15));
      border: 1px solid #f0e4f3;
      border-radius: 20px;
      padding: 4px 12px;
      font-size: 0.8rem;
      color: #9b7cb8;
      font-weight: 500;
      margin: 3px;
    }

    .btn-secondary-custom {
      background: white;
      border: 2px solid #9b7cb8;
      color: #9b7cb8;
      font-weight: 500;
      border-radius: 25px;
      padding: 8px 20px;
      transition: 0.3s;
    }

    .btn-secondary-custom:hover { background: #9b7cb8; color: white; }

    .empty-state { text-align: center; padding: 60px 20px; color: #9b7cb8; }

    /* SweetAlert2 */
    .swal2-popup {
      border-radius: 20px !important;
      font-family: 'Poppins', sans-serif !important;
      padding: 30px !important;
      max-width: 700px !important;
    }

    .swal2-title { color: #9b7cb8 !important; font-weight: 700 !important; font-size: 1.8rem !important; }

    .swal2-confirm {
      background: linear-gradient(135deg, #f5a3c7, #9b7cb8) !important;
      border: none !important;
      border-radius: 25px !important;
      padding: 12px 35px !important;
      font-weight: 600 !important;
    }

    .swal2-confirm:hover {
      background: linear-gradient(135deg, #9b7cb8, #f5a3c7) !important;
      transform: translateY(-2px) !important;
    }

    .swal2-cancel {
      background: white !important;
      border: 2px solid #9b7cb8 !important;
      border-radius: 25px !important;
      padding: 12px 35px !important;
      font-weight: 600 !important;
      color: #9b7cb8 !important;
    }

    .swal2-input, .swal2-textarea {
      border-radius: 15px !important;
      border: 2px solid #ddd !important;
      padding: 12px 20px !important;
      font-family: 'Poppins', sans-serif !important;
    }

    .swal2-input:focus, .swal2-textarea:focus {
      border-color: #9b7cb8 !important;
      box-shadow: 0 0 0 4px rgba(155,124,184,0.15) !important;
    }

    @media (max-width: 768px) {
      body { padding-top: 140px; }
      .top-header { margin: 10px; padding: 15px 0; border-radius: 15px; }
      .top-header .container-fluid { flex-direction: column; padding: 0 15px; }
      .top-header h2 { font-size: 1.2rem; }
      .btn-volver { padding: 6px 15px; font-size: 0.9rem; }
    }

    @media (max-width: 576px) {
      body { padding-top: 160px; }
      .top-header { margin: 8px; }
      .top-header h2 { font-size: 1rem; }
      .btn-volver { width: 100%; justify-content: center; }
      .container { padding: 10px 8px; }
    }
    </style>
</head>
<body>

<div class="top-header">
  <div class="container-fluid">
    <h2><i class="fa-solid fa-file-powerpoint fa-beat"></i>  Presentaciones — <?= htmlspecialchars($carpeta['nombre']) ?></h2>
    <div class="header-right">
      <a href="carpeta.php?id=<?= $id_carpeta ?>" class="btn-volver">
        <i class="fa-solid fa-angle-left" style="color: #B197FC;"></i>
        <span>Volver</span>
      </a>
    </div>
  </div>
</div>

<div class="container">

  <div class="btn-add-video-container">
    <button id="btnAddPresentacion" class="btn-add-video">
      <span>Añadir Presentación</span>
      <i class="fa-solid fa-circle-plus"></i>
    </button>
  </div>

  <div class="section-header">
    <h4><i class="fa-solid fa-file-powerpoint" style="color:#9b7cb8;"></i> Presentaciones de <?= htmlspecialchars($carpeta['nombre']) ?>:</h4>
    <span class="badge-count">
      <?= count($presentaciones) ?> presentación<?= count($presentaciones) != 1 ? 'es' : '' ?>
    </span>
  </div>

  <?php if (count($presentaciones) > 0): ?>
    <?php foreach ($presentaciones as $p): ?>
      <div class="presentacion-card">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
          <div style="flex:1;">
            <h5><?= htmlspecialchars($p['titulo']) ?></h5>
            <span class="momento-badge" style="background: <?= $momentos_colors[$p['momento']] ?>;">
              <i class="fas fa-clock"></i> <?= $momentos_labels[$p['momento']] ?>
            </span>
            <?php if (!empty($p['descripcion'])): ?>
              <p class="text-muted mb-2" style="font-size:0.9rem;"><?= nl2br(htmlspecialchars($p['descripcion'])) ?></p>
            <?php endif; ?>

            <?php
            $stmtA = $pdo->prepare("SELECT * FROM presentacion_archivos WHERE id_presentacion = ? ORDER BY orden ASC");
            $stmtA->execute([$p['id_presentacion']]);
            $archivos = $stmtA->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <div class="mt-2">
              <?php foreach ($archivos as $a): ?>
                <?php
                $icono = match($a['tipo']) {
                  'pdf'    => '<i class="fas fa-file-pdf" style="color:#dc3545;"></i>',
                  'pptx'   => '<i class="fas fa-file-powerpoint" style="color:#d04423;"></i>',
                  default  => '<i class="fas fa-image" style="color:#17a2b8;"></i>'
                };
                ?>
                <span class="archivo-chip"><?= $icono ?> <?= htmlspecialchars($a['nombre_original']) ?></span>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="d-flex gap-2 mt-2">
            <button class="btn btn-secondary-custom btn-sm btn-edit-presentacion"
                    data-id="<?= $p['id_presentacion'] ?>">
              <i class="fas fa-edit"></i> Editar
            </button>
            <button class="btn btn-danger btn-sm btn-del-presentacion"
                    data-id="<?= $p['id_presentacion'] ?>"
                    data-titulo="<?= htmlspecialchars($p['titulo']) ?>"
                    style="border-radius:25px;">
              <i class="fas fa-trash-alt"></i> Eliminar
            </button>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="empty-state">
      <div style="font-size:3rem; margin-bottom:15px; opacity:0.3;">📊</div>
      <h5>No hay presentaciones en este módulo</h5>
      <p class="text-muted">Usa el botón de arriba para agregar una presentación</p>
    </div>
  <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function escapeHtml(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function htmlFormulario(datos = null) {
  return `
    <div style="text-align:left; max-height:65vh; overflow-y:auto; padding: 0 10px;">

      <label class="fw-bold">Título:</label>
      <input type="text" id="tituloPres" class="swal2-input"
        placeholder="Ej: Material introductorio"
        value="${escapeHtml(datos?.titulo || '')}"
        style="width:100%; margin-top:5px; margin-left:-5px;">

      <label class="fw-bold mt-3">Descripción (opcional):</label>
      <textarea id="descPres" class="swal2-textarea"
        placeholder="Descripción breve de la presentación..."
        style="width:100%; margin-left:-5px;">${escapeHtml(datos?.descripcion || '')}</textarea>

      <label class="fw-bold mt-3">¿Cuándo se muestra al alumno?</label>
      <select id="momentoPres" class="form-control mt-2" style="border-radius:15px; border:2px solid #ddd; padding:10px 20px; font-family:'Poppins',sans-serif;">
        <option value="antes"       ${datos?.momento === 'antes'       ? 'selected' : ''}>📌 Antes de los videos</option>
        <option value="despues"     ${datos?.momento === 'despues'     ? 'selected' : ''}>✅ Después de los videos</option>
        <option value="al_terminar" ${datos?.momento === 'al_terminar' ? 'selected' : ''}>🏁 Al terminar el cuestionario</option>
      </select>

      <label class="fw-bold mt-3">
        <i class="fas fa-paperclip" style="color:#9b7cb8;"></i>
        Archivos <small class="text-muted fw-normal">(imágenes, PDF, PowerPoint)</small>
      </label>
      <input type="file" id="archivosPres" class="swal2-input"
        accept=".jpg,.jpeg,.png,.gif,.pdf,.pptx,.ppt" multiple
        style="width:100%; margin-top:5px; margin-left:-5px; padding:8px;">
      <small class="text-muted d-block mt-1">
        <i class="fas fa-info-circle" style="color:#9b7cb8;"></i>
        Puedes seleccionar múltiples archivos a la vez
      </small>

      <div id="archivosExistentes"></div>
    </div>
  `;
}

// ── AÑADIR ──────────────────────────────────────────────────
document.getElementById('btnAddPresentacion').addEventListener('click', async () => {
  const { value: formValues } = await Swal.fire({
    title: '<i class="fas fa-plus-circle"></i> Añadir presentación',
    html: htmlFormulario(),
    width: '700px',
    showCancelButton: true,
    confirmButtonText: '<i class="fas fa-save"></i> Guardar',
    cancelButtonText: 'Cancelar',
    preConfirm: () => {
      const titulo   = document.getElementById('tituloPres').value.trim();
      const desc     = document.getElementById('descPres').value.trim();
      const momento  = document.getElementById('momentoPres').value;
      const archivos = document.getElementById('archivosPres').files;

      if (!titulo) { Swal.showValidationMessage('El título es obligatorio'); return false; }
      if (archivos.length === 0) { Swal.showValidationMessage('Debes subir al menos un archivo'); return false; }

      return { titulo, desc, momento, archivos };
    }
  });

  if (!formValues) return;

  Swal.fire({ title: 'Guardando...', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });

  const fd = new FormData();
  fd.append('action', 'guardar');
  fd.append('id_carpeta', <?= $id_carpeta ?>);
  fd.append('titulo', formValues.titulo);
  fd.append('descripcion', formValues.desc);
  fd.append('momento', formValues.momento);
  for (let i = 0; i < formValues.archivos.length; i++) {
    fd.append('archivos[]', formValues.archivos[i]);
  }

  try {
    const res  = await fetch('presentaciones_modulo.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      await Swal.fire({ icon: 'success', title: '¡Guardado!', text: data.message, confirmButtonText: 'Aceptar' });
      location.reload();
    } else {
      Swal.fire({ icon: 'error', title: 'Error', text: data.message });
    }
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Error de conexión', text: err.message });
  }
});

// ── EDITAR ───────────────────────────────────────────────────
document.querySelectorAll('.btn-edit-presentacion').forEach(btn => {
  btn.addEventListener('click', async () => {
    const idPres = btn.dataset.id;

    Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    const res  = await fetch(`presentaciones_modulo.php?action=obtener&id=${idPres}`);
    const data = await res.json();
    if (!data.success) { Swal.fire({ icon: 'error', title: 'Error', text: data.message }); return; }

    const presentacion    = data.presentacion;
    const archivosActuales = data.archivos || [];
    const archivosEliminar = [];

    const { value: formValues } = await Swal.fire({
      title: '<i class="fas fa-edit"></i> Editar presentación',
      html: htmlFormulario(presentacion),
      width: '700px',
      showCancelButton: true,
      confirmButtonText: '<i class="fas fa-save"></i> Guardar cambios',
      cancelButtonText: 'Cancelar',
      didOpen: () => {
        // Mostrar archivos existentes
        const cont = document.getElementById('archivosExistentes');
        if (archivosActuales.length > 0) {
          cont.innerHTML = `
            <label class="fw-bold mt-3 d-block">Archivos actuales:</label>
            <div id="listaArchivosActuales">
              ${archivosActuales.map(a => `
                <div class="d-flex align-items-center gap-2 mb-2" id="arch-${a.id_archivo}">
                  <span style="flex:1; font-size:0.85rem; color:#555;">
                    ${a.tipo === 'pdf' ? '📄' : a.tipo === 'pptx' ? '📊' : '🖼️'} ${escapeHtml(a.nombre_original)}
                  </span>
                  <button type="button" class="btn btn-sm btn-danger btn-eliminar-archivo"
                    data-id="${a.id_archivo}"
                    style="border-radius:20px; padding:3px 12px; font-size:0.8rem;">
                    Quitar
                  </button>
                </div>
              `).join('')}
            </div>
          `;

          cont.querySelectorAll('.btn-eliminar-archivo').forEach(b => {
            b.addEventListener('click', () => {
              const idArch = b.dataset.id;
              archivosEliminar.push(idArch);
              document.getElementById(`arch-${idArch}`).remove();
            });
          });
        }
      },
      preConfirm: () => {
        const titulo  = document.getElementById('tituloPres').value.trim();
        const desc    = document.getElementById('descPres').value.trim();
        const momento = document.getElementById('momentoPres').value;
        const archivos = document.getElementById('archivosPres').files;

        if (!titulo) { Swal.showValidationMessage('El título es obligatorio'); return false; }

        return { titulo, desc, momento, archivos, archivosEliminar };
      }
    });

    if (!formValues) return;

    Swal.fire({ title: 'Guardando...', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });

    const fd = new FormData();
    fd.append('action', 'actualizar');
    fd.append('id_presentacion', idPres);
    fd.append('titulo', formValues.titulo);
    fd.append('descripcion', formValues.desc);
    fd.append('momento', formValues.momento);
    fd.append('archivos_eliminar', JSON.stringify(formValues.archivosEliminar));
    for (let i = 0; i < formValues.archivos.length; i++) {
      fd.append('archivos[]', formValues.archivos[i]);
    }

    try {
      const res  = await fetch('presentaciones_modulo.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        await Swal.fire({ icon: 'success', title: '¡Actualizado!', text: data.message, confirmButtonText: 'Aceptar' });
        location.reload();
      } else {
        Swal.fire({ icon: 'error', title: 'Error', text: data.message });
      }
    } catch (err) {
      Swal.fire({ icon: 'error', title: 'Error de conexión', text: err.message });
    }
  });
});

// ── ELIMINAR ─────────────────────────────────────────────────
document.querySelectorAll('.btn-del-presentacion').forEach(btn => {
  btn.addEventListener('click', async () => {
    const id     = btn.dataset.id;
    const titulo = btn.dataset.titulo;

    const result = await Swal.fire({
      title: '¿Eliminar presentación?',
      html: `Se eliminará: <br><strong>"${escapeHtml(titulo)}"</strong><br><br>Esta acción no se puede deshacer y eliminará todos los archivos asociados.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#dc3545'
    });

    if (!result.isConfirmed) return;

    Swal.fire({ title: 'Eliminando...', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });

    const fd = new FormData();
    fd.append('action', 'eliminar');
    fd.append('id_presentacion', id);

    try {
      const res  = await fetch('presentaciones_modulo.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        await Swal.fire({ icon: 'success', title: '¡Eliminado!', text: data.message });
        location.reload();
      } else {
        Swal.fire({ icon: 'error', title: 'Error', text: data.message });
      }
    } catch (err) {
      Swal.fire({ icon: 'error', title: 'Error de conexión', text: err.message });
    }
  });
});
</script>
</body>
</html>