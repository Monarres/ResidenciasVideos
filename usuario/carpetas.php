<?php
session_start();
require_once("../conexion.php");

// Verificar sesión
if (!isset($_SESSION['id_usuario'])) { 
    header("Location: ../index.php"); 
    exit; 
}

$id_usuario = $_SESSION['id_usuario'];
$nombre = $_SESSION['nombre'] ?? "Usuario";

//  Obtener el área asignada al usuario
$id_area_usuario = $_SESSION['id_carpeta'] ?? null;

if (!$id_area_usuario) {
    echo "<h3>No tienes un área asignada.</h3>";
    exit;
}

//  Obtener el nombre del área
$stmt = $pdo->prepare("SELECT nombre FROM carpetas WHERE id_carpeta = ?");
$stmt->execute([$id_area_usuario]);
$area = $stmt->fetch(PDO::FETCH_ASSOC);
$area_usuario = $area ? $area['nombre'] : 'Área desconocida';

//  Obtener los módulos (carpetas hijas) del área asignada - ORDENADOS
$stmt = $pdo->prepare("SELECT * FROM carpetas WHERE id_padre = ? ORDER BY fecha_creacion ASC");
$stmt->execute([$id_area_usuario]);
$carpetas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$carpetas_con_progreso = [];
$modulo_anterior_completo = true; // El primer módulo siempre está desbloqueado

