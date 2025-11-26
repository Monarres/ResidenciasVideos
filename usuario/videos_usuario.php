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
    
    // El video está completo si respondió TODAS las preguntas (incisos + archivos)
    $completo_incisos = ($respondidas_incisos >= $total_incisos);
    $completo_archivos = ($respondidas_archivos >= $total_archivos);
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
    <h2>🎬 Videos de Capacitación</h2>
    <div class="header-right">
      <!-- Menú de usuario -->
      <div class="user-section">
        <button class="user-toggle" id="userToggle">
          <span>👤</span> <?= htmlspecialchars($nombre) ?> <span style="font-size: 0.8em;">▼</span>
        </button>
        <div class="user-dropdown" id="userDropdown">
          <a href="../logout.php" class="user-dropdown-item logout">
            <span>🚪</span> Cerrar sesión
          </a>
        </div>
      </div>
      <!-- Botón Volver -->
      <a href="carpetas.php" class="btn-volver">⬅ Volver</a>
    </div>
  </div>
</div>

<div class="container">
  <?php if ($video_actual): ?>
    <div class="card">
      <div class="video-header">
        <h3><?= htmlspecialchars($video_actual['titulo']) ?></h3>
        <small>📚 <?= htmlspecialchars($video_actual['carpeta_nombre']) ?></small>
      </div>
      
      <div class="card-body" id="videoContainer">
        <video id="videoPlayer" controls controlsList="nodownload" preload="metadata">
          <source src="../<?= htmlspecialchars($video_actual['ruta']) ?>" type="video/mp4">
          Tu navegador no soporta el elemento de video.
        </video>
        
        <div class="video-info">
          <p>⏱️ <strong>Instrucción:</strong> Debes ver el video completo para poder acceder al cuestionario</p>
          <?php if (count($preguntas) > 0): ?>
            <?php if ($video_actual['total_archivos'] > 0): ?>
            <?php endif; ?>
          <?php else: ?>
            <p>⚠️ Este video no tiene cuestionario asociado</p>
          <?php endif; ?>
        </div>
        
        <div class="text-center mt-4">
          <button id="btnMostrarCuestionario" class="btn btn-cuestionario">
            📝 Contestar Cuestionario
          </button>
        </div>
      </div>
      
      <!-- Formulario de cuestionario -->
      <div id="cuestionarioContainer" class="cuestionario-container">
        <?php if (count($preguntas) > 0): ?>
          <div class="cuestionario-header">
            <h4>📝 Cuestionario del Video</h4>
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
                  <!-- Pregunta tipo archivo -->
                  <?php if (!empty($p['instrucciones_archivo'])): ?>
                    <div class="alert alert-info" style="background: rgba(155, 124, 184, 0.1); border-left: 4px solid #9b7cb8; padding: 15px; margin-bottom: 20px;">
                      <strong>📌 Instrucciones:</strong><br>
                      <?= nl2br(htmlspecialchars($p['instrucciones_archivo'])) ?>
                    </div>
                  <?php endif; ?>
                  
                  <div class="file-upload-container">
                    <label class="form-label" style="font-weight: 600; color: #9b7cb8;">
                      📎 Sube tu archivo:
                    </label>
                    <input type="file" 
                           class="form-control" 
                           name="archivo[<?= $p['id_cuestionario'] ?>]" 
                           id="archivo_<?= $p['id_cuestionario'] ?>"
                           accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip"
                           required
                           style="border: 2px dashed #9b7cb8; padding: 10px;">
                    <small class="form-text text-muted">
                      Formatos permitidos: PDF, Word, Imágenes (JPG, PNG), ZIP. Máximo 10MB
                    </small>
                  </div>
                  <?php else: ?>
  <!-- Pregunta tipo multiple choice con OPCIONES DINÁMICAS -->
  <div class="opciones-container">
    <?php
    // Intentar cargar opciones desde JSON (nuevo formato)
    $opciones = null;
    if (!empty($p['opciones_json'])) {
      $opciones = json_decode($p['opciones_json'], true);
    }
    
    if ($opciones && is_array($opciones)) {
      // NUEVO FORMATO: Mostrar opciones dinámicas desde JSON
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
      // FORMATO VIEJO: Compatibilidad con preguntas antiguas (A, B, C fijos)
      if (!empty($p['opcion_a'])) {
        ?>
        <div class="form-check">
          <input class="form-check-input" type="radio" 
                 name="resp[<?= $p['id_cuestionario'] ?>]" 
                 value="A" 
                 id="q<?= $p['id_cuestionario'] ?>a" 
                 required>
          <label class="form-check-label" for="q<?= $p['id_cuestionario'] ?>a">
            <strong>A)</strong> <?= htmlspecialchars($p['opcion_a']) ?>
          </label>
        </div>
        <?php
      }
      
      if (!empty($p['opcion_b'])) {
        ?>
        <div class="form-check">
          <input class="form-check-input" type="radio" 
                 name="resp[<?= $p['id_cuestionario'] ?>]" 
                 value="B" 
                 id="q<?= $p['id_cuestionario'] ?>b" 
                 required>
          <label class="form-check-label" for="q<?= $p['id_cuestionario'] ?>b">
            <strong>B)</strong> <?= htmlspecialchars($p['opcion_b']) ?>
          </label>
        </div>
        <?php
      }
      
      if (!empty($p['opcion_c'])) {
        ?>
        <div class="form-check">
          <input class="form-check-input" type="radio" 
                 name="resp[<?= $p['id_cuestionario'] ?>]" 
                 value="C" 
                 id="q<?= $p['id_cuestionario'] ?>c" 
                 required>
          <label class="form-check-label" for="q<?= $p['id_cuestionario'] ?>c">
            <strong>C)</strong> <?= htmlspecialchars($p['opcion_c']) ?>
          </label>
        </div>
        <?php
      }
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
                ⚠️ Asegúrate de responder todas las preguntas antes de enviar
              </p>
              <button type="submit" class="btn btn-enviar">
                ✅ Enviar Respuestas (<?= count($preguntas) ?> preguntas)
              </button>
            </div>
          </form>
        <?php else: ?>
          <div class="alert alert-warning text-center">
            <h5>⚠️ Sin Cuestionario</h5>
            <p>Este video no tiene cuestionario asociado. Puedes continuar con el siguiente video.</p>
            <a href="videos_usuario.php" class="btn btn-primary-custom mt-3">
              ➡️ Continuar
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
// MOSTRAR RESULTADO DEL CUESTIONARIO
<?php if (isset($_SESSION['resultado_cuestionario'])): ?>
  const resultado = <?= json_encode($_SESSION['resultado_cuestionario']) ?>;
  
  if (resultado.tipo === 'archivo') {
    Swal.fire({
      title: '📋 Respuestas Enviadas',
      html: `
        <div style="text-align: center; padding: 20px;">
          <div style="font-size: 4rem; margin: 20px 0;">📝</div>
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
      title: aprobado ? '¡Felicidades! 🎉' : 'Resultado 📝',
      html: `
        <div style="text-align: center; padding: 20px;">
          <h3 style="color: #9b7cb8; margin-bottom: 20px;">Resultados del Cuestionario</h3>
          
          <div style="font-size: 4rem; margin: 20px 0; color: ${color}; font-weight: bold;">
            ${resultado.porcentaje}%
          </div>
          
          <div style="margin-top: 30px; padding: 20px; background: ${aprobado ? 'rgba(40, 167, 69, 0.1)' : 'rgba(255, 107, 107, 0.1)'}; border-radius: 10px; border-left: 4px solid ${color};">
            <strong style="font-size: 1.2rem; color: ${color};">
              ${aprobado ? '✅ ¡APROBADO!' : '❌ NO APROBADO'}
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

// CONTROL DEL VIDEO Y CUESTIONARIO
const videoPlayer = document.getElementById('videoPlayer');
const btnMostrarCuestionario = document.getElementById('btnMostrarCuestionario');
const videoContainer = document.getElementById('videoContainer');
const cuestionarioContainer = document.getElementById('cuestionarioContainer');

let videoCompletado = false;
const tienePreguntas = <?= count($preguntas) > 0 ? 'true' : 'false' ?>;
const totalPreguntas = <?= count($preguntas) ?>;

console.log('Total de preguntas cargadas:', totalPreguntas);

videoPlayer.addEventListener('ended', function() {
  videoCompletado = true;
  if (tienePreguntas) {
    btnMostrarCuestionario.style.display = 'inline-block';
    
    Swal.fire({
      title: '✅ Video Completado',
      text: 'Ahora puedes contestar el cuestionario',
      icon: 'success',
      confirmButtonText: 'Entendido'
    });
  } else {
    Swal.fire({
      title: '✅ Video Completado',
      text: 'Este video no tiene cuestionario. ¿Deseas continuar?',
      icon: 'success',
      showCancelButton: true,
      confirmButtonText: 'Continuar',
      cancelButtonText: 'Ver de nuevo'
    }).then((result) => {
      if (result.isConfirmed) {
        window.location.href = 'videos_usuario.php';
      }
    });
  }
});

videoPlayer.addEventListener('timeupdate', function() {
  if (!videoCompletado && tienePreguntas) {
    const porcentaje = (videoPlayer.currentTime / videoPlayer.duration) * 100;
    if (porcentaje >= 95) {
      videoCompletado = true;
      btnMostrarCuestionario.style.display = 'inline-block';
    }
  }
});

if (btnMostrarCuestionario) {
  btnMostrarCuestionario.addEventListener('click', function() {
    if (!videoCompletado) {
      Swal.fire({
        title: '⚠️ Video Incompleto',
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
      title: '📝 Cuestionario',
      html: 'Responde todas las preguntas antes de enviar',
      icon: 'info',
      timer: 3000,
      showConfirmButton: false
    });
  });
}

// VALIDACIÓN DEL FORMULARIO
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
        title: '⚠️ Preguntas sin responder',
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
    
    Swal.fire({
      title: '¿Enviar respuestas?',
      html: 'Estás enviando las respuestas de <strong>' + totalPreguntas + ' preguntas</strong>.<br><br>Una vez enviadas no podrás modificarlas.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, enviar',
      cancelButtonText: 'Revisar de nuevo'
    }).then((result) => {
      if (result.isConfirmed) {
        permitirSalida = true;
        
        Swal.fire({
          title: 'Enviando...',
          text: 'Por favor espera',
          allowOutsideClick: false,
          allowEscapeKey: false,
          showConfirmButton: false,
          willOpen: () => {
            Swal.showLoading();
          }
        });
        
        formularioEnviando = true;
        formCuestionario.submit();
      }
    });
    
    return false;
  });
}

// MENÚ DE USUARIO
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

// PREVENIR RECARGA ACCIDENTAL
let permitirSalida = false;

window.addEventListener('beforeunload', function(e) {
  if (cuestionarioContainer.style.display === 'block' && !permitirSalida) {
    e.preventDefault();
    e.returnValue = '';
  }
});
</script>
</body>
</html> 
