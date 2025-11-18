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

    .user-name {
      color: white;
      font-weight: 500;
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

      .user-name {
        display: none;
      }

      .btn-volver {
        padding: 6px 15px;
        font-size: 0.9rem;
      }
    }
  </style>
</head>
<body>

<div class="top-header">
  <div class="container-fluid">
    <h2>📂 <?= htmlspecialchars($carpeta['nombre']) ?> </h2>
    <div class="header-actions">
      <span class="user-name"><?= htmlspecialchars($_SESSION['nombre'] ?? 'Franquiciatario') ?></span>
      <a href="area.php?id=<?= $carpeta['id_padre'] ?>" class="btn-volver">
        ⬅ Volver
      </a>
    </div>
  </div>
</div>

<div class="container">
  

  <div class="section-header">
    <h4>Videos del <?= htmlspecialchars($carpeta['nombre']) ?>:</h4>
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
                      ⚠️ Video no encontrado
                    </div>
                  <?php endif; ?>
                  
                  <div class="d-flex justify-content-between align-items-center mt-3">
                    <small class="text-muted">ID: <?= $video['id_video'] ?></small>
                    <?php if ($video['num_preguntas'] > 0): ?>
                      <span class="cuestionario-badge completo">
                        ✅ <?= $video['num_preguntas'] ?> pregunta<?= $video['num_preguntas'] != 1 ? 's' : '' ?>
                      </span>
                    <?php else: ?>
                      <span class="cuestionario-badge pendiente">
                        ⚠️ Sin cuestionario
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
                
                <div class="col-md-6">
                  <div class="d-flex justify-content-center align-items-center" style="height: 100%; min-height: 300px;">
                    <div class="text-center" style="color: #9b7cb8;">
                      <div style="font-size: 60px; opacity: 0.3; margin-bottom: 15px;">🔒</div>
                      <h5 style="font-weight: 600; margin-bottom: 10px;">Cuestionario Privado</h5>
                      <p class="text-muted" style="font-size: 0.9rem;">
                        El contenido del cuestionario no está disponible<br>para visualización en modo lectura
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
      <div class="icon">🎬</div>
      <h4>No hay videos en este módulo</h4>
      <p class="text-muted">Este módulo aún no tiene contenido disponible</p>
    </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>