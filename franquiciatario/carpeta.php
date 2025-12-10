<?php
session_start();
require_once("../conexion.php");

// Verificar franquiciatario
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'franquiciatario') {
    header("Location: ../index.php");
    exit;
}

$id_carpeta = $_GET['id'] ?? null;
if (!$id_carpeta) { 
    header("Location: dashboard.php"); 
    exit; 
}

// Obtener nombre del módulo
$stmt = $pdo->prepare("SELECT * FROM carpetas WHERE id_carpeta = ?");
$stmt->execute([$id_carpeta]);
$carpeta = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$carpeta) die("Módulo no encontrado");

// Obtener videos con sus cuestionarios
$stmt = $pdo->prepare("SELECT v.*, 
    (SELECT COUNT(*) FROM cuestionarios WHERE id_video = v.id_video) as num_preguntas
    FROM videos v 
    WHERE v.id_carpeta = ? 
    ORDER BY v.id_video DESC");
$stmt->execute([$id_carpeta]);
$videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Módulo <?= htmlspecialchars($carpeta['nombre']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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

    .header-actions {
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
      z-index: 1001;
    }

    .user-dropdown.show {
      display: block;
    }

    .user-dropdown-item {
      padding: 12px 20px;
      border-radius: 10px;
      font-weight: 500;
      text-decoration: none;
      color: #dc3545;
      display: flex;
      align-items: center;
      gap: 10px;
      transition: 0.3s;
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
      max-width: 1200px;
      padding: 20px 15px;
    }

    .card {
      border: none;
      border-radius: 15px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      background: white;
      overflow: visible;
      position: relative;
    }

    /* Badge de solo lectura */
    .readonly-badge {
      background: linear-gradient(135deg, #ffd700, #ffed4e);
      color: #856404;
      padding: 12px 25px;
      border-radius: 25px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      margin: 30px 0;
      box-shadow: 0 2px 10px rgba(255, 215, 0, 0.3);
      font-size: 1.1rem;
    }

    .video-card {
      transition: all 0.3s ease;
      margin-bottom: 30px;
    }

    .video-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 5px 20px rgba(155, 124, 184, 0.3);
    }

    .video-card h5 {
      color: #9b7cb8;
      font-weight: 600;
    }

    .video-descripcion {
      color: #666;
      font-size: 0.9rem;
      margin-bottom: 15px;
      padding: 10px;
      background: linear-gradient(135deg, rgba(245, 163, 199, 0.05), rgba(155, 124, 184, 0.05));
      border-radius: 10px;
      border-left: 3px solid #9b7cb8;
    }

    video {
      border-radius: 10px;
      background: #000;
    }

    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: #9b7cb8;
    }

    .empty-state .icon {
      font-size: 80px;
      opacity: 0.3;
    }

    .badge {
      background: linear-gradient(135deg, #f5a3c7, #9b7cb8);
      font-weight: 500;
      padding: 8px 15px;
      border-radius: 20px;
    }

    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    .section-header h4 {
      color: #9b7cb8;
      font-weight: 600;
      margin: 0;
    }

    .cuestionario-badge {
      display: inline-block;
      padding: 5px 15px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
      margin-top: 10px;
    }

    .cuestionario-badge.completo {
      background: linear-gradient(135deg, #28a745, #20c997);
      color: white;
    }

    .cuestionario-badge.pendiente {
      background: linear-gradient(135deg, #ffc107, #ff9800);
      color: white;
    }

    .preguntas-container {
      background: white;
      border-radius: 15px;
      padding: 20px;
      max-height: 500px;
      overflow-y: auto;
      border: 2px solid #f0e4f3;
    }

    .preguntas-container::-webkit-scrollbar {
      width: 8px;
    }

    .preguntas-container::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 10px;
    }

    .preguntas-container::-webkit-scrollbar-thumb {
      background: #9b7cb8;
      border-radius: 10px;
    }

    .pregunta-card {
      background: linear-gradient(135deg, rgba(245, 163, 199, 0.1), rgba(155, 124, 184, 0.1));
      border: 1px solid #f0e4f3;
      border-radius: 12px;
      padding: 15px;
      margin-bottom: 12px;
    }

    @media (max-width: 768px) {
      body {
        padding-top: 90px;
      }

      .top-header {
        margin: 10px;
        border-radius: 15px;
      }

      .top-header .container-fluid {
        padding: 0 15px;
      }

      .top-header h2 {
        font-size: 1.2rem;
      }

      .btn-volver {
        padding: 6px 15px;
        font-size: 0.9rem;
      }
    }
    /* ========== RESPONSIVO ========== */

/* Tablets y dispositivos medianos */
@media (max-width: 992px) {
  .top-header h2 {
    font-size: 1.3rem;
  }
  
  .btn-volver {
    padding: 6px 15px;
    font-size: 0.9rem;
  }
  
  .user-toggle {
    padding: 6px 15px;
    font-size: 0.9rem;
  }
  
  .header-actions {
    gap: 10px;
  }
  
  .readonly-badge {
    padding: 10px 20px;
    font-size: 1rem;
  }
  
  .video-card h5 {
    font-size: 1.1rem;
  }
  
  .section-header h4 {
    font-size: 1.2rem;
  }
}

/* Móviles */
@media (max-width: 768px) {
  body {
    padding-top: 140px;
  }
  
  .top-header {
    margin: 10px;
    padding: 10px 0;
    border-radius: 15px;
  }
  
  .top-header .container-fluid {
    flex-direction: column;
    padding: 0 15px;
    gap: 10px;
  }
  
  .top-header h2 {
    font-size: 1.1rem;
    position: static;
    transform: none;
    text-align: center;
    width: 100%;
  }
  
  .top-header h2 i {
    font-size: 1rem;
  }
  
  .header-actions {
    justify-content: center;
    width: 100%;
    margin-left: 0;
  }
  
  .btn-volver {
    padding: 6px 12px;
    font-size: 0.8rem;
  }
  
  .user-toggle {
    padding: 6px 12px;
    font-size: 0.8rem;
    gap: 5px;
  }
  
  .user-dropdown {
    right: auto;
    left: 50%;
    transform: translateX(-50%);
    min-width: 180px;
  }
  
  .user-dropdown-item {
    padding: 10px 15px;
    font-size: 0.85rem;
    gap: 8px;
  }
  
  .container {
    padding: 15px 10px;
  }
  
  /* Badge de solo lectura */
  .readonly-badge {
    padding: 8px 15px;
    font-size: 0.9rem;
    gap: 6px;
    margin: 20px 0;
  }
  
  /* Section header */
  .section-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 15px;
  }
  
  .section-header h4 {
    font-size: 1.1rem;
  }
  
  .badge {
    font-size: 0.8rem;
    padding: 6px 12px;
  }
  
  /* Video cards */
  .video-card {
    margin-bottom: 20px;
  }
  
  .video-card .card-body {
    padding: 15px;
  }
  
  .video-card h5 {
    font-size: 1rem;
    margin-bottom: 10px;
  }
  
  .video-descripcion {
    font-size: 0.85rem;
    padding: 8px;
    margin-bottom: 12px;
  }
  
  /* Videos en columna única */
  .video-card .row {
    flex-direction: column;
  }
  
  .video-card .col-md-6 {
    width: 100%;
    max-width: 100%;
    margin-bottom: 15px;
  }
  
  .video-card .col-md-6:last-child {
    margin-bottom: 0;
  }
  
  /* Ajustar altura mínima del cuestionario privado */
  .video-card .col-md-6 .d-flex {
    min-height: 200px !important;
  }
  
  .video-card .col-md-6 .d-flex div[style*="font-size: 60px"] {
    font-size: 40px !important;
  }
  
  .video-card .col-md-6 h5 {
    font-size: 1rem;
  }
  
  /* Cuestionario badges */
  .cuestionario-badge {
    padding: 4px 12px;
    font-size: 0.75rem;
    margin-top: 8px;
  }
  
  /* Empty state */
  .empty-state {
    padding: 40px 15px;
  }
  
  .empty-state .icon {
    font-size: 60px;
  }
  
  .empty-state h4 {
    font-size: 1.2rem;
  }
  
  /* Preguntas container */
  .preguntas-container {
    padding: 15px;
    max-height: 400px;
  }
  
  .pregunta-card {
    padding: 12px;
    margin-bottom: 10px;
  }
}

/* Móviles pequeños */
@media (max-width: 576px) {
  body {
    padding-top: 150px;
  }
  
  .top-header h2 {
    font-size: 1rem;
  }
  
  .top-header h2 i {
    font-size: 0.9rem;
  }
  
  .btn-volver {
    font-size: 0.75rem;
    padding: 5px 10px;
  }
  
  .user-toggle {
    font-size: 0.75rem;
    padding: 5px 10px;
  }
  
  .user-toggle i:last-child {
    display: none; /* Ocultar flecha */
  }
  
  .header-actions {
    gap: 8px;
  }
  
  .user-dropdown {
    min-width: 160px;
  }
  
  .user-dropdown-item {
    padding: 8px 12px;
    font-size: 0.8rem;
  }
  
  .readonly-badge {
    padding: 6px 12px;
    font-size: 0.8rem;
  }
  
  .section-header h4 {
    font-size: 1rem;
  }
  
  .badge {
    font-size: 0.75rem;
    padding: 5px 10px;
  }
  
  /* Video cards más compactas */
  .video-card .card-body {
    padding: 12px;
  }
  
  .video-card h5 {
    font-size: 0.95rem;
  }
  
  .video-descripcion {
    font-size: 0.8rem;
    padding: 6px;
  }
  
  video {
    border-radius: 8px;
  }
  
  /* Cuestionario privado más compacto */
  .video-card .col-md-6 .d-flex {
    min-height: 180px !important;
  }
  
  .video-card .col-md-6 .d-flex div[style*="font-size: 60px"] {
    font-size: 35px !important;
    margin-bottom: 10px !important;
  }
  
  .video-card .col-md-6 h5 {
    font-size: 0.95rem;
    margin-bottom: 8px !important;
  }
  
  .video-card .col-md-6 p {
    font-size: 0.8rem !important;
  }
  
  .video-card .col-md-6 small {
    font-size: 0.75rem !important;
  }
  
  .cuestionario-badge {
    font-size: 0.7rem;
    padding: 3px 10px;
  }
  
  /* Alert más compacto */
  .alert {
    padding: 10px;
    font-size: 0.85rem;
  }
  
  /* Empty state */
  .empty-state {
    padding: 30px 10px;
  }
  
  .empty-state .icon {
    font-size: 50px;
  }
  
  .empty-state h4 {
    font-size: 1.1rem;
  }
  
  .empty-state p {
    font-size: 0.9rem;
  }
}

/* Móviles muy pequeños (320px) */
@media (max-width: 400px) {
  body {
    padding-top: 160px;
  }
  
  .top-header {
    margin: 8px;
  }
  
  .top-header h2 {
    font-size: 0.95rem;
  }
  
  .header-actions {
    flex-wrap: wrap;
  }
  
  .btn-volver,
  .user-toggle {
    font-size: 0.7rem;
    padding: 5px 8px;
  }
  
  .readonly-badge {
    font-size: 0.75rem;
    padding: 5px 10px;
  }
  
  .section-header h4 {
    font-size: 0.95rem;
  }
  
  .video-card h5 {
    font-size: 0.9rem;
  }
  
  .video-descripcion {
    font-size: 0.75rem;
  }
  
  .video-card .col-md-6 .d-flex div[style*="font-size: 60px"] {
    font-size: 30px !important;
  }
}

/* Orientación horizontal en móviles */
@media (max-width: 768px) and (orientation: landscape) {
  body {
    padding-top: 100px;
  }
  
  .top-header {
    padding: 8px 0;
  }
  
  .top-header .container-fluid {
    flex-direction: row;
    justify-content: space-between;
  }
  
  .top-header h2 {
    font-size: 1rem;
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    width: auto;
  }
  
  .header-actions {
    position: static;
    width: auto;
    margin-left: auto;
  }
  
  .user-dropdown {
    right: 0;
    left: auto;
    transform: none;
  }
  
  /* Videos en grid de 2 columnas en landscape */
  .video-card .row {
    flex-direction: row;
  }
  
  .video-card .col-md-6 {
    width: 50%;
    margin-bottom: 0;
  }
  
  .video-card .col-md-6 .d-flex {
    min-height: 250px !important;
  }
}

/* Accesibilidad táctil */
@media (hover: none) and (pointer: coarse) {
  .btn-volver,
  .user-toggle,
  .user-dropdown-item {
    min-height: 44px;
    min-width: 44px;
  }
}

/* Mejora de hover solo en desktop */
@media (hover: hover) and (pointer: fine) {
  .video-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(155, 124, 184, 0.3);
  }
  
  .btn-volver:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
  }
  
  .user-toggle:hover {
    background: rgba(255, 255, 255, 0.3);
  }
  
  .user-dropdown-item:hover {
    background: #f8f9fa;
  }
}

