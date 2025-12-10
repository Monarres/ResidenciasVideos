<?php
session_start();
require_once("../conexion.php");

// Verificar admin
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
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

// Variables para mensajes
$mensaje = '';
$tipo_mensaje = '';

// Mensajes de éxito desde redirect
if (isset($_GET['msg'])) {
    switch($_GET['msg']) {
        case 'video_subido':
            $mensaje = " Video y cuestionario subidos correctamente";
            $tipo_mensaje = "success";
            break;
        case 'cuestionario_guardado':
            $mensaje = " Cuestionario guardado correctamente";
            $tipo_mensaje = "success";
            break;
    }
}

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
    }
    
    /* Header Principal */
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

    /* Usuario Section */
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
      white-space: nowrap;
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
      color: #dc3545;
      cursor: pointer;
      background: linear-gradient(135deg, rgba(220, 53, 69, 0.05), rgba(245, 163, 199, 0.1));
    }

    .user-dropdown-item:hover {
      background: linear-gradient(135deg, rgba(220, 53, 69, 0.15), rgba(245, 163, 199, 0.2));
      transform: translateX(5px);
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

    /* Contenedor Principal */
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

    .form-control, textarea.form-control {
      border-radius: 25px;
      padding: 10px 20px;
      border: 1px solid #ddd;
    }

    textarea.form-control {
      border-radius: 15px;
      min-height: 80px;
      resize: vertical;
    }

    /* Botón Añadir Video */
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
      box-shadow: 0 4px 15px rgba(155, 124, 184, 0.3);
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 10px;
    }

    .btn-add-video:hover {
      background: linear-gradient(135deg, #9b7cb8, #f5a3c7);
      transform: translateY(-3px);
      box-shadow: 0 6px 20px rgba(155, 124, 184, 0.4);
    }

    /* Video Cards */
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
      word-wrap: break-word;
      overflow-wrap: break-word;
    }

    .video-descripcion {
      color: #666;
      font-size: 0.9rem;
      margin-bottom: 15px;
      padding: 10px;
      background: linear-gradient(135deg, rgba(245, 163, 199, 0.05), rgba(155, 124, 184, 0.05));
      border-radius: 10px;
      border-left: 3px solid #9b7cb8;
      word-wrap: break-word;
    }

    video, iframe {
      border-radius: 10px;
      background: #000;
      width: 100%;
      max-width: 100%;
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: #9b7cb8;
    }

    .empty-state .icon {
      font-size: 80px;
      opacity: 0.3;
    }

    /* Alert */
    .alert {
      border-radius: 15px;
      border: none;
    }

    /* Badge */
    .badge {
      background: linear-gradient(135deg, #f5a3c7, #9b7cb8);
      font-weight: 500;
      padding: 8px 15px;
      border-radius: 20px;
    }

    /* Section Header */
    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      flex-wrap: wrap;
      gap: 10px;
    }

    .section-header h4 {
      color: #9b7cb8;
      font-weight: 600;
      margin: 0;
      word-wrap: break-word;
    }

    /* Cuestionario Badge */
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

    /* Preguntas Container */
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
      transition: all 0.2s ease;
      word-wrap: break-word;
    }

    .pregunta-card:hover {
      transform: translateX(5px);
      box-shadow: 0 2px 8px rgba(155, 124, 184, 0.2);
    }

    /* Botones */
    .btn-secondary-custom {
      background: white;
      border: 2px solid #9b7cb8;
      color: #9b7cb8;
      font-weight: 500;
      border-radius: 25px;
      padding: 8px 20px;
      transition: 0.3s;
    }

    .btn-secondary-custom:hover {
      background: #9b7cb8;
      color: white;
    }

    /* SweetAlert2 Styles */
    .swal2-popup {
      border-radius: 20px !important;
      font-family: 'Poppins', sans-serif !important;
      padding: 30px !important;
      background: #ffffff !important;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2) !important;
      max-width: 900px !important;
    }

    .swal2-title {
      color: #9b7cb8 !important;
      font-weight: 700 !important;
      font-size: 1.8rem !important;
      margin-bottom: 20px !important;
    }

    .swal2-html-container {
      color: #666 !important;
      font-size: 1rem !important;
      font-weight: 500 !important;
      max-height: 500px !important;
      overflow-y: auto !important;
    }

    .swal2-confirm {
      background: linear-gradient(135deg, #f5a3c7, #9b7cb8) !important;
      border: none !important;
      border-radius: 25px !important;
      padding: 12px 35px !important;
      font-weight: 600 !important;
      font-size: 1rem !important;
      box-shadow: 0 4px 15px rgba(155, 124, 184, 0.4) !important;
      transition: all 0.3s ease !important;
    }

    .swal2-confirm:hover {
      background: linear-gradient(135deg, #9b7cb8, #f5a3c7) !important;
      transform: translateY(-2px) !important;
      box-shadow: 0 6px 20px rgba(155, 124, 184, 0.5) !important;
    }

    .swal2-cancel {
      background: white !important;
      border: 2px solid #9b7cb8 !important;
      border-radius: 25px !important;
      padding: 12px 35px !important;
      font-weight: 600 !important;
      font-size: 1rem !important;
      color: #9b7cb8 !important;
      transition: all 0.3s ease !important;
    }

    .swal2-cancel:hover {
      background: #f8f9fa !important;
      transform: translateY(-2px) !important;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1) !important;
    }

    .swal2-input,
    .swal2-textarea {
      border-radius: 15px !important;
      border: 2px solid #ddd !important;
      padding: 12px 20px !important;
      font-family: 'Poppins', sans-serif !important;
      font-size: 1rem !important;
      transition: all 0.3s ease !important;
    }

    .swal2-textarea {
      min-height: 100px !important;
    }

    .swal2-input:focus,
    .swal2-textarea:focus {
      border-color: #9b7cb8 !important;
      box-shadow: 0 0 0 4px rgba(155, 124, 184, 0.15) !important;
      outline: none !important;
    }

    /* Media Queries */

    /* Tablets grandes */
    @media (max-width: 992px) {
      .top-header h2 {
        font-size: 1.3rem;
      }

      .btn-add-video {
        font-size: 1rem;
        padding: 12px 30px;
      }

      .video-card h5 {
        font-size: 1.1rem;
      }

      .container {
        padding: 15px 10px;
      }
    }

    /* Tablets pequeñas */
    @media (max-width: 768px) {
      body {
        padding-top: 140px;
      }

      .top-header {
        margin: 10px;
        padding: 15px 0;
        border-radius: 15px;
      }

      .top-header .container-fluid {
        flex-direction: column;
        padding: 0 15px;
        gap: 10px;
      }

      .top-header h2 {
        font-size: 1.2rem;
        text-align: center;
        width: 100%;
      }

      .header-right {
        width: 100%;
        justify-content: center;
        gap: 10px;
      }

      .user-toggle {
        padding: 6px 15px;
        font-size: 0.9rem;
      }

      .btn-volver {
        padding: 6px 15px;
        font-size: 0.9rem;
      }

      .btn-add-video {
        font-size: 0.95rem;
        padding: 12px 25px;
      }

      .section-header {
        flex-direction: column;
        align-items: flex-start;
      }

      .section-header h4 {
        font-size: 1rem;
      }

      /* Video responsivo en dos columnas */
      .video-card .row {
        display: flex;
        flex-direction: column;
      }

      .video-card .col-md-6 {
        max-width: 100%;
        margin-bottom: 20px;
      }

      video, iframe {
        height: 250px;
      }

      .preguntas-container {
        max-height: 300px;
      }

      /* Botones en video card */
      .video-card .d-flex.gap-2 {
        flex-direction: column;
        gap: 10px !important;
      }

      .video-card .btn {
        width: 100%;
      }

      /* SweetAlert2 responsivo */
      .swal2-popup {
        padding: 20px !important;
        max-width: 95% !important;
      }

      .swal2-title {
        font-size: 1.4rem !important;
      }

      .swal2-html-container {
        font-size: 0.9rem !important;
        max-height: 400px !important;
      }
    }

    /* Móviles */
    @media (max-width: 576px) {
      body {
        padding-top: 160px;
      }

      .top-header {
        margin: 8px;
        padding: 12px 0;
      }

      .top-header .container-fluid {
        padding: 0 10px;
      }

      .top-header h2 {
        font-size: 1rem;
        line-height: 1.4;
      }

      .header-right {
        flex-direction: column;
        width: 100%;
      }

      .user-section {
        width: 100%;
        justify-content: center;
      }

      .user-toggle {
        width: 100%;
        justify-content: center;
        padding: 8px 15px;
        font-size: 0.85rem;
      }

      .btn-volver {
        width: 100%;
        justify-content: center;
        padding: 8px 15px;
        font-size: 0.85rem;
      }

      .user-dropdown {
        right: 50%;
        transform: translateX(50%);
        min-width: 180px;
      }

      .container {
        padding: 10px 8px;
      }

      .btn-add-video {
        font-size: 0.9rem;
        padding: 10px 20px;
        width: 90%;
      }

      .btn-add-video-container {
        margin: 20px 0;
      }

      .section-header h4 {
        font-size: 0.95rem;
      }

      .badge {
        padding: 6px 12px;
        font-size: 0.8rem;
      }

      .video-card {
        margin-bottom: 20px;
      }

      .video-card h5 {
        font-size: 1rem;
      }

      .video-descripcion {
        font-size: 0.85rem;
        padding: 8px;
      }

      video, iframe {
        height: 200px;
      }

      .cuestionario-badge {
        font-size: 0.8rem;
        padding: 4px 12px;
      }

      .preguntas-container {
        padding: 15px;
        max-height: 250px;
      }

      .pregunta-card {
        padding: 12px;
        font-size: 0.85rem;
      }

      .btn-secondary-custom,
      .btn-danger {
        font-size: 0.85rem;
        padding: 6px 15px;
      }

      /* SweetAlert2 móvil */
      .swal2-popup {
        padding: 15px !important;
        width: 95% !important;
      }

      .swal2-title {
        font-size: 1.2rem !important;
      }

      .swal2-html-container {
        font-size: 0.85rem !important;
        max-height: 350px !important;
      }

      .swal2-confirm,
      .swal2-cancel {
        padding: 10px 25px !important;
        font-size: 0.9rem !important;
      }

      .swal2-input,
      .swal2-textarea {
        font-size: 0.9rem !important;
        padding: 10px 15px !important;
      }
    }

    /* Móviles muy pequeños */
    @media (max-width: 380px) {
      .top-header h2 {
        font-size: 0.9rem;
      }

      .user-toggle,
      .btn-volver {
        font-size: 0.8rem;
        padding: 6px 12px;
      }

      .btn-add-video {
        font-size: 0.85rem;
        padding: 8px 15px;
      }

      .section-header h4 {
        font-size: 0.9rem;
      }

      video, iframe {
        height: 180px;
      }

      .pregunta-card {
        font-size: 0.8rem;
        padding: 10px;
      }
    }

    /* Landscape en móviles */
    @media (max-height: 500px) and (orientation: landscape) {
      body {
        padding-top: 100px;
      }

      .top-header {
        padding: 10px 0;
      }

      .btn-add-video-container {
        margin: 15px 0;
      }

      video, iframe {
        height: 150px;
      }

      .preguntas-container {
        max-height: 200px;
      }
    }
  </style>
</head>
<body>

<div class="top-header">
  <div class="container-fluid">
    <h2><i class="fa-solid fa-book fa-beat"></i> <?= htmlspecialchars($carpeta['nombre']) ?></h2>
    <div class="header-right">
      <div class="user-section">
        <button class="user-toggle" id="userToggle">
          <i class="fa-solid fa-user" style="color: #B197FC;"></i>
          <span><?=htmlspecialchars($_SESSION['nombre'])?></span>
          <i class="fa-solid fa-caret-down" style="color: #B197FC;"></i>
        </button>
        <div class="user-dropdown" id="userDropdown">
          <a href="../logout.php" class="user-dropdown-item">
            <i class="fa-solid fa-door-open" style="color: #ef061d;"></i>
            <span>Cerrar sesión</span>
          </a>
        </div>
      </div>
      <a href="area.php?id=<?= $carpeta['id_padre'] ?>" class="btn-volver">
        <i class="fa-solid fa-angle-left" style="color: #B197FC;"></i>
        <span>Volver</span>
      </a>
    </div>
  </div>
</div>

<div class="container">
  <?php if ($mensaje): ?>
    <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($mensaje) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="btn-add-video-container">
    <button id="btnAddVideo" class="btn-add-video">
      <span>Añadir Video</span>
      <i class="fa-solid fa-circle-plus"></i>
    </button>
  </div>

  <div class="section-header">
    <h4><i class="fa-solid fa-video" style="color: #9b7cb8;"></i> Videos de <?= htmlspecialchars($carpeta['nombre']) ?>:</h4>
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
                  
                  <?php if ($video['ruta']): ?>
                    <?php if ($video['tipo_video'] === 'youtube' || strpos($video['ruta'], 'youtube.com') !== false): ?>
                      <iframe 
                        src="<?= htmlspecialchars($video['ruta']) ?>" 
                        width="100%" 
                        height="400"
                        style="border-radius: 10px; border: none;"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen>
                      </iframe>
                    <?php else: ?>
                      <video src="../<?= htmlspecialchars($video['ruta']) ?>" 
                             width="100%" 
                             controls 
                             controlsList="nodownload"
                             preload="metadata">
                        Tu navegador no soporta el elemento de video.
                      </video>
                    <?php endif; ?>
                  <?php else: ?>
                    <div class="alert alert-warning mb-0">Video no encontrado</div>
                  <?php endif; ?>
                  
                  <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <small></small>
                    <?php if ($video['num_preguntas'] > 0): ?>
                      <span class="cuestionario-badge completo">
                        <?= $video['num_preguntas'] ?> pregunta<?= $video['num_preguntas'] != 1 ? 's' : '' ?>
                      </span>
                    <?php else: ?>
                      <span class="cuestionario-badge pendiente">
                        Sin cuestionario
                      </span>
                    <?php endif; ?>
                  </div>

                  <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-secondary-custom btn-sm flex-fill btn-edit-completo" 
                            data-id="<?= $video['id_video'] ?>">
                      <span>Editar</span> <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-danger btn-sm flex-fill btn-del-video" 
                            data-id="<?= $video['id_video'] ?>"
                            data-title="<?= htmlspecialchars($video['titulo']) ?>"
                            style="border-radius: 12px;">
                      <span>Eliminar</span> <i class="fas fa-trash-alt"></i>
                    </button>
                  </div>
                </div>
                
                <div class="col-md-6">
                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 style="color: #9b7cb8; font-weight: 600; margin: 0;">
                      <span>Cuestionario</span> <i class="fas fa-clipboard-list"></i>
                    </h6>
                  </div>
                  
                  <?php
                  $stmtQ = $pdo->prepare("SELECT * FROM cuestionarios WHERE id_video = ? ORDER BY id_cuestionario ASC");
                  $stmtQ->execute([$video['id_video']]);
                  $preguntas = $stmtQ->fetchAll(PDO::FETCH_ASSOC);
                  ?>
                  
                  <?php if (count($preguntas) > 0): ?>
                    <div class="preguntas-container">
                      <?php 
foreach ($preguntas as $p): 
  $es_archivo = ($p['tipo_pregunta'] === 'archivo');
  $etiqueta = $es_archivo ? "Archivo" : "Pregunta";
?>
  <div class="pregunta-card">
    <small style="color: #9b7cb8; font-weight: 600;"><?= $etiqueta ?></small>
                          <p class="mb-2 mt-1" style="font-size: 0.9rem;"><strong><?= htmlspecialchars($p['pregunta']) ?></strong></p>
                          
                          <?php if (!$es_archivo): ?>
                            <?php
                            // Opciones dinámicas
                            $opciones = null;
                            if (!empty($p['opciones_json'])) {
                              $opciones = json_decode($p['opciones_json'], true);
                            }

                            if ($opciones && is_array($opciones)) {
                              foreach ($opciones as $letra => $texto) {
                                echo "<small class='text-muted d-block'>{$letra}) " . htmlspecialchars($texto) . "</small>";
                              }
                            }
                            ?>
                            <small class="d-block mt-2" style="color: #28a745; font-weight: 600;">
                               Respuesta: <?= htmlspecialchars($p['respuesta_correcta']) ?>
                            </small>
                          <?php else: ?>
                            <small class="d-block mt-2" style="color: #9b7cb8; font-weight: 600;">
                               Pregunta de tipo archivo
                            </small>
                            <?php if (!empty($p['instrucciones_archivo'])): ?>
                              <small class="d-block text-muted mt-1">
                                Instrucciones: <?= htmlspecialchars($p['instrucciones_archivo']) ?>
                              </small>
                            <?php endif; ?>
                          <?php endif; ?>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <div class="alert alert-warning mb-0">
                      Este video aún no tiene preguntas.
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty-state">
      <div class="icon"></div>
      <h4>No hay videos en este módulo</h4>
      <p class="text-muted">Sube tu primer video usando el botón de arriba</p>
    </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Agregar ANTES de los fetch
const originalFetch = window.fetch;
window.fetch = function(...args) {
    console.log('Fetch llamado a:', args[0]);
    return originalFetch.apply(this, args)
        .then(response => {
            console.log('Respuesta recibida de:', args[0], 'Status:', response.status);
            if (response.status === 404) {
                console.error('ERROR 404 en:', args[0]);
            }
            return response;
        })
        .catch(error => {
            console.error('Error en fetch a:', args[0], error);
            throw error;
        });
};

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

// FUNCIÓN AUXILIAR
function escapeHtml(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}



// Función para reordenar opciones después de eliminar
function reordenarOpciones(contenedorOpciones, selectRespuesta) {
  const letras = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  const opcionesItems = contenedorOpciones.querySelectorAll('.opcion-item');
  const respuestaSeleccionada = selectRespuesta.value;
  
  const mapeoLetras = {};
  const nuevasOpciones = [];
  
  opcionesItems.forEach((item, index) => {
    const letraAntigua = item.getAttribute('data-letra');
    const letraNueva = letras[index];
    const textoOpcion = item.querySelector('.opcion-texto').value;
    
    mapeoLetras[letraAntigua] = letraNueva;
    nuevasOpciones.push({ letra: letraNueva, texto: textoOpcion });
    
    item.setAttribute('data-letra', letraNueva);
    item.querySelector('.input-group-text').textContent = letraNueva;
    item.querySelector('.opcion-texto').placeholder = `Opción ${letraNueva}`;
  });
  
  selectRespuesta.innerHTML = '<option value="">Selecciona la respuesta correcta</option>';
  
  nuevasOpciones.forEach(opcion => {
    const option = document.createElement('option');
    option.value = opcion.letra;
    option.textContent = opcion.letra;
    selectRespuesta.appendChild(option);
  });
  
  if (respuestaSeleccionada && mapeoLetras[respuestaSeleccionada]) {
    selectRespuesta.value = mapeoLetras[respuestaSeleccionada];
  } else {
    selectRespuesta.value = '';
  }
}

// BOTÓN AÑADIR VIDEO
document.getElementById("btnAddVideo").addEventListener("click", async () => {
  const { value: formValues } = await Swal.fire({
    title: 'Añadir nuevo video <i class="fas fa-plus-circle"></i>',
    html: `
      <div style="text-align:left; max-height:70vh; overflow-y:auto; padding: 0 10px;">
        <label class="fw-bold">Título del video:</label>
        <input type="text" id="tituloVideo" class="swal2-input" placeholder="Ejemplo: Introducción al módulo 1" style="width:100%; margin-top: 5px; margin-left: -5px;">

        <label class="fw-bold mt-3">Descripción:</label>
        <textarea id="descripcionVideo" class="swal2-textarea" placeholder="Descripción breve del contenido" style="width:100%; margin-left: -5px;"></textarea>

        <label class="fw-bold mt-3"><i class="fa-solid fa-video" style="color: #B197FC;"></i> URL de YouTube:</label>
<input type="url" id="urlYoutube" class="swal2-input" placeholder="https://www.youtube.com/watch?v=..." style="width:100%; margin-top: 5px; margin-left: -5px;">
<small class="text-muted d-block mt-1">
  <i class="fa-solid fa-clipboard-list" style="color: #B197FC;"></i> Puedes usar: youtube.com/watch?v=... o youtu.be/...
</small>
        <hr class="my-4">

        <h6 style="color:#9b7cb8; font-weight:600;">Cuestionario:</h6>
        
        <div style="background: linear-gradient(135deg, rgba(245, 163, 199, 0.1), rgba(155, 124, 184, 0.1)); border: 2px solid #9b7cb8; border-radius: 15px; padding: 15px; margin-top: 15px; margin-bottom: 20px;">
          <label class="fw-bold" style="color: #9b7cb8;"> Instrucciones del cuestionario:</label>
          <textarea id="instruccionesCuestionario" class="swal2-textarea" placeholder="Escribe las instrucciones generales para el cuestionario (opcional)..." style="width:100%; margin-left: -5px; margin-top: 8px; min-height: 100px; border: 2px solid #ddd;"></textarea>
        </div>

        <div id="contenedorPreguntas" style="margin-top:10px;"></div>

        <button type="button" class="btn mt-3" id="btnAgregarPregunta" style="background: linear-gradient(135deg, #f5a3c7, #9b7cb8); color: white; border:none; border-radius:15px; padding: 10px 20px; font-weight: 600;">
          Añadir pregunta <i class="fas fa-plus-circle"></i>
        </button>
      </div>
    `,
    width: '900px',
    showCancelButton: true,
    confirmButtonText: 'Guardar y publicar <i class="fas fa-save"></i>',
    cancelButtonText: 'Cancelar',
    didOpen: () => {
      const cont = document.getElementById("contenedorPreguntas");
      
      document.getElementById("btnAgregarPregunta").addEventListener("click", () => {
  const div = document.createElement("div");
  div.className = "pregunta-item";
  div.style.marginTop = "15px";
  div.innerHTML = `
    <div style="border:2px solid #f0e4f3; border-radius:15px; padding:15px; background: linear-gradient(135deg, rgba(245, 163, 199, 0.05), rgba(155, 124, 184, 0.05));">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <label class="fw-bold pregunta-label" style="color: #9b7cb8;"><i class="fa-solid fa-question" style="color: #B197FC;"></i>
 Pregunta</label>
              <button type="button" class="btn btn-sm btn-danger btn-eliminar-pregunta" style="border-radius: 15px;">Eliminar</button>
            </div>
            
            <div class="mt-2 mb-2">
              <button type="button" class="btn btn-outline-secondary btn-sm tipo-btn active" data-tipo="incisos" style="border-radius: 10px; margin-right: 5px;">✓ Opción múltiple</button>
              <button type="button" class="btn btn-outline-secondary btn-sm tipo-btn" data-tipo="archivo" style="border-radius: 10px;"> Subir archivo</button>
            </div>
            
            <div class="pregunta-texto-container mt-2">
              <input type="text" class="form-control pregunta-input" placeholder="Escribe la pregunta" style="border-radius: 15px;">
            </div>
            
            <div class="opciones-dinamicas mt-3" style="display:block;">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <small class="fw-bold" style="color: #666;">Opciones de respuesta:</small>
                <button type="button" class="btn btn-sm btn-agregar-opcion" style="background: #9b7cb8; color: white; border-radius: 10px; padding: 4px 12px;">
                  Agregar opción
                </button>
              </div>
              <div class="contenedor-opciones"></div>
              
              <small class="d-block mt-3 fw-bold" style="color: #666;">Respuesta correcta:</small>
              <select class="form-control mt-2 select-respuesta" style="border-radius: 15px;">
                <option value="">Selecciona la respuesta correcta</option>
              </select>
            </div>
            
            <div class="archivo-container mt-2" style="display:none;">
              <textarea class="form-control instrucciones-archivo" placeholder="Instrucciones para el usuario..." style="border-radius: 15px; min-height: 100px;"></textarea>
            </div>
          </div>
        `;

        cont.appendChild(div);

        // Configurar botones de tipo
        const tipoBtns = div.querySelectorAll(".tipo-btn");
        const preguntaTextoContainer = div.querySelector('.pregunta-texto-container');
        const opcionesDinamicas = div.querySelector('.opciones-dinamicas');
        const archivoContainer = div.querySelector('.archivo-container');
        const label = div.querySelector('.pregunta-label');
        
       tipoBtns.forEach(btn => {
  btn.addEventListener("click", () => {
    tipoBtns.forEach(x => x.classList.remove("active"));
    btn.classList.add("active");
    const tipo = btn.dataset.tipo;
    
    if (tipo === 'incisos') {
      preguntaTextoContainer.style.display = 'block';
      opcionesDinamicas.style.display = 'block';
      archivoContainer.style.display = 'none';
      label.innerHTML = '<i class="fa-solid fa-question" style="color: #B197FC;"></i> Pregunta';
    } else {
      preguntaTextoContainer.style.display = 'none';
      opcionesDinamicas.style.display = 'none';
      archivoContainer.style.display = 'block';
      label.innerHTML = '<i class="fa-solid fa-paperclip" style="color: #B197FC;"></i> Archivo';
    }
  });
});

        // Botón eliminar pregunta
        div.querySelector('.btn-eliminar-pregunta').addEventListener('click', function() {
          div.remove();
          actualizarEtiquetasPreguntas();
        });

        // Configurar botón agregar opción
        const btnAgregarOpcion = div.querySelector('.btn-agregar-opcion');
        const contenedorOpciones = div.querySelector('.contenedor-opciones');
        const selectRespuesta = div.querySelector('.select-respuesta');
        
        btnAgregarOpcion.addEventListener('click', () => {
          const letras = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
          const opcionesActuales = contenedorOpciones.querySelectorAll('.opcion-item');
          const siguienteLetra = letras[opcionesActuales.length];
          
          if (opcionesActuales.length >= 26) {
            Swal.fire({
              icon: 'warning',
              title: 'Límite alcanzado',
              text: 'No puedes agregar más de 26 opciones',
              toast: true,
              position: 'top-end',
              timer: 3000,
              showConfirmButton: false
            });
            return;
          }
          
          const nuevaOpcion = document.createElement('div');
          nuevaOpcion.className = 'opcion-item mb-2';
          nuevaOpcion.setAttribute('data-letra', siguienteLetra);
          nuevaOpcion.innerHTML = `
            <div class="input-group">
              <span class="input-group-text" style="background: #9b7cb8; color: white; border-radius: 10px 0 0 10px; font-weight: 600;">${siguienteLetra}</span>
              <input type="text" class="form-control opcion-texto" placeholder="Opción ${siguienteLetra}" style="border-radius: 0;">
              <button type="button" class="btn btn-danger btn-eliminar-opcion" style="border-radius: 0 10px 10px 0;">Eliminar</button>
            </div>
          `;
          
          contenedorOpciones.appendChild(nuevaOpcion);
          
          const nuevaOption = document.createElement('option');
          nuevaOption.value = siguienteLetra;
          nuevaOption.textContent = siguienteLetra;
          selectRespuesta.appendChild(nuevaOption);
          
          nuevaOpcion.querySelector('.btn-eliminar-opcion').addEventListener('click', function() {
            nuevaOpcion.remove();
            reordenarOpciones(contenedorOpciones, selectRespuesta);
          });
        });
      });
    },
    preConfirm: () => {
      const titulo = document.getElementById("tituloVideo").value.trim();
      const descripcion = document.getElementById("descripcionVideo").value.trim();
      const urlYoutube = document.getElementById("urlYoutube").value.trim();
      const instruccionesCuestionario = document.getElementById("instruccionesCuestionario").value.trim();

      if (!titulo) {
        Swal.showValidationMessage("Debes ingresar el título del video");
        return false;
      }

      if (!urlYoutube) {
        Swal.showValidationMessage("Debes ingresar la URL de YouTube");
        return false;
      }

      if (!urlYoutube.includes('youtube.com') && !urlYoutube.includes('youtu.be')) {
        Swal.showValidationMessage("La URL debe ser de YouTube");
        return false;
      }

      const preguntas = [];
      document.querySelectorAll(".pregunta-item").forEach((div, i) => {
        const tipo = div.querySelector(".tipo-btn.active")?.dataset.tipo;

        if (tipo === "incisos") {
          const pregunta = div.querySelector('.pregunta-input').value.trim();
          
          if (!pregunta) {
            Swal.showValidationMessage(`La pregunta ${i+1} no puede estar vacía`);
            return false;
          }

          const opciones = {};
          const opcionesItems = div.querySelectorAll('.opcion-item');
          
          opcionesItems.forEach(item => {
            const letra = item.getAttribute('data-letra');
            const texto = item.querySelector('.opcion-texto').value.trim();
            
            if (!texto) {
              Swal.showValidationMessage(`La opción ${letra} de la pregunta ${i+1} no puede estar vacía`);
              return false;
            }
            
            opciones[letra] = texto;
          });

          if (Object.keys(opciones).length < 2) {
            Swal.showValidationMessage(`La pregunta ${i+1} debe tener al menos 2 opciones`);
            return false;
          }

          const resp = div.querySelector('.select-respuesta').value;
          
          if (!resp) {
            Swal.showValidationMessage(`Debes seleccionar la respuesta correcta de la pregunta ${i+1}`);
            return false;
          }

          preguntas.push({
            tipo,
            pregunta,
            opciones,
            respuesta_correcta: resp
          });
        } else if (tipo === "archivo") {
          const instrucciones = div.querySelector('.instrucciones-archivo').value.trim();
          
          if (!instrucciones) {
            Swal.showValidationMessage(`La pregunta ${i+1} de tipo archivo debe tener instrucciones`);
            return false;
          }

          preguntas.push({
            tipo,
            pregunta: instrucciones,
            instrucciones: instrucciones
          });
        }
      });

      if (preguntas.length === 0) {
        Swal.showValidationMessage("Debes agregar al menos una pregunta al cuestionario");
        return false;
      }

      return { titulo, descripcion, urlYoutube, preguntas, instruccionesCuestionario };
    }
  });

  if (!formValues) return;

  Swal.fire({
    title: 'Subiendo video...',
    html: `
      <div style="padding: 20px;">
        <p>Por favor espera mientras se procesa el video y el cuestionario.</p>
        <p class="text-muted" style="font-size: 0.9rem;">Esto puede tomar varios minutos dependiendo del tamaño del archivo.</p>
      </div>
    `,
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading();
    }
  });

  const formData = new FormData();
  formData.append("id_carpeta", <?= $id_carpeta ?>);
  formData.append("titulo", formValues.titulo);
  formData.append("descripcion", formValues.descripcion);
  formData.append("url_youtube", formValues.urlYoutube);  
  formData.append("preguntas", JSON.stringify(formValues.preguntas));
  formData.append("instrucciones_cuestionario", formValues.instruccionesCuestionario);

  try {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 300000);

    console.log("Enviando video...");
    
    const res = await fetch("/learning/admin/guardar_video.php", { 
      method: "POST", 
      body: formData,
      signal: controller.signal
    });
    
    clearTimeout(timeoutId);

    console.log("Respuesta recibida, status:", res.status);

    const contentType = res.headers.get("content-type");
    if (!contentType || !contentType.includes("application/json")) {
      const text = await res.text();
      console.error("Respuesta no JSON:", text);
      throw new Error("El servidor no devolvió una respuesta válida. Respuesta: " + text.substring(0, 200));
    }

    const data = await res.json();
    console.log("Datos recibidos:", data);

    if (data.success) {
      await Swal.fire({ 
        icon: 'success', 
        title: '¡Video publicado!', 
        text: data.message, 
        confirmButtonText: 'Aceptar'
      });
      location.reload();
    } else {
      Swal.fire({ 
        icon: 'error', 
        title: 'Error', 
        html: `<p>${data.message}</p><small class="text-muted">Si el problema persiste, verifica el tamaño del video y la configuración del servidor.</small>`
      });
    }
  } catch (error) {
    console.error("Error completo:", error);
    
    if (error.name === 'AbortError') {
      Swal.fire({
        icon: 'error',
        title: 'Tiempo agotado',
        html: `
          <p>La subida del video tomó demasiado tiempo.</p>
          <small class="text-muted">Sugerencias:</small>
          <ul style="text-align: left; font-size: 0.9rem;">
            <li>Intenta con un video más pequeño</li>
            <li>Comprime el video antes de subirlo</li>
            <li>Verifica tu conexión a internet</li>
          </ul>
        `
      });
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Error de conexión',
        html: `
          <p>No se pudo conectar con el servidor.</p>
          <small class="text-muted">${error.message}</small>
        `
      });
    }
  }
});

