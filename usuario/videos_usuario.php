<?php
session_start();
require_once("../conexion.php");

if(!isset($_SESSION['id_usuario'])){ 
    header("Location: ../index.php"); 
    exit; 
}

$id_usuario = $_SESSION['id_usuario'];
$nombre = $_SESSION['nombre'] ?? "Usuario";
$id_carpeta_usuario = $_SESSION['id_carpeta']; // Área asignada al usuario

// Obtener videos de la carpeta del usuario Y de sus subcarpetas (módulos)
$stmt = $pdo->prepare("
    SELECT v.*, c.nombre AS carpeta_nombre 
    FROM videos v 
    JOIN carpetas c ON v.id_carpeta = c.id_carpeta 
    WHERE v.id_carpeta = ? 
       OR c.id_padre = ?
    ORDER BY v.id_video ASC
");
$stmt->execute([$id_carpeta_usuario, $id_carpeta_usuario]);
$videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Encontrar el primer video no completado
$video_actual = null;
foreach($videos as $v) {
    // Contar preguntas totales del video por tipo
    $stmt2 = $pdo->prepare("SELECT 
                            SUM(CASE WHEN tipo_pregunta = 'incisos' THEN 1 ELSE 0 END) as total_incisos,
                            SUM(CASE WHEN tipo_pregunta = 'archivo' THEN 1 ELSE 0 END) as total_archivos,
                            COUNT(*) as total_preguntas
                            FROM cuestionarios WHERE id_video=?");
    $stmt2->execute([$v['id_video']]);
    $totales = $stmt2->fetch(PDO::FETCH_ASSOC);
    
    $total_incisos = $totales['total_incisos'] ?? 0;
    $total_archivos = $totales['total_archivos'] ?? 0;
    $total_preguntas = $totales['total_preguntas'] ?? 0;
    
    // Si el video no tiene preguntas, se considera completado automáticamente
    if ($total_preguntas == 0) {
        continue;
    }
    
    // Contar respuestas de INCISOS del usuario para este video
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT ru.id_cuestionario) as cnt 
                           FROM respuestas_usuario ru 
                           JOIN cuestionarios q ON ru.id_cuestionario=q.id_cuestionario 
                           WHERE ru.id_usuario=? AND q.id_video=? AND q.tipo_pregunta='incisos'");
    $stmt->execute([$id_usuario, $v['id_video']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $respondidas_incisos = $result['cnt'] ?? 0;
    
    // Contar respuestas de ARCHIVOS del usuario para este video
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT ra.id_cuestionario) as cnt 
                           FROM respuestas_archivo ra 
                           JOIN cuestionarios q ON ra.id_cuestionario=q.id_cuestionario 
                           WHERE ra.id_usuario=? AND q.id_video=? AND q.tipo_pregunta='archivo'");
    $stmt->execute([$id_usuario, $v['id_video']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $respondidas_archivos = $result['cnt'] ?? 0;
    
    // LÓGICA CORREGIDA: Un video está completo cuando:
    // - Si tiene incisos: todos están respondidos
    // - Si tiene archivos: todos están subidos
    $completo_incisos = ($total_incisos > 0) ? ($respondidas_incisos >= $total_incisos) : true;
    $completo_archivos = ($total_archivos > 0) ? ($respondidas_archivos >= $total_archivos) : true;
    $completo = $completo_incisos && $completo_archivos;
    
  
    
    if (!$completo) {
        $video_actual = $v;
        $video_actual['total_preguntas'] = $total_preguntas;
        $video_actual['respondidas_incisos'] = $respondidas_incisos;
        $video_actual['respondidas_archivos'] = $respondidas_archivos;
        $video_actual['total_incisos'] = $total_incisos;
        $video_actual['total_archivos'] = $total_archivos;
        break;
    }
}

// Obtener TODAS las preguntas del video actual
$preguntas = [];
if ($video_actual) {
    $stmtQ = $pdo->prepare("SELECT * FROM cuestionarios WHERE id_video=? ORDER BY id_cuestionario ASC");
    $stmtQ->execute([$video_actual['id_video']]);
    $preguntas = $stmtQ->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Videos de Capacitación</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  background: #f0d5e8;
  font-family: 'Poppins', sans-serif;
  min-height: 100vh;
  padding-top: 100px;
  padding-bottom: 50px;
}

.top-header {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
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
  position: relative;
}

.top-header h2 {
  color: white;
  font-weight: 600;
  margin: 0;
  font-size: 1.5rem;
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
}

.header-right {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-left: auto;
}

.user-section {
  position: relative;
}

.user-toggle {
  background: rgba(255, 255, 255, 0.2);
  border: 2px solid white;
  color: white;
  font-weight: 500;
  border-radius: 25px;
  padding: 8px 20px;
  cursor: pointer;
  display: inline-flex;
  gap: 8px;
  transition: 0.3s;
}

.user-toggle:hover {
  background: rgba(255, 255, 255, 0.3);
}

.user-dropdown {
  position: absolute;
  top: 100%;
  right: 0;
  margin-top: 10px;
  background: white;
  border-radius: 15px;
  box-shadow: 0 5px 20px rgba(155,124,184,0.3);
  min-width: 200px;
  padding: 10px;
  display: none;
  z-index: 10001;
}

.user-dropdown.show {
  display: block;
}

.user-dropdown-item {
  padding: 12px 20px;
  border-radius: 10px;
  font-weight: 500;
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 10px;
  transition: 0.3s;
  color: #dc3545;
}

.user-dropdown-item:hover {
  background: #f8f9fa;
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
  display: inline-block;
}

.btn-volver:hover {
  background: #f8f9fa;
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(0,0,0,0.2);
  color: #9b7cb8;
}

.container {
  max-width: 1000px;
  padding: 20px 15px;
}

.card {
  border: none;
  border-radius: 20px;
  box-shadow: 0 5px 20px rgba(155, 124, 184, 0.2);
  background: white;
  margin-bottom: 30px;
}

video {
  border-radius: 15px;
  background: #000;
  width: 100%;
}

.video-header {
  background: linear-gradient(135deg, #f5a3c7, #9b7cb8);
  color: white;
  padding: 20px;
  border-radius: 20px 20px 0 0;
}

.video-header h3 {
  margin: 0;
  font-weight: 600;
}

.video-header small {
  opacity: 0.9;
}

.btn-cuestionario {
  background: linear-gradient(135deg, #f5a3c7, #9b7cb8);
  border: none;
  color: white;
  font-weight: 600;
  border-radius: 25px;
  padding: 15px 40px;
  font-size: 1.1rem;
  transition: 0.3s;
  display: none;
}

.btn-cuestionario:hover {
  background: linear-gradient(135deg, #9b7cb8, #f5a3c7);
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(155, 124, 184, 0.4);
}

.cuestionario-container {
  display: none;
  padding: 30px;
  background: #fff;
}

.cuestionario-header {
  text-align: center;
  margin-bottom: 30px;
  padding: 20px;
  background: linear-gradient(135deg, rgba(245, 163, 199, 0.2), rgba(155, 124, 184, 0.2));
  border-radius: 15px;
}

.cuestionario-header h4 {
  color: #9b7cb8;
  font-weight: 700;
  margin-bottom: 10px;
}

.cuestionario-header p {
  color: #666;
  margin: 0;
}

.pregunta-card {
  background: linear-gradient(135deg, rgba(245, 163, 199, 0.1), rgba(155, 124, 184, 0.1));
  border-radius: 15px;
  padding: 25px;
  margin-bottom: 25px;
  border-left: 5px solid #9b7cb8;
}

.pregunta-card h5 {
  color: #9b7cb8;
  font-weight: 600;
  margin-bottom: 20px;
  font-size: 1.1rem;
}

.form-check {
  padding: 12px;
  border-radius: 10px;
  transition: 0.3s;
  margin-bottom: 10px;
  background: white;
}

.form-check:hover {
  background: rgba(155, 124, 184, 0.1);
  transform: translateX(5px);
}

.form-check-input {
  cursor: pointer;
  width: 20px;
  height: 20px;
}

.form-check-label {
  cursor: pointer;
  margin-left: 10px;
  font-size: 1rem;
}

.form-check-input:checked {
  background-color: #9b7cb8;
  border-color: #9b7cb8;
}

.btn-enviar {
  background: linear-gradient(135deg, #28a745, #20c997);
  border: none;
  color: white;
  font-weight: 600;
  border-radius: 25px;
  padding: 15px 50px;
  font-size: 1.1rem;
  transition: 0.3s;
}

.btn-enviar:hover {
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
}

.btn-primary-custom {
  background: linear-gradient(135deg, #f5a3c7, #9b7cb8);
  border: none;
  color: white;
  font-weight: 600;
  border-radius: 25px;
  padding: 12px 40px;
  font-size: 1.1rem;
  transition: 0.3s;
  text-decoration: none;
  display: inline-block;
}

.btn-primary-custom:hover {
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(155, 124, 184, 0.4);
  color: white;
}

.completado-card {
  text-align: center;
  padding: 60px 20px;
}

.completado-card .icon {
  font-size: 100px;
  margin-bottom: 20px;
}

.completado-card h3 {
  color: #9b7cb8;
  font-weight: 700;
  margin-bottom: 15px;
}

.video-info {
  background: rgba(155, 124, 184, 0.1);
  border-radius: 15px;
  padding: 15px;
  margin-top: 20px;
  text-align: center;
}

.video-info p {
  margin: 5px 0;
  color: #9b7cb8;
  font-weight: 500;
}

.file-upload-container {
  background: white;
  padding: 20px;
  border-radius: 10px;
  margin-top: 15px;
}

.file-upload-container .form-control {
  cursor: pointer;
  transition: 0.3s;
}

.file-upload-container .form-control:hover {
  border-color: #f5a3c7;
  background: rgba(155, 124, 184, 0.05);
}

.file-upload-container .form-control:focus {
  border-color: #9b7cb8;
  box-shadow: 0 0 0 0.2rem rgba(155, 124, 184, 0.25);
}

.file-name-preview {
  margin-top: 10px;
  padding: 10px;
  background: rgba(155, 124, 184, 0.1);
  border-radius: 8px;
  border-left: 4px solid #9b7cb8;
  display: none;
}

.file-name-preview i {
  margin-right: 8px;
  color: #9b7cb8;
}

.file-name-preview.duplicate {
  background: rgba(255, 107, 107, 0.1);
  border-left-color: #ff6b6b;
}

.file-name-preview.duplicate i {
  color: #ff6b6b;
}

.swal2-popup {
  border-radius: 20px !important;
  font-family: 'Poppins', sans-serif !important;
  padding: 30px !important;
  background: #ffffff !important;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2) !important;
}

.swal2-title {
  color: #9b7cb8 !important;
  font-weight: 700 !important;
}

.swal2-confirm {
  background: linear-gradient(135deg, #f5a3c7, #9b7cb8) !important;
  border: none !important;
  border-radius: 25px !important;
  padding: 12px 35px !important;
  font-weight: 600 !important;
}

.swal2-cancel {
  background: white !important;
  border: 2px solid #9b7cb8 !important;
  border-radius: 25px !important;
  padding: 12px 35px !important;
  color: #9b7cb8 !important;
  font-weight: 600 !important;
}

@media (max-width: 768px) {
  body {
    padding-top: 90px;
  }
  
  .top-header {
    margin: 10px;
    border-radius: 15px;
  }
  
  .top-header h2 {
    font-size: 1.2rem;
  }
  
  .btn-volver, .user-toggle {
    padding: 6px 15px;
    font-size: 0.9rem;
  }
  
  .pregunta-card h5 {
    font-size: 1rem;
  }
  
  .pregunta-card {
    padding: 15px;
  }
}
  </style>
</head>
<body>
  
<div class="top-header">
  <div class="container-fluid">
    <h2><i class="fa-solid fa-video"></i> Videos de Capacitación</h2>
    <div class="header-right">
      <div class="user-section">
        <button class="user-toggle" id="userToggle">
          <i class="fa-solid fa-user"></i> <?= htmlspecialchars($nombre) ?> <span style="font-size: 0.8em;"><i class="fa-solid fa-caret-down"></i></span>
        </button>
        <div class="user-dropdown" id="userDropdown">
          <a href="../logout.php" class="user-dropdown-item logout">
            <i class="fa-solid fa-door-open"></i> Cerrar sesión
          </a>
        </div>
      </div>
      <a href="carpetas.php" class="btn-volver"><i class="fa-solid fa-angle-left"></i> Volver</a>
    </div>
  </div>
</div>

<div class="container">
  <?php if ($video_actual): ?>
    <div class="card">
      <div class="video-header">
        <h3><?= htmlspecialchars($video_actual['titulo']) ?></h3>
        <small><i class="fa-solid fa-book"></i> <?= htmlspecialchars($video_actual['carpeta_nombre']) ?></small>
      </div>
      
      <div class="card-body" id="videoContainer">
        <?php if ($video_actual['tipo_video'] === 'youtube' || strpos($video_actual['ruta'], 'youtube.com') !== false): ?>
  <!-- Video de YouTube -->
  <iframe id="videoPlayer" 
          src="<?= htmlspecialchars($video_actual['ruta']) ?>" 
          width="100%" 
          height="500"
          style="border-radius: 15px; border: none;"
          frameborder="0"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
          allowfullscreen>
  </iframe>
<?php else: ?>
  <!-- Video local -->
  <video id="videoPlayer" controls controlsList="nodownload" preload="metadata">
    <source src="../<?= htmlspecialchars($video_actual['ruta']) ?>" type="video/mp4">
    Tu navegador no soporta el elemento de video.
  </video>
<?php endif; ?>
        
        <div class="video-info">
          <p><i class="fa-solid fa-stopwatch"></i> <strong>Instrucción:</strong> Debes ver el video completo para poder acceder al cuestionario</p>
          <?php if (count($preguntas) == 0): ?>
            <p>¡Este video no tiene cuestionario asociado!</p>
          <?php endif; ?>
        </div>
        
        <div class="text-center mt-4">
          <button id="btnMostrarCuestionario" class="btn btn-cuestionario">
            Contestar Cuestionario
          </button>
        </div>
      </div>
      
      <div id="cuestionarioContainer" class="cuestionario-container">
        <?php if (count($preguntas) > 0): ?>
          <div class="cuestionario-header">
            <h4>Cuestionario del Video</h4>
          </div>
          
          <form id="formCuestionario" method="post" action="guardar_respuestas.php" enctype="multipart/form-data">
            <?php 
            $numero_pregunta = 1;
            foreach($preguntas as $p): 
              $tipo_pregunta = $p['tipo_pregunta'] ?? 'incisos';
            ?>
              <div class="pregunta-card" data-pregunta="<?= $numero_pregunta ?>" data-tipo="<?= $tipo_pregunta ?>">
                <h5>
                  <span style="background: #9b7cb8; color: white; padding: 5px 12px; border-radius: 50%; margin-right: 10px;">
                    <?= $numero_pregunta ?>
                  </span>
                  <?= htmlspecialchars($p['pregunta']) ?>
                </h5>
                
                <?php if ($tipo_pregunta === 'archivo'): ?>
                  <?php if (!empty($p['instrucciones_archivo'])): ?>
                    <div class="alert alert-info" style="background: rgba(155, 124, 184, 0.1); border-left: 4px solid #9b7cb8; padding: 15px; margin-bottom: 20px;">
                      <strong><i class="fa-solid fa-thumbtack"></i> Instrucciones:</strong><br>
                      <?= nl2br(htmlspecialchars($p['instrucciones_archivo'])) ?>
                    </div>
                  <?php endif; ?>
                  
                  <div class="file-upload-container">
                    <label class="form-label" style="font-weight: 600; color: #9b7cb8;">
                      Sube tu archivo:
                    </label>
                    <input type="file" 
                           class="form-control file-input" 
                           name="archivo[<?= $p['id_cuestionario'] ?>]" 
                           id="archivo_<?= $p['id_cuestionario'] ?>"
                           accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip"
                           required
                           data-cuestionario-id="<?= $p['id_cuestionario'] ?>"
                           style="border: 2px dashed #9b7cb8; padding: 10px;">
                    <small class="form-text text-muted">
                      Formatos permitidos: PDF, Word, Imágenes (JPG, PNG), ZIP. Máximo 10MB
                    </small>
                    <div class="file-name-preview" id="preview_<?= $p['id_cuestionario'] ?>">
                      <i class="fa-solid fa-file"></i>
                      <span class="filename-text"></span>
                    </div>
                  </div>
                <?php else: ?>
                  <div class="opciones-container">
                    <?php
                    $opciones = json_decode($p['opciones_json'], true);
                    
                    if ($opciones && is_array($opciones)) {
                      foreach ($opciones as $letra => $texto) {
                        $id_input = "q" . $p['id_cuestionario'] . strtolower($letra);
                        ?>
                        <div class="form-check">
                          <input class="form-check-input" type="radio" 
                                 name="resp[<?= $p['id_cuestionario'] ?>]" 
                                 value="<?= htmlspecialchars($letra) ?>" 
                                 id="<?= $id_input ?>" 
                                 required>
                          <label class="form-check-label" for="<?= $id_input ?>">
                            <strong><?= htmlspecialchars($letra) ?>)</strong> <?= htmlspecialchars($texto) ?>
                          </label>
                        </div>
                        <?php
                      }
                    } else {
                      ?>
                      <div class="alert alert-danger">
                        <strong>Error:</strong> Esta pregunta no tiene opciones configuradas.
                      </div>
                      <?php
                    }
                    ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php 
            $numero_pregunta++;
            endforeach; 
            ?>
            
            <input type="hidden" name="id_video" value="<?= $video_actual['id_video'] ?>">
            <input type="hidden" name="id_carpeta" value="<?= $video_actual['id_carpeta'] ?>">
            
            <div class="text-center mt-4">
              <p style="color: #666; margin-bottom: 20px;">
                ¡Asegúrate de responder todas las preguntas antes de enviar!
              </p>
              <button type="submit" class="btn btn-enviar">
                Enviar Respuestas
              </button>
            </div>
          </form>
        <?php else: ?>
          <div class="alert alert-warning text-center">
            <h5>Sin Cuestionario</h5>
            <p>Este video no tiene cuestionario asociado. Puedes continuar con el siguiente video.</p>
            <a href="videos_usuario.php" class="btn btn-primary-custom mt-3">
              Continuar
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="card">
      <div class="card-body completado-card">
        <div class="icon">🎉</div>
        <h3>¡Felicitaciones!</h3>
        <p class="text-muted">Has completado todos los videos y cuestionarios disponibles.</p>
        <a href="carpetas.php" class="btn btn-primary-custom mt-3">
          ← Volver
        </a>
      </div>
    </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const archivosSeleccionados = new Map();

document.querySelectorAll('.file-input').forEach(input => {
  input.addEventListener('change', function(e) {
    const file = e.target.files[0];
    const cuestionarioId = this.getAttribute('data-cuestionario-id');
    const preview = document.getElementById('preview_' + cuestionarioId);
    
    if (!file) {
      preview.style.display = 'none';
      archivosSeleccionados.delete(cuestionarioId);
      return;
    }
    
    const fileName = file.name;
    const fileSize = (file.size / 1024 / 1024).toFixed(2);
    
    let isDuplicate = false;
    for (let [id, name] of archivosSeleccionados) {
      if (id !== cuestionarioId && name === fileName) {
        isDuplicate = true;
        break;
      }
    }
    
    preview.style.display = 'block';
    preview.querySelector('.filename-text').textContent = `${fileName} (${fileSize} MB)`;
    
    if (isDuplicate) {
      preview.classList.add('duplicate');
      preview.querySelector('.filename-text').innerHTML = `<strong>ADVERTENCIA:</strong> Ya seleccionaste un archivo con este nombre en otra pregunta<br>${fileName} (${fileSize} MB)`;
      
      Swal.fire({
        title: ' Archivo Duplicado',
        html: `Ya has seleccionado un archivo con el nombre:<br><br><strong>${fileName}</strong><br><br>¿Estás seguro de que quieres usar el mismo archivo dos veces?<br><br><small style="color: #666;">Si fue un error, por favor selecciona un archivo diferente.</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, usar este archivo',
        cancelButtonText: 'Cambiar archivo',
        confirmButtonColor: '#ff9800',
        cancelButtonColor: '#9b7cb8'
      }).then((result) => {
        if (!result.isConfirmed) {
          e.target.value = '';
          preview.style.display = 'none';
          archivosSeleccionados.delete(cuestionarioId);
        } else {
          archivosSeleccionados.set(cuestionarioId, fileName);
        }
      });
    } else {
      preview.classList.remove('duplicate');
      archivosSeleccionados.set(cuestionarioId, fileName);
    }
  });
});

<?php if (isset($_SESSION['resultado_cuestionario'])): ?>
  const resultado = <?= json_encode($_SESSION['resultado_cuestionario']) ?>;
  
  if (resultado.tipo === 'archivo') {
    Swal.fire({
      title: 'Respuestas Enviadas',
      html: `
        <div style="text-align: center; padding: 20px;">
          <div style="font-size: 4rem; margin: 20px 0;"><i class="fa-solid fa-clipboard-list"></i></div>
          <h3 style="color: #9b7cb8; margin-bottom: 20px;">Tu calificación será revisada</h3>
          <p style="color: #666; font-size: 1.1rem;">
            Tus respuestas han sido enviadas correctamente.<br>
            Un administrador revisará tus archivos y asignará tu calificación.
          </p>
        </div>
      `,
      icon: 'info',
      confirmButtonText: 'Entendido',
      allowOutsideClick: false,
      customClass: {
        confirmButton: 'btn-enviar'
      }
    });
  } else {
    const aprobado = resultado.porcentaje >= 70;
    const color = aprobado ? '#28a745' : '#ff6b6b';
    
    Swal.fire({
      title: aprobado ? '¡Felicidades!' : 'Resultado 📝',
      html: `
        <div style="text-align: center; padding: 20px;">
          <h3 style="color: #9b7cb8; margin-bottom: 20px;">Resultados del Cuestionario</h3>
          
          <div style="font-size: 4rem; margin: 20px 0; color: ${color}; font-weight: bold;">
            ${resultado.porcentaje}%
          </div>
          
          <div style="margin-top: 30px; padding: 20px; background: ${aprobado ? 'rgba(40, 167, 69, 0.1)' : 'rgba(255, 107, 107, 0.1)'}; border-radius: 10px; border-left: 4px solid ${color};">
            <strong style="font-size: 1.2rem; color: ${color};">
              ${aprobado ? '¡APROBADO!' : 'NO APROBADO'}
            </strong>
            <p style="margin-top: 10px; color: #666;">
              ${aprobado ? 'Has superado el 70% mínimo requerido' : 'Necesitas al menos 70% para aprobar'}
            </p>
          </div>
        </div>
      `,
      icon: aprobado ? 'success' : 'warning',
      confirmButtonText: 'Continuar',
      allowOutsideClick: false,
      customClass: {
        confirmButton: 'btn-enviar'
      }
    });
  }
  
  <?php unset($_SESSION['resultado_cuestionario']); ?>
<?php endif; ?>

<?php if (isset($_GET['modulo_completado'])): ?>
  Swal.fire({
    title: '🎉 ¡Módulo Completado!',
    text: 'Has finalizado todos los videos de este módulo.',
    icon: 'success',
    confirmButtonText: 'Continuar'
  });
<?php endif; ?>

// Declarar variables GLOBALES para que YouTube API pueda accederlas
window.videoCompletado = false;
window.tienePreguntas = <?= count($preguntas) > 0 ? 'true' : 'false' ?>;
const totalPreguntas = <?= count($preguntas) ?>;
const esYoutube = <?= isset($video_actual) && ($video_actual['tipo_video'] === 'youtube' || strpos($video_actual['ruta'], 'youtube.com') !== false) ? 'true' : 'false' ?>;

console.log('Total de preguntas cargadas:', totalPreguntas);
console.log('Es video de YouTube:', esYoutube);

const videoPlayer = document.getElementById('videoPlayer');
const videoContainer = document.getElementById('videoContainer');
const cuestionarioContainer = document.getElementById('cuestionarioContainer');

// Guardar referencia al botón GLOBALMENTE
window.btnCuestionario = document.getElementById('btnMostrarCuestionario');
console.log('Botón encontrado:', window.btnCuestionario);

if (videoPlayer) {
  if (esYoutube) {
    console.log('=== INICIALIZANDO VIDEO DE YOUTUBE ===');
    
    // Cargar YouTube IFrame API
    if (typeof YT === 'undefined' || typeof YT.Player === 'undefined') {
      const tag = document.createElement('script');
      tag.src = "https://www.youtube.com/iframe_api";
      const firstScriptTag = document.getElementsByTagName('script')[0];
      firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
    }

    window.onYouTubeIframeAPIReady = function() {
      console.log('YouTube API cargada');
      console.log('Botón disponible en callback:', window.btnCuestionario);
      
      const iframeSrc = videoPlayer.src;
      const videoId = iframeSrc.split('/embed/')[1].split('?')[0];
      console.log('Video ID:', videoId);
      
      const playerDiv = document.createElement('div');
      playerDiv.id = 'ytplayer';
      videoPlayer.parentNode.replaceChild(playerDiv, videoPlayer);
      
      const player = new YT.Player('ytplayer', {
        height: '500',
        width: '100%',
        videoId: videoId,
        playerVars: {
          'playsinline': 1,
          'rel': 0,
          'modestbranding': 1
        },
        events: {
          'onStateChange': function(event) {
            console.log('Estado:', event.data);
            
            if (event.data === 0) { // Video terminado
              console.log('¡VIDEO TERMINADO!');
              window.videoCompletado = true;
              
              console.log('Accediendo a botón global:', window.btnCuestionario);
              console.log('Tiene preguntas:', window.tienePreguntas);
              
              if (window.tienePreguntas && window.btnCuestionario) {
                window.btnCuestionario.style.display = 'inline-block';
                console.log('✅ Botón mostrado exitosamente');
                
                Swal.fire({
                  title: '¡Video Completado!',
                  text: 'Ahora puedes contestar el cuestionario',
                  icon: 'success',
                  confirmButtonText: 'Entendido'
                });
              } else {
                console.error('❌ ERROR: No se puede mostrar el botón');
                console.log('- tienePreguntas:', window.tienePreguntas);
                console.log('- btnCuestionario:', window.btnCuestionario);
              }
            }
          }
        }
      });
    };
    
  } else {
    // Videos locales
    const btnMostrarCuestionario = window.btnCuestionario;
    
    videoPlayer.addEventListener('ended', function() {
      window.videoCompletado = true;
      if (window.tienePreguntas && btnMostrarCuestionario) {
        btnMostrarCuestionario.style.display = 'inline-block';
        
        Swal.fire({
          title: 'Video Completado',
          text: 'Ahora puedes contestar el cuestionario',
          icon: 'success',
          confirmButtonText: 'Entendido'
        });
      }
    });

    videoPlayer.addEventListener('timeupdate', function() {
      if (!window.videoCompletado && window.tienePreguntas) {
        const porcentaje = (videoPlayer.currentTime / videoPlayer.duration) * 100;
        if (porcentaje >= 95) {
          window.videoCompletado = true;
          if (btnMostrarCuestionario) {
            btnMostrarCuestionario.style.display = 'inline-block';
          }
        }
      }
    });
  }
}


// HACER CLICKEABLE TODA LA OPCIÓN
document.querySelectorAll('.form-check').forEach(function(formCheck) {
  const radioInput = formCheck.querySelector('input[type="radio"]');
  
  if (!radioInput) return;
  
  formCheck.addEventListener('click', function(e) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'LABEL') {
      return;
    }
    
    radioInput.checked = true;
    const event = new Event('change', { bubbles: true });
    radioInput.dispatchEvent(event);
  });
  
  radioInput.addEventListener('change', function() {
    if (this.checked) {
      const contenedor = formCheck.closest('.opciones-container');
      if (contenedor) {
        contenedor.querySelectorAll('.form-check').forEach(opcion => {
          opcion.classList.remove('selected');
        });
      }
      formCheck.classList.add('selected');
    }
  });
});

const formCuestionario = document.getElementById('formCuestionario');
let formularioEnviando = false;

if (formCuestionario) {
  formCuestionario.addEventListener('submit', function(e) {
    if (formularioEnviando) {
      return true;
    }
    
    e.preventDefault();
    
    const preguntasCards = formCuestionario.querySelectorAll('.pregunta-card');
    let preguntasSinResponder = [];
    let hayDuplicados = false;
    
    const nombresArchivos = [];
    document.querySelectorAll('.file-input').forEach(input => {
      if (input.files && input.files[0]) {
        const fileName = input.files[0].name;
        if (nombresArchivos.includes(fileName)) {
          hayDuplicados = true;
        }
        nombresArchivos.push(fileName);
      }
    });
    
    preguntasCards.forEach((card, index) => {
      const tipoPregunta = card.getAttribute('data-tipo');
      
      if (tipoPregunta === 'archivo') {
        const fileInput = card.querySelector('input[type="file"]');
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
          preguntasSinResponder.push(index + 1);
        }
      } else {
        const radioChecked = card.querySelector('input[type="radio"]:checked');
        if (!radioChecked) {
          preguntasSinResponder.push(index + 1);
        }
      }
    });
    
    if (preguntasSinResponder.length > 0) {
      Swal.fire({
        title: 'Preguntas sin responder',
        html: 'Te falta responder ' + preguntasSinResponder.length + ' pregunta' + (preguntasSinResponder.length > 1 ? 's' : '') + '.<br><br>Pregunta' + (preguntasSinResponder.length > 1 ? 's' : '') + ': <strong>' + preguntasSinResponder.join(', ') + '</strong>',
        icon: 'warning',
        confirmButtonText: 'Entendido'
      });
      
      const primeraSinResponder = formCuestionario.querySelector('.pregunta-card[data-pregunta="' + preguntasSinResponder[0] + '"]');
      if (primeraSinResponder) {
        primeraSinResponder.scrollIntoView({ behavior: 'smooth', block: 'center' });
        primeraSinResponder.style.border = '3px solid #ff6b6b';
        setTimeout(() => {
          primeraSinResponder.style.border = '';
        }, 2000);
      }
      return false;
    }
    
    if (hayDuplicados) {
      Swal.fire({
        title: '⚠️ Archivos Duplicados Detectados',
        html: 'Has seleccionado archivos con el mismo nombre en diferentes preguntas.<br><br><strong>¿Estás seguro de continuar?</strong><br><br><small>Verifica que hayas seleccionado los archivos correctos para cada pregunta.</small>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, enviar',
        cancelButtonText: 'Revisar archivos',
        confirmButtonColor: '#ff9800',
        cancelButtonColor: '#9b7cb8'
      }).then((result) => {
        if (result.isConfirmed) {
          enviarFormulario();
        }
      });
      return false;
    }
    
    Swal.fire({
      title: '¿Enviar respuestas?',
      html: 'Una vez enviadas no podrás modificarlas.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, enviar',
      cancelButtonText: 'Revisar de nuevo'
    }).then((result) => {
      if (result.isConfirmed) {
        enviarFormulario();
      }
    });
    
    return false;
  });
}

function enviarFormulario() {
  permitirSalida = true;
  
  Swal.fire({
    title: 'Enviando...',
    text: 'Por favor espera',
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading();
    }
  });
  
  formularioEnviando = true;
  
  // DEBUG: Verificar qué se está enviando
  console.log('=== ENVIANDO FORMULARIO ===');
  const formData = new FormData(formCuestionario);
  for (let pair of formData.entries()) {
    console.log(pair[0] + ': ' + pair[1]);
  }
  console.log('=========================');
  
  formCuestionario.submit();
}
// Usar la variable global para el botón
if (window.btnCuestionario) {
  window.btnCuestionario.addEventListener('click', function() {
    if (!window.videoCompletado) {
      Swal.fire({
        title: 'Video Incompleto',
        text: 'Debes ver el video completo antes de acceder al cuestionario.',
        icon: 'warning',
        confirmButtonText: 'Entendido'
      });
      return;
    }

    videoContainer.style.display = 'none';
    cuestionarioContainer.style.display = 'block';

    setTimeout(() => {
      cuestionarioContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 100);

    Swal.fire({
      title: 'Cuestionario',
      html: 'Responde todas las preguntas antes de enviar',
      icon: 'info',
      timer: 3000,
      showConfirmButton: false
    });
  });
}
const userToggle = document.getElementById('userToggle');
const userDropdown = document.getElementById('userDropdown');

if (userToggle && userDropdown) {
  userToggle.addEventListener('click', function(e) {
    e.stopPropagation();
    userDropdown.classList.toggle('show');
  });

  document.addEventListener('click', function(e) {
    if (!userToggle.contains(e.target) && !userDropdown.contains(e.target)) {
      userDropdown.classList.remove('show');
    }
  });

  userDropdown.addEventListener('click', function(e) {
    e.stopPropagation();
  });
}

let permitirSalida = false;

window.addEventListener('beforeunload', function(e) {
  if (cuestionarioContainer && cuestionarioContainer.style.display === 'block' && !permitirSalida) {
    e.preventDefault();
    e.returnValue = '';
  }
});
</script>
</body>
</html>