/* Ajustes para pantallas muy anchas */
@media (min-width: 1400px) {
  .container {
    max-width: 1320px;
  }
  
  .readonly-badge {
    padding: 14px 30px;
    font-size: 1.2rem;
  }
  
  .section-header h4 {
    font-size: 1.5rem;
  }
}
  </style>
</head>
<body>

<div class="top-header">
  <div class="container-fluid">
    <h2><i class="fa-solid fa-book fa-beat" style="color: #ffffffff;"></i> <?= htmlspecialchars($carpeta['nombre']) ?> </h2>
    <div class="header-actions">
      <!-- Usuario Desplegable -->
      <div class="user-section">
        <button class="user-toggle" id="userToggle">
          <i class="fa-solid fa-user" style="color: #B197FC;"></i> <?= htmlspecialchars($_SESSION['nombre'] ?? 'Franquiciatario') ?> <i class="fa-solid fa-caret-down" style="color: #B197FC;"></i>
        </button>

        <div class="user-dropdown" id="userDropdown">
          <a href="usuarios_franquiciatario.php" class="user-dropdown-item" style="color: #9b7cb8;"><i class="fas fa-users"></i> Mis Usuarios</a>
          <a href="../logout.php" class="user-dropdown-item"><i class="fa-solid fa-door-open" style="color: #ef061d;"></i> Cerrar sesión</a>
        </div>
      </div>

      <!-- Botón Volver -->
      <a href="area.php?id=<?= $carpeta['id_padre'] ?>" class="btn-volver">
        <i class="fa-solid fa-angle-left" style="color: #B197FC;"></i> Volver
      </a>
    </div>
  </div>