// Botón Eliminar Video
document.querySelectorAll('.btn-del-video').forEach(btn => {
  btn.addEventListener('click', async (e) => {
    e.preventDefault();
    const id = btn.dataset.id;
    const titulo = btn.dataset.title;
    
    const result = await Swal.fire({
      title: '¿Eliminar video?',
      html: `Se eliminará el video:<br><strong>"${escapeHtml(titulo)}"</strong><br><br> Esta acción no se puede deshacer y eliminará también todas las preguntas asociadas.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#dc3545'
    });

    if (result.isConfirmed) {
      Swal.fire({
        title: 'Eliminando...',
        text: 'Por favor espera',
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });

      try {
        const res = await fetch('eliminar_video.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            id: id,
            carpeta: <?= $id_carpeta ?>
          })
        });
        
        const json = await res.json();
        
        if (json.success) {
          await Swal.fire({
            icon: 'success',
            title: '¡Eliminado!',
            text: json.message
          });
          location.reload();
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: json.message
          });
        }
      } catch (err) {
        console.error("Error:", err);
        Swal.fire({
          icon: 'error',
          title: 'Error de conexión',
          text: 'No se pudo conectar con el servidor'
        });
      }
    }
  });
});

// Botón Editar Video Completo
document.querySelectorAll('.btn-edit-completo').forEach(btn => {
  btn.addEventListener('click', async (e) => {
    e.preventDefault();
    const idVideo = btn.dataset.id;
    
    // Mostrar loading mientras se cargan los datos
    Swal.fire({
      title: 'Cargando datos...',
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });

    try {
      // Obtener datos del video y preguntas
      const res = await fetch(`/learning/admin/obtener_video.php?id=${idVideo}`);
      const data = await res.json();
      
      if (!data.success) {
        throw new Error(data.message || 'Error al cargar los datos');
      }

      const video = data.video;
      const preguntas = data.preguntas || [];
      
      // Mostrar formulario de edición
      const { value: formValues } = await Swal.fire({
        title: ' Editar video',
        html: `
          <div style="text-align:left; max-height:70vh; overflow-y:auto; padding: 0 10px;">
            <label class="fw-bold">Título del video:</label>
            <input type="text" id="tituloVideo" class="swal2-input" value="${escapeHtml(video.titulo)}" style="width:100%; margin-top: 5px; margin-left: -5px;">

            <label class="fw-bold mt-3">Descripción:</label>
            <textarea id="descripcionVideo" class="swal2-textarea" style="width:100%; margin-left: -5px;">${escapeHtml(video.descripcion || '')}</textarea>

            <label class="fw-bold mt-3">Cambiar URL de YouTube (opcional):</label>
<input type="url" id="urlYoutube" class="swal2-input" value="${video.tipo_video === 'youtube' ? escapeHtml(video.ruta) : ''}" placeholder="https://www.youtube.com/watch?v=..." style="width:100%; margin-top: 5px; margin-left: -5px;">
<small class="text-muted d-block mt-1">Deja vacío si no deseas cambiar el video actual</small>

            <hr class="my-4">

            <h6 style="color:#9b7cb8; font-weight:600;"> Cuestionario</h6>
            
            <div style="background: linear-gradient(135deg, rgba(245, 163, 199, 0.1), rgba(155, 124, 184, 0.1)); border: 2px solid #9b7cb8; border-radius: 15px; padding: 15px; margin-top: 15px; margin-bottom: 20px;">
              <label class="fw-bold" style="color: #9b7cb8;"> Instrucciones del cuestionario:</label>
              <textarea id="instruccionesCuestionario" class="swal2-textarea" placeholder="Escribe las instrucciones generales para el cuestionario (opcional)..." style="width:100%; margin-left: -5px; margin-top: 8px; min-height: 100px; border: 2px solid #ddd;">${escapeHtml(video.instrucciones_cuestionario || '')}</textarea>
            </div>

            <div id="contenedorPreguntas" style="margin-top:10px;"></div>

            <button type="button" class="btn mt-3" id="btnAgregarPregunta" style="background: linear-gradient(135deg, #f5a3c7, #9b7cb8); color: white; border:none; border-radius:15px; padding: 10px 20px; font-weight: 600;">
               Añadir pregunta
            </button>
          </div>
        `,
        width: '900px',
        showCancelButton: true,
        confirmButtonText: ' Guardar cambios',
        cancelButtonText: 'Cancelar',
        didOpen: () => {
          const cont = document.getElementById("contenedorPreguntas");
          
          // Función para crear una pregunta
          function crearPregunta(datosPregunta = null, num = null) {
            if (!num) num = cont.children.length + 1;
            
            const div = document.createElement("div");
            div.className = "pregunta-item";
            div.style.marginTop = "15px";
            
            
            const opciones = datosPregunta?.opciones_json ? JSON.parse(datosPregunta.opciones_json) : {};
            const esArchivo = datosPregunta && datosPregunta.tipo_pregunta === 'archivo';
const etiqueta = esArchivo ? '<i class="fa-solid fa-paperclip" style="color: #B197FC;"></i> Archivo' : '<i class="fa-solid fa-question" style="color: #B197FC;"></i> Pregunta';

div.innerHTML = `
  <div style="border:2px solid #f0e4f3; border-radius:15px; padding:15px; background: linear-gradient(135deg, rgba(245, 163, 199, 0.05), rgba(155, 124, 184, 0.05));">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <label class="fw-bold pregunta-label" style="color: #9b7cb8;">${etiqueta}</label>
                  <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.pregunta-item').remove()" style="border-radius: 15px;">Eliminar</button>
                </div>
                
                <input type="hidden" class="pregunta-id" value="${datosPregunta?.id_cuestionario || ''}">
                
                <div class="mt-2 mb-2">
                  <button type="button" class="btn btn-outline-secondary btn-sm tipo-btn ${!esArchivo ? 'active' : ''}" data-tipo="incisos" style="border-radius: 10px; margin-right: 5px;"> Opción múltiple</button>
                  <button type="button" class="btn btn-outline-secondary btn-sm tipo-btn ${esArchivo ? 'active' : ''}" data-tipo="archivo" style="border-radius: 10px;"> Subir archivo</button>
                </div>
                
                <div class="pregunta-texto-container mt-2" style="display:${esArchivo ? 'none' : 'block'};">
                  <input type="text" class="form-control pregunta-input" placeholder="Escribe la pregunta" value="${escapeHtml(datosPregunta?.pregunta || '')}" style="border-radius: 15px;">
                </div>
                
                <div class="opciones-dinamicas mt-3" style="display:${esArchivo ? 'none' : 'block'};">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <small class="fw-bold" style="color: #666;">Opciones de respuesta:</small>
                    <button type="button" class="btn btn-sm btn-agregar-opcion" style="background: #9b7cb8; color: white; border-radius: 10px; padding: 4px 12px;">
                       Agregar opción
                    </button>
                  </div>
                  <div class="contenedor-opciones"></div>
                  
                  <small class="d-block mt-3 fw-bold" style="color: #666;">Respuesta correcta:</small>
                  <select class="form-control mt-2 select-respuesta" style="border-radius: 15px;">
                    <option value="">Selecciona la respuesta correcta</option>
                  </select>
                </div>
                
                <div class="archivo-container mt-2" style="display:${esArchivo ? 'block' : 'none'};">
                  <textarea class="form-control instrucciones-archivo" placeholder="Instrucciones para el usuario..." style="border-radius: 15px; min-height: 100px;">${escapeHtml(datosPregunta?.instrucciones_archivo || '')}</textarea>
                </div>
              </div>
            `;

            cont.appendChild(div);

            // Si es pregunta de incisos, cargar opciones
            if (!esArchivo && datosPregunta) {
              const contenedorOpciones = div.querySelector('.contenedor-opciones');
              const selectRespuesta = div.querySelector('.select-respuesta');
              
              // Cargar opciones existentes
              if (Object.keys(opciones).length > 0) {
                Object.entries(opciones).forEach(([letra, texto]) => {
                  agregarOpcion(contenedorOpciones, selectRespuesta, letra, texto);
                });
              } else {
                // Formato antiguo
                const opcionesAntiguas = [
                  ['A', datosPregunta.opcion_a],
                  ['B', datosPregunta.opcion_b],
                  ['C', datosPregunta.opcion_c]
                ];
                opcionesAntiguas.forEach(([letra, texto]) => {
              if (texto) {
                agregarOpcion(contenedorOpciones, selectRespuesta, letra, texto);
              }
            });
          }
          
          // Seleccionar respuesta correcta
          if (datosPregunta.respuesta_correcta) {
            selectRespuesta.value = datosPregunta.respuesta_correcta;
          }
        }

        // Configurar botones de tipo
        configurarBotonesTipo(div);
        
        // Configurar botón agregar opción
        configurarBotonAgregarOpcion(div);
      }
      
      // Función para agregar una opción
      function agregarOpcion(contenedorOpciones, selectRespuesta, letra, texto = '') {
        const nuevaOpcion = document.createElement('div');
        nuevaOpcion.className = 'opcion-item mb-2';
        nuevaOpcion.setAttribute('data-letra', letra);
        
        nuevaOpcion.innerHTML = `
          <div class="input-group">
            <span class="input-group-text" style="background: #9b7cb8; color: white; border-radius: 10px 0 0 10px; font-weight: 600;">${letra}</span>
            <input type="text" class="form-control opcion-texto" placeholder="Opción ${letra}" value="${escapeHtml(texto)}" style="border-radius: 0;">
            <button type="button" class="btn btn-danger btn-eliminar-opcion" style="border-radius: 0 10px 10px 0;">Eliminar</button>
          </div>
        `;
        
        contenedorOpciones.appendChild(nuevaOpcion);
        
        // Agregar al select si no existe
        if (!selectRespuesta.querySelector(`option[value="${letra}"]`)) {
          const option = document.createElement('option');
          option.value = letra;
          option.textContent = letra;
          selectRespuesta.appendChild(option);
        }
        
        // Configurar botón eliminar CON REORDENAMIENTO
        nuevaOpcion.querySelector('.btn-eliminar-opcion').addEventListener('click', function() {
          nuevaOpcion.remove();
          reordenarOpciones(contenedorOpciones, selectRespuesta);
        });
      }
      
      // Configurar botones de tipo de pregunta
      function configurarBotonesTipo(div) {
        const tipoBtns = div.querySelectorAll(".tipo-btn");
        const preguntaTextoContainer = div.querySelector('.pregunta-texto-container');
        const opcionesDinamicas = div.querySelector('.opciones-dinamicas');
        const archivoContainer = div.querySelector('.archivo-container');
        
        tipoBtns.forEach(btn => {
          btn.addEventListener("click", () => {
            tipoBtns.forEach(x => x.classList.remove("active"));
            btn.classList.add("active");
            const tipo = btn.dataset.tipo;
            
           const label = div.querySelector('.pregunta-label');

if (tipo === 'incisos') {
  preguntaTextoContainer.style.display = 'block';
  opcionesDinamicas.style.display = 'block';
  archivoContainer.style.display = 'none';
  if (label) label.innerHTML = '<i class="fa-solid fa-question" style="color: #B197FC;"></i> Pregunta';
} else {
  preguntaTextoContainer.style.display = 'none';
  opcionesDinamicas.style.display = 'none';
  archivoContainer.style.display = 'block';
  if (label) label.innerHTML = '<i class="fa-solid fa-paperclip" style="color: #B197FC;"></i> Archivo';
}
          });
        });
      }
      
      // Configurar botón agregar opción
      function configurarBotonAgregarOpcion(div) {
        const btnAgregarOpcion = div.querySelector('.btn-agregar-opcion');
        const contenedorOpciones = div.querySelector('.contenedor-opciones');
        const selectRespuesta = div.querySelector('.select-respuesta');
        
        btnAgregarOpcion.addEventListener('click', () => {
          const letras = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
          const opcionesActuales = contenedorOpciones.querySelectorAll('.opcion-item');
          const siguienteLetra = letras[opcionesActuales.length];
          
          if (opcionesActuales.length >= 26) {
            Swal.fire({
              icon: 'warning',
              title: 'Límite alcanzado',
              text: 'No puedes agregar más de 26 opciones',
              toast: true,
              position: 'top-end',
              timer: 3000,
              showConfirmButton: false
            });
            return;
          }
          
          agregarOpcion(contenedorOpciones, selectRespuesta, siguienteLetra, '');
        });
      }
      
      // Cargar preguntas existentes
      preguntas.forEach((p, i) => {
        crearPregunta(p, i + 1);
      });
      
      // Configurar botón agregar pregunta nueva
      document.getElementById("btnAgregarPregunta").addEventListener("click", () => {
        crearPregunta();
      });
    },
    preConfirm: () => {
      const titulo = document.getElementById("tituloVideo").value.trim();
      const descripcion = document.getElementById("descripcionVideo").value.trim();
      const urlYoutube = document.getElementById("urlYoutube").value.trim();
      const instruccionesCuestionario = document.getElementById("instruccionesCuestionario").value.trim();

      if (!titulo) {
        Swal.showValidationMessage("Debes ingresar el título del video");
        return false;
      }

   

      // Clasificar preguntas: nuevas, actualizadas y eliminadas
      const preguntasNuevas = [];
      const preguntasActualizadas = [];
      const preguntasEliminadas = [];
      
      // IDs de preguntas originales (las que vinieron del servidor)
      const idsOriginales = preguntas.map(p => p.id_cuestionario.toString());
      
      // IDs de preguntas actuales en el formulario
      const idsActuales = [];
      
      let errorValidacion = null;
      
      document.querySelectorAll(".pregunta-item").forEach((div, i) => {
        if (errorValidacion) return;
        
        const id = div.querySelector('.pregunta-id').value;
        const tipo = div.querySelector(".tipo-btn.active")?.dataset.tipo;

        if (tipo === "incisos") {
          const pregunta = div.querySelector('.pregunta-input').value.trim();
          
          if (!pregunta) {
            errorValidacion = `La pregunta ${i+1} no puede estar vacía`;
            return;
          }

          const opciones = {};
          const opcionesItems = div.querySelectorAll('.opcion-item');
          
          opcionesItems.forEach(item => {
            const letra = item.getAttribute('data-letra');
            const texto = item.querySelector('.opcion-texto').value.trim();
            
            if (!texto) {
              errorValidacion = `La opción ${letra} de la pregunta ${i+1} no puede estar vacía`;
              return;
            }
            
            opciones[letra] = texto;
          });

          if (Object.keys(opciones).length < 2) {
            errorValidacion = `La pregunta ${i+1} debe tener al menos 2 opciones`;
            return;
          }

          const resp = div.querySelector('.select-respuesta').value;
          
          if (!resp) {
            errorValidacion = `Debes seleccionar la respuesta correcta de la pregunta ${i+1}`;
            return;
          }

          // Verificar que la respuesta existe en las opciones
          if (!opciones[resp]) {
            errorValidacion = `La respuesta correcta "${resp}" no existe entre las opciones de la pregunta ${i+1}`;
            return;
          }

          const preguntaData = {
            tipo,
            pregunta,
            opciones,
            respuesta_correcta: resp
          };

          if (id) {
            // Pregunta existente - ACTUALIZAR
            preguntaData.id = id;
            preguntasActualizadas.push(preguntaData);
            idsActuales.push(id);
          } else {
            // Pregunta nueva
            preguntasNuevas.push(preguntaData);
          }

        } else if (tipo === "archivo") {
          const instrucciones = div.querySelector('.instrucciones-archivo').value.trim();
          
          if (!instrucciones) {
            errorValidacion = `La pregunta ${i+1} de tipo archivo debe tener instrucciones`;
            return;
          }

          const preguntaData = {
            tipo,
            pregunta: instrucciones,
            instrucciones: instrucciones
          };

          if (id) {
            // Pregunta existente - ACTUALIZAR
            preguntaData.id = id;
            preguntasActualizadas.push(preguntaData);
            idsActuales.push(id);
          } else {
            // Pregunta nueva
            preguntasNuevas.push(preguntaData);
          }
        }
      });

      if (errorValidacion) {
        Swal.showValidationMessage(errorValidacion);
        return false;
      }

      // Detectar preguntas eliminadas (las que estaban pero ya no están)
      idsOriginales.forEach(idOriginal => {
        if (!idsActuales.includes(idOriginal)) {
          preguntasEliminadas.push(idOriginal);
        }
      });

      if (preguntasNuevas.length === 0 && preguntasActualizadas.length === 0) {
        Swal.showValidationMessage("Debes tener al menos una pregunta en el cuestionario");
        return false;
      }

      return { 
        titulo, 
        descripcion, 
        urlYoutube, 
        preguntasNuevas, 
        preguntasActualizadas, 
        preguntasEliminadas,
        instruccionesCuestionario 
      };
    }
  });

  if (!formValues) return;

  // Mostrar loading
  Swal.fire({
    title: 'Guardando cambios...',
    html: '<p>Por favor espera mientras se actualizan los datos.</p>',
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading();
    }
  });

  // Enviar datos actualizados con clasificación correcta
  const formData = new FormData();
  formData.append("id_video", idVideo);
  formData.append("titulo", formValues.titulo);
  formData.append("descripcion", formValues.descripcion);
  formData.append("instrucciones_cuestionario", formValues.instruccionesCuestionario);

  // Clasificar preguntas según el formato que espera el servidor
  if (formValues.preguntasNuevas.length > 0) {
    formData.append("preguntas_nuevas", JSON.stringify(formValues.preguntasNuevas));
  }

  if (formValues.preguntasActualizadas.length > 0) {
    formData.append("preguntas_actualizadas", JSON.stringify(formValues.preguntasActualizadas));
  }

  if (formValues.preguntasEliminadas.length > 0) {
    formData.append("preguntas_eliminadas", JSON.stringify(formValues.preguntasEliminadas));
  }

  if (formValues.urlYoutube) { 
    formData.append("url_youtube",  formValues.urlYoutube);
}

  // LOG PARA DEBUGGING
  console.log("=== DATOS A ENVIAR ===");
  console.log("ID Video:", idVideo);
  console.log("Título:", formValues.titulo);
  console.log("Preguntas nuevas:", formValues.preguntasNuevas);
  console.log("Preguntas actualizadas:", formValues.preguntasActualizadas);
  console.log("Preguntas eliminadas:", formValues.preguntasEliminadas);

  try {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 300000);

    const updateRes = await fetch("/learning/admin/actualizar_video_completo.php", {
      method: "POST",
      body: formData,
      signal: controller.signal
    });

    clearTimeout(timeoutId);

    console.log("Status de respuesta:", updateRes.status);

    const contentType = updateRes.headers.get("content-type");
    const responseText = await updateRes.text();
    
    console.log("Respuesta del servidor:", responseText);

    if (!contentType || !contentType.includes("application/json")) {
      throw new Error("El servidor no devolvió JSON. Respuesta: " + responseText.substring(0, 500));
    }

    let updateData;
    try {
      updateData = JSON.parse(responseText);
    } catch (parseError) {
      console.error("Error al parsear JSON:", parseError);
      throw new Error("Respuesta inválida del servidor: " + responseText.substring(0, 500));
    }

    console.log("Datos parseados:", updateData);

    if (updateData.success) {
      await Swal.fire({
        icon: 'success',
        title: '¡Cambios guardados!',
        text: updateData.message,
        confirmButtonText: 'Aceptar'
      });
      location.reload();
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Error al guardar',
        html: `
          <p><strong>Mensaje:</strong> ${updateData.message || 'Error desconocido'}</p>
          ${updateData.error ? `<p class="text-muted" style="font-size: 0.9rem;"><strong>Detalle:</strong> ${updateData.error}</p>` : ''}
        `,
        width: '600px'
      });
    }

  } catch (error) {
    console.error("Error completo:", error);
    
    if (error.name === 'AbortError') {
      Swal.fire({
        icon: 'error',
        title: 'Tiempo agotado',
        text: 'La operación tomó demasiado tiempo. Intenta nuevamente.'
      });
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Error de conexión',
        html: `
          <p><strong>Error:</strong> ${error.message}</p>
          <details style="margin-top: 15px; text-align: left;">
            <summary style="cursor: pointer; color: #9b7cb8; font-weight: 600;">Ver detalles técnicos</summary>
            <pre style="background: #f8f9fa; padding: 10px; border-radius: 8px; margin-top: 10px; font-size: 0.8rem; overflow-x: auto;">${error.stack || error.message}</pre>
          </details>
        `,
        width: '700px'
      });
    }
  }

} catch (error) {
  console.error("Error:", error);
  
  Swal.fire({
    icon: 'error',
    title: 'Error',
    text: error.message || 'No se pudieron cargar los datos del video'
  });
}
});
});
</script>
</body>
</html>