//  Calcular progreso por módulo
foreach ($carpetas as $index => $carpeta) {
    $id_carpeta = $carpeta['id_carpeta'];

    // Videos del módulo
    $stmt = $pdo->prepare("SELECT id_video FROM videos WHERE id_carpeta = ?");
    $stmt->execute([$id_carpeta]);
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_videos = count($videos);
    $videos_completados = 0;

    // ✅ SI NO HAY VIDEOS, el módulo se considera completado automáticamente
    if ($total_videos == 0) {
        $porcentaje = 100;
        $completo = true;
        $bloqueado = false; // Los módulos sin videos NUNCA se bloquean
        
        $carpetas_con_progreso[] = [
            'carpeta' => $carpeta,
            'total_videos' => 0,
            'videos_completados' => 0,
            'porcentaje' => 100,
            'completo' => true,
            'bloqueado' => false,
            'numero_modulo' => $index + 1,
            'sin_videos' => true
        ];
        
        // El módulo sin videos NO afecta el desbloqueo del siguiente
        // $modulo_anterior_completo sigue siendo true
        continue;
    }

    // SI HAY VIDEOS, calcular progreso normal
    foreach ($videos as $video) {
        $id_video = $video['id_video'];

        // Contar total de preguntas por tipo
        $stmt = $pdo->prepare("SELECT 
                              SUM(CASE WHEN tipo_pregunta = 'incisos' THEN 1 ELSE 0 END) as total_incisos,
                              SUM(CASE WHEN tipo_pregunta = 'archivo' THEN 1 ELSE 0 END) as total_archivos
                              FROM cuestionarios WHERE id_video = ?");
        $stmt->execute([$id_video]);
        $totales = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_incisos = $totales['total_incisos'] ?? 0;
        $total_archivos = $totales['total_archivos'] ?? 0;

        // Contar respuestas de INCISOS del usuario
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM respuestas_usuario ru
            JOIN cuestionarios q ON ru.id_cuestionario = q.id_cuestionario
            WHERE ru.id_usuario = ? AND q.id_video = ? AND q.tipo_pregunta = 'incisos'
        ");
        $stmt->execute([$id_usuario, $id_video]);
        $respuestas_incisos = $stmt->fetchColumn();

        // Contar respuestas de ARCHIVOS del usuario
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM respuestas_archivo ra
            JOIN cuestionarios q ON ra.id_cuestionario = q.id_cuestionario
            WHERE ra.id_usuario = ? AND q.id_video = ? AND q.tipo_pregunta = 'archivo'
        ");
        $stmt->execute([$id_usuario, $id_video]);
        $respuestas_archivos = $stmt->fetchColumn();

        // Video completado si respondió TODAS las preguntas de ambos tipos
        $completo_incisos = ($respuestas_incisos >= $total_incisos);
        $completo_archivos = ($respuestas_archivos >= $total_archivos);
        
        if ($completo_incisos && $completo_archivos) {
            $videos_completados++;
        }
    }

    $porcentaje = $total_videos > 0 ? round(($videos_completados / $total_videos) * 100) : 0;
    $completo = ($total_videos > 0 && $videos_completados >= $total_videos);
    
    //  Determinar si el módulo está bloqueado (solo si tiene videos)
    $bloqueado = !$modulo_anterior_completo;

    $carpetas_con_progreso[] = [
        'carpeta' => $carpeta,
        'total_videos' => $total_videos,
        'videos_completados' => $videos_completados,
        'porcentaje' => $porcentaje,
        'completo' => $completo,
        'bloqueado' => $bloqueado,
        'numero_modulo' => $index + 1,
        'sin_videos' => false
    ];
    
    // Actualizar estado para el siguiente módulo
    $modulo_anterior_completo = $completo;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Módulos de Capacitación</title>
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
  padding-bottom: 40px;
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

.user-section {
  display: flex;
  align-items: center;
  gap: 10px;
  position: relative;
}

.user-toggle {
  background: rgba(255, 255, 255, 0.2);
  border: 2px solid white;
  color: white;
  font-weight: 500;
  border-radius: 25px;
  padding: 8px 20px;
  transition: 0.3s;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.user-toggle:hover {
  background: white;
  color: #9b7cb8;
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.user-dropdown {
  position: absolute;
  top: 100%;
  right: 0;
  margin-top: 10px;
  background: white;
  border-radius: 15px;
  box-shadow: 0 5px 20px rgba(155, 124, 184, 0.3);
  min-width: 200px;
  padding: 10px;
  z-index: 10000;
  display: none;
}

.user-dropdown.show {
  display: block;
}

.user-dropdown-item {
  padding: 12px 20px;
  transition: 0.3s;
  border-radius: 10px;
  margin-bottom: 5px;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
  color: #333;
  cursor: pointer;
}

.user-dropdown-item:last-child {
  margin-bottom: 0;
}

.user-dropdown-item:hover {
  background: linear-gradient(135deg, rgba(245, 163, 199, 0.2), rgba(155, 124, 184, 0.2));
  transform: translateX(5px);
  color: #9b7cb8;
}

.user-dropdown-item.logout {
  color: #dc3545;
  background: linear-gradient(135deg, rgba(220, 53, 69, 0.05), rgba(245, 163, 199, 0.1));
}

.user-dropdown-item.logout:hover {
  background: linear-gradient(135deg, rgba(220, 53, 69, 0.15), rgba(245, 163, 199, 0.2));
  color: #dc3545;
}

.container {
  max-width: 1200px;
  padding: 20px 15px;
}

.area-badge {
  display: inline-block;
  background: linear-gradient(135deg, rgba(245, 163, 199, 0.3), rgba(155, 124, 184, 0.3));
  color: #9b7cb8;
  padding: 10px 25px;
  border-radius: 25px;
  font-weight: 600;
  margin-bottom: 30px;
  border: 2px solid #9b7cb8;
}

.modulos-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 25px;
  margin-top: 30px;
}

.modulo-card {
  background: white;
  border: none;
  border-radius: 20px;
  box-shadow: 0 5px 20px rgba(155, 124, 184, 0.2);
  transition: all 0.3s ease;
  overflow: hidden;
  position: relative;
}

.modulo-card:hover {
  transform: translateY(-10px);
  box-shadow: 0 10px 30px rgba(155, 124, 184, 0.3);
}

.modulo-card.completado {
  border: 3px solid #28a745;
}

.modulo-card.sin-videos {
  border: 3px solid #17a2b8;
  background: linear-gradient(135deg, rgba(23, 162, 184, 0.05), rgba(155, 124, 184, 0.05));
}

.modulo-card.bloqueado {
  background: #e0e0e0;
  opacity: 0.6;
  pointer-events: none;
  position: relative;
}

.modulo-card.bloqueado:hover {
  transform: none;
  box-shadow: 0 5px 20px rgba(155, 124, 184, 0.2);
}

.modulo-card.bloqueado .modulo-header {
  background: linear-gradient(135deg, #9e9e9e, #757575);
}

.modulo-card.bloqueado .btn-modulo {
  background: #9e9e9e;
  cursor: not-allowed;
}

.badge-bloqueado {
  position: absolute;
  top: 15px;
  right: 15px;
  background: #757575;
  color: white;
  padding: 8px 15px;
  border-radius: 20px;
  font-weight: 600;
  font-size: 0.85rem;
  z-index: 10;
}

.badge-sin-videos {
  position: absolute;
  top: 15px;
  right: 15px;
  background: #17a2b8;
  color: white;
  padding: 8px 15px;
  border-radius: 20px;
  font-weight: 600;
  font-size: 0.85rem;
  z-index: 10;
}

.modulo-header {
  background: linear-gradient(135deg, #f5a3c7, #9b7cb8);
  padding: 25px;
  text-align: center;
  color: white;
  position: relative;
}

.modulo-numero {
  position: absolute;
  top: 10px;
  left: 15px;
  background: rgba(255, 255, 255, 0.3);
  color: white;
  width: 35px;
  height: 35px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 1.1rem;
}

.modulo-icon {
  font-size: 60px;
  margin-bottom: 10px;
}

.modulo-nombre {
  font-size: 1.3rem;
  font-weight: 700;
  margin: 0;
}

.modulo-body {
  padding: 25px;
}

.progreso-info {
  margin-bottom: 20px;
}

.progreso-texto {
  display: flex;
  justify-content: space-between;
  margin-bottom: 10px;
  color: #666;
  font-size: 0.9rem;
}

.progress {
  height: 10px;
  border-radius: 10px;
  background: #f0f0f0;
}

.progress-bar {
  background: linear-gradient(135deg, #f5a3c7, #9b7cb8);
  border-radius: 10px;
  transition: width 0.3s ease;
}

.videos-count {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 15px 0;
  color: #666;
  font-size: 0.95rem;
}

.videos-count i {
  color: #9b7cb8;
}

.mensaje-sin-videos {
  background: linear-gradient(135deg, rgba(23, 162, 184, 0.1), rgba(155, 124, 184, 0.1));
  border: 2px solid #17a2b8;
  border-radius: 15px;
  padding: 15px;
  margin-top: 15px;
  text-align: center;
}

.mensaje-sin-videos p {
  margin: 0;
  color: #0c7b8c;
  font-weight: 600;
  font-size: 0.9rem;
}

.btn-modulo {
  width: 100%;
  background: linear-gradient(135deg, #f5a3c7, #9b7cb8);
  border: none;
  color: white;
  font-weight: 600;
  border-radius: 25px;
  padding: 12px;
  font-size: 1rem;
  transition: 0.3s;
  text-decoration: none;
  display: inline-block;
  text-align: center;
}

.btn-modulo:hover {
  background: linear-gradient(135deg, #9b7cb8, #f5a3c7);
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(155, 124, 184, 0.4);
  color: white;
}

.btn-modulo.completado {
  background: linear-gradient(135deg, #28a745, #20c997);
}

.btn-modulo.completado:hover {
  background: linear-gradient(135deg, #20c997, #28a745);
}

.badge-completado {
  position: absolute;
  top: 15px;
  right: 15px;
  background: #28a745;
  color: white;
  padding: 8px 15px;
  border-radius: 20px;
  font-weight: 600;
  font-size: 0.85rem;
  z-index: 10;
}

.mensaje-bloqueado {
  background: linear-gradient(135deg, rgba(255, 152, 0, 0.1), rgba(255, 193, 7, 0.1));
  border: 2px solid #ff9800;
  border-radius: 15px;
  padding: 15px;
  margin-top: 15px;
  text-align: center;
}

.mensaje-bloqueado p {
  margin: 0;
  color: #e65100;
  font-weight: 600;
  font-size: 0.9rem;
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
  background: white;
  border-radius: 20px;
  box-shadow: 0 5px 20px rgba(155, 124, 184, 0.2);
}

.empty-state .icon {
  font-size: 100px;
  margin-bottom: 20px;
  opacity: 0.5;
}

.empty-state h3 {
  color: #9b7cb8;
  font-weight: 700;
  margin-bottom: 15px;
}

.empty-state p {
  color: #666;
  font-size: 1.1rem;
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
  
  .header-right {
    gap: 10px;
  }
  
  .btn-volver, .user-toggle {
    padding: 6px 15px;
    font-size: 0.9rem;
  }
  
  .modulos-grid {
    grid-template-columns: 1fr;
  }
  
  .area-badge {
    font-size: 0.9rem;
    padding: 8px 20px;
  }
}
</style>
</head>
<body>

<div class="top-header">
  <div class="container-fluid">
    <h2><i class="fa-solid fa-book"></i> Módulos de Capacitación</h2>
    <div class="header-right">
      <!-- Menú de usuario -->
      <div class="user-section">
        <button class="user-toggle" id="userToggle">
          <i class="fa-solid fa-user"></i> <?= htmlspecialchars($nombre) ?> <span style="font-size: 0.8em;">▼</span>
        </button>
        <div class="user-dropdown" id="userDropdown">
          <a href="../logout.php" class="user-dropdown-item logout">
            <i class="fa-solid fa-door-open"></i> Cerrar sesión
          </a>
        </div>
      </div>
      
      <a href="dashboard.php" class="btn-volver"><i class="fa-solid fa-angle-left"></i> Volver</a>
    </div>
  </div>
</div>

<div class="container">
  
  <!-- Badge del área -->
  <div class="text-center">
    <span class="area-badge">
       Área: <?= htmlspecialchars($area_usuario) ?>
    </span>
  </div>

  <!-- Grid de módulos -->
  <?php if (count($carpetas_con_progreso) > 0): ?>
    <div class="modulos-grid">
      <?php foreach($carpetas_con_progreso as $item): ?>
        <?php 
          $carpeta = $item['carpeta'];
          $total = $item['total_videos'];
          $completados = $item['videos_completados'];
          $porcentaje = $item['porcentaje'];
          $completo = $item['completo'];
          $bloqueado = $item['bloqueado'];
          $numero_modulo = $item['numero_modulo'];
          $sin_videos = $item['sin_videos'];
        ?>
        
        <div class="modulo-card <?= $completo ? 'completado' : '' ?> <?= $bloqueado ? 'bloqueado' : '' ?> <?= $sin_videos ? 'sin-videos' : '' ?>">
          
          <?php if ($sin_videos): ?>
            <span class="badge-sin-videos"><i class="fa-solid fa-circle-info"></i> Sin contenido</span>
          <?php elseif ($completo): ?>
            <span class="badge-completado"><i class="fa-solid fa-circle-check"></i> Completado</span>
          <?php elseif ($bloqueado): ?>
            <span class="badge-bloqueado"><i class="fa-solid fa-lock"></i> Bloqueado</span>
          <?php endif; ?>
          
          <div class="modulo-header">
            <div class="modulo-numero"><?= $numero_modulo ?></div>
            <div class="modulo-icon">
              <?php if ($bloqueado): ?>
                <i class="fa-solid fa-lock"></i>
              <?php else: ?>
                <i class="fa-solid fa-book"></i>
              <?php endif; ?>
            </div>
            <h3 class="modulo-nombre"><?= htmlspecialchars($carpeta['nombre']) ?></h3>
          </div>
          
          <div class="modulo-body">
            
            <?php if ($sin_videos): ?>
              <!-- Mensaje de módulo sin videos -->
              <div class="mensaje-sin-videos">
                <p><i class="fa-solid fa-circle-info"></i> Este módulo no tiene contenido disponible actualmente</p>
              </div>
            <?php else: ?>
              <!-- Información de progreso -->
              <div class="progreso-info">
                <div class="progreso-texto">
                  <span>Progreso:</span>
                  <span><strong><?= $porcentaje ?>%</strong></span>
                </div>
                <div class="progress">
                  <div class="progress-bar" role="progressbar" 
                       style="width: <?= $porcentaje ?>%" 
                       aria-valuenow="<?= $porcentaje ?>" 
                       aria-valuemin="0" 
                       aria-valuemax="100">
                  </div>
                </div>
              </div>
              
              <!-- Contador de videos -->
              <div class="videos-count">
                <i class="fa-solid fa-video"></i>
                <span><?= $completados ?> de <?= $total ?> videos completados</span>
              </div>
              
              <?php if ($bloqueado): ?>
                <!-- Mensaje de módulo bloqueado -->
                <div class="mensaje-bloqueado">
                  <p>⚠️ Completa el módulo anterior para desbloquear</p>
                </div>
              <?php else: ?>
                <!-- Botón de acción -->
                <a href="videos_usuario.php?id_carpeta=<?= $carpeta['id_carpeta'] ?>" 
                   class="btn-modulo <?= $completo ? 'completado' : '' ?>">
                  <?php if ($completo): ?>
                    <i class="fa-solid fa-circle-check"></i> Revisar Módulo
                  <?php else: ?>
                    <?= $completados > 0 ? 'Continuar Módulo →' : 'Comenzar Módulo →' ?>
                  <?php endif; ?>
                </a>
              <?php endif; ?>
            <?php endif; ?>
            
          </div>
        </div>
        
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <!-- Estado vacío -->
    <div class="empty-state">
      <div class="icon">📭</div>
      <h3>No hay módulos disponibles</h3>
      <p>Aún no se han asignado módulos para tu área.</p>
    </div>
  <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle del menú de usuario
const userToggle = document.getElementById('userToggle');
const userDropdown = document.getElementById('userDropdown');

userToggle.addEventListener('click', function(e) {
  e.stopPropagation();
  userDropdown.classList.toggle('show');
});

// Cerrar el menú al hacer clic fuera
document.addEventListener('click', function(e) {
  if (!userToggle.contains(e.target) && !userDropdown.contains(e.target)) {
    userDropdown.classList.remove('show');
  }
});

// Animación de las barras de progreso al cargar
document.addEventListener('DOMContentLoaded', function() {
  const progressBars = document.querySelectorAll('.progress-bar');
  
  progressBars.forEach(bar => {
    const width = bar.style.width;
    bar.style.width = '0%';
    
    setTimeout(() => {
      bar.style.width = width;
    }, 200);
  });
});

// Mensaje de felicitación si se completa un módulo
<?php 
$modulos_completados = array_filter($carpetas_con_progreso, function($item) {
  return $item['completo'] && !$item['sin_videos'];
});

if (count($modulos_completados) > 0 && isset($_GET['completado'])):
?>
Swal.fire({
  icon: 'success',
  title: '¡Felicitaciones!',
  text: 'Has completado un módulo de capacitación.',
  confirmButtonText: 'Continuar',
  timer: 3000
});
<?php endif; ?>

// Prevenir clicks en módulos bloqueados con mensaje
document.querySelectorAll('.modulo-card.bloqueado').forEach(card => {
  card.addEventListener('click', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    Swal.fire({
      icon: 'warning',
      title: 'Módulo Bloqueado',
      html: 'Debes completar el módulo anterior antes de acceder a este contenido.',
      confirmButtonText: 'Entendido',
      confirmButtonColor: '#9b7cb8'
    });
  });
});
</script>
</body>
</html>