</div>

<div class="container">
  

  <div class="section-header">
    <h4>Videos de <?= htmlspecialchars($carpeta['nombre']) ?>:</h4>
    <span class="badge">
      <?= count($videos) ?> video<?= count($videos) != 1 ? 's' : '' ?>
    </span>
  </div>
  
  <?php if (count($videos) > 0): ?>
    <div class="row">
      <?php foreach ($videos as $video): ?>
        <div class="col-md-12 mb-4">
          <div class="card video-card">
            <div class="card-body">
              <div class="row">
                <div class="col-md-6">
                  <h5 class="mb-2">
                    <?= htmlspecialchars($video['titulo']) ?>
                  </h5>
                  
                  <?php if (!empty($video['descripcion'])): ?>
                    <div class="video-descripcion">
                      <?= nl2br(htmlspecialchars($video['descripcion'])) ?>
                    </div>
                  <?php endif; ?>
                  
                  <?php if ($video['ruta'] && file_exists("../" . $video['ruta'])): ?>
                    <video src="../<?= htmlspecialchars($video['ruta']) ?>" 
                           width="100%" 
                           controls 
                           controlsList="nodownload"
                           preload="metadata">
                      Tu navegador no soporta el elemento de video.
                    </video>
                  <?php else: ?>
                    <div class="alert alert-warning mb-0">
                      <i class="fas fa-exclamation-triangle"></i> Video no encontrado
                    </div>
                  <?php endif; ?>
                  
                  <div class="d-flex justify-content-between align-items-center mt-3">
                    <small></small>
                    <?php if ($video['num_preguntas'] > 0): ?>
                      <span class="cuestionario-badge completo">
                        <i class="fas fa-check-circle"></i> <?= $video['num_preguntas'] ?> pregunta<?= $video['num_preguntas'] != 1 ? 's' : '' ?>
                      </span>
                    <?php else: ?>
                      <span class="cuestionario-badge pendiente">
                         <i class="fas fa-exclamation-triangle"></i> Sin cuestionario
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
                
                <div class="col-md-6">
                  <div class="d-flex justify-content-center align-items-center" style="height: 100%; min-height: 300px;">
                    <div class="text-center" style="color: #9b7cb8;">
                      <div style="font-size: 60px; opacity: 0.3; margin-bottom: 15px;"><i class="fas fa-lock"></i> </div>
                      <h5 style="font-weight: 600; margin-bottom: 10px;">Cuestionario Privado</h5>
                      <p class="text-muted" style="font-size: 0.9rem;">
                        El contenido del cuestionario no está disponible.
                      </p>
                      <?php if ($video['num_preguntas'] > 0): ?>
                        <small class="text-muted" style="font-size: 0.85rem;">
                          Este video tiene <?= $video['num_preguntas'] ?> pregunta<?= $video['num_preguntas'] != 1 ? 's' : '' ?> configurada<?= $video['num_preguntas'] != 1 ? 's' : '' ?>
                        </small>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty-state">
      <div class="icon"><i class="fas fa-video"></i></div>
      <h4>No hay videos en este módulo</h4>
      <p class="text-muted">Este módulo aún no tiene contenido disponible</p>
    </div>
  <?php endif; ?>
</div>

<script>
const userToggle = document.getElementById('userToggle');
const userDropdown = document.getElementById('userDropdown');

userToggle.addEventListener('click', (e) => {
  e.stopPropagation();
  userDropdown.classList.toggle('show');
});

document.addEventListener('click', () => {
  userDropdown.classList.remove('show');
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>