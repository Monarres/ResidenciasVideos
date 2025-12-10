<?php
session_start();
require_once("../conexion.php");

// Verificar si es franquiciatario
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'franquiciatario') {
    header("Location: ../index.php");
    exit;
}

$id_franquiciatario = $_SESSION['id_usuario'];

// 🔹 Listar solo usuarios de las unidades del franquiciatario
$stmt = $pdo->prepare("SELECT u.*, 
                       c.nombre as area_nombre, 
                       un.nombre as unidad_nombre
                       FROM usuarios u 
                       LEFT JOIN carpetas c ON u.id_carpeta = c.id_carpeta 
                       LEFT JOIN unidades un ON u.id_unidad = un.id_unidad
                       INNER JOIN unidad_franquiciatarios uf ON u.id_unidad = uf.id_unidad
                       WHERE uf.id_usuario = ? AND u.rol = 'usuario'
                       ORDER BY un.nombre ASC, u.nombre ASC");
$stmt->execute([$id_franquiciatario]);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener unidades del franquiciatario
$stmt = $pdo->prepare("SELECT u.id_unidad, u.nombre 
                       FROM unidades u
                       INNER JOIN unidad_franquiciatarios uf ON u.id_unidad = uf.id_unidad
                       WHERE uf.id_usuario = ?
                       ORDER BY u.nombre ASC");
$stmt->execute([$id_franquiciatario]);
$mis_unidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mis Usuarios</title>
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

.user-info {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-left: auto;
}

.btn-logout {
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

.btn-logout:hover {
  background: #f8f9fa;
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(0,0,0,0.2);
  color: #9b7cb8;
}

.container {
  max-width: 1400px;
  padding: 20px 15px;
}

.card {
  border: none;
  border-radius: 15px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
  background: white;
}

.table {
  border-radius: 15px;
  overflow: hidden;
}

.table thead {
  background: linear-gradient(135deg, #f5a3c7, #9b7cb8);
  color: white;
}

.table thead th {
  border: none;
  padding: 15px;
  font-weight: 600;
}

.table tbody td {
  padding: 12px 15px;
  vertical-align: middle;
}

.table tbody tr {
  transition: 0.3s;
}

.table tbody tr:hover {
  background: rgba(155, 124, 184, 0.05);
}

.badge {
  background: linear-gradient(135deg, #f5a3c7, #9b7cb8);
  font-weight: 500;
  padding: 8px 15px;
  border-radius: 20px;
}

.area-badge {
  background: linear-gradient(135deg, rgba(245, 163, 199, 0.2), rgba(155, 124, 184, 0.2));
  color: #9b7cb8;
  padding: 5px 12px;
  border-radius: 15px;
  font-size: 0.85rem;
  font-weight: 500;
  display: inline-block;
}

.unidad-badge {
  background: linear-gradient(135deg, rgba(23, 162, 184, 0.2), rgba(19, 132, 150, 0.2));
  color: #138496;
  padding: 5px 12px;
  border-radius: 15px;
  font-size: 0.85rem;
  font-weight: 500;
  display: inline-block;
  margin: 2px;
}

.no-area {
  color: #999;
  font-style: italic;
  font-size: 0.9rem;
}

.nav-pills .nav-link {
  color: #9b7cb8;
  font-weight: 500;
  border-radius: 20px;
  padding: 10px 20px;
  margin-right: 10px;
  transition: 0.3s;
  background: white;
  border: 2px solid #e0e0e0;
}

.nav-pills .nav-link:hover {
  background: rgba(155, 124, 184, 0.1);
  border-color: #9b7cb8;
}

.nav-pills .nav-link.active {
  background: linear-gradient(135deg, #f5a3c7, #9b7cb8);
  color: white;
  border-color: transparent;
}

.nav-pills .nav-link .badge {
  font-size: 0.75rem;
  padding: 3px 8px;
}

.nav-pills .nav-link.active .badge {
  background: white !important;
  color: #9b7cb8 !important;
}

.info-box {
  background: linear-gradient(135deg, rgba(245, 163, 199, 0.1), rgba(155, 124, 184, 0.1));
  border-radius: 15px;
  padding: 20px;
  margin-bottom: 20px;
  border: 2px solid rgba(155, 124, 184, 0.2);
}

.info-box h6 {
  color: #9b7cb8;
  font-weight: 600;
  margin-bottom: 10px;
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
  
  .nav-pills .nav-link {
    font-size: 0.85rem;
    padding: 8px 15px;
    margin-right: 5px;
    margin-bottom: 5px;
  }
}
/* ========== RESPONSIVO ========== */

/* Tablets y dispositivos medianos */
@media (max-width: 992px) {
  .top-header h2 {
    font-size: 1.3rem;
  }
  
  .btn-logout {
    padding: 6px 15px;
    font-size: 0.9rem;
  }
  
  .table thead th,
  .table tbody td {
    font-size: 0.85rem;
    padding: 10px 8px;
  }
  
  .nav-pills .nav-link {
    font-size: 0.85rem;
    padding: 8px 15px;
  }
  
  .info-box {
    padding: 15px;
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
    gap: 8px;
  }
  
  .top-header h2 {
    font-size: 1.1rem;
    position: static;
    transform: none;
    text-align: center;
    width: 100%;
  }
  
  .user-info {
    justify-content: center;
    width: 100%;
    margin-left: 0;
  }
  
  .btn-logout {
    padding: 6px 12px;
    font-size: 0.8rem;
  }
  
  .container {
    padding: 15px 10px;
  }
  
  /* Info box */
  .info-box {
    padding: 15px;
    margin-bottom: 15px;
  }
  
  .info-box h6 {
    font-size: 0.95rem;
    margin-bottom: 8px;
  }
  
  /* Card */
  .card {
    padding: 15px !important;
    border-radius: 12px;
  }
  
  .card h5 {
    font-size: 1rem;
  }
  
  /* Badges */
  .badge {
    font-size: 0.75rem;
    padding: 5px 10px;
  }
  
  .area-badge,
  .unidad-badge {
    font-size: 0.75rem;
    padding: 4px 10px;
    margin: 2px;
  }
  
  /* Pestañas responsivas */
  .nav-pills {
    flex-wrap: wrap;
    gap: 5px;
  }
  
  .nav-pills .nav-link {
    font-size: 0.75rem;
    padding: 6px 12px;
    margin-right: 0;
    margin-bottom: 5px;
    white-space: nowrap;
  }
  
  .nav-pills .nav-link .badge {
    font-size: 0.65rem;
    padding: 2px 6px;
  }
  
  /* Tabla responsiva */
  .table {
    font-size: 0.75rem;
    min-width: 600px;
  }
  
  .table thead th {
    padding: 8px 5px;
    font-size: 0.75rem;
  }
  
  .table tbody td {
    padding: 8px 5px;
    font-size: 0.75rem;
  }
  
  /* Alert */
  .alert {
    font-size: 0.85rem;
    padding: 12px;
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
  
  .btn-logout {
    font-size: 0.75rem;
    padding: 5px 10px;
  }
  
  /* Info box más compacto */
  .info-box {
    padding: 12px;
  }
  
  .info-box h6 {
    font-size: 0.9rem;
  }
  
  /* Card header */
  .card .d-flex {
    flex-direction: column;
    align-items: flex-start !important;
    gap: 8px;
  }
  
  .card h5 {
    font-size: 0.95rem;
    margin-bottom: 0 !important;
  }
  
  .badge {
    font-size: 0.7rem;
    padding: 4px 8px;
  }
  
  /* Pestañas en móviles pequeños */
  .nav-pills .nav-link {
    font-size: 0.7rem;
    padding: 5px 10px;
  }
  
  .nav-pills .nav-link i {
    font-size: 0.8rem;
  }
  
  .nav-pills .nav-link .badge {
    font-size: 0.6rem;
    padding: 1px 5px;
  }
  
  /* Tabla más compacta */
  .table {
    min-width: 550px;
    font-size: 0.7rem;
  }
  
  .table thead th,
  .table tbody td {
    padding: 6px 4px;
  }
  
  /* Badges en tabla */
  .area-badge,
  .unidad-badge {
    font-size: 0.7rem;
    padding: 3px 8px;
  }
  
  .no-area {
    font-size: 0.8rem;
  }
  
  /* Alert más compacto */
  .alert {
    font-size: 0.8rem;
    padding: 10px;
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
  
  .btn-logout {
    font-size: 0.7rem;
    padding: 5px 8px;
  }
  
  .info-box {
    padding: 10px;
  }
  
  .info-box h6 {
    font-size: 0.85rem;
  }
  
  .card {
    padding: 12px !important;
  }
  
  .card h5 {
    font-size: 0.9rem;
  }
  
  .nav-pills .nav-link {
    font-size: 0.65rem;
    padding: 4px 8px;
  }
  
  .table {
    min-width: 500px;
    font-size: 0.65rem;
  }
  
  .table thead th,
  .table tbody td {
    padding: 5px 3px;
  }
  
  .area-badge,
  .unidad-badge {
    font-size: 0.65rem;
    padding: 2px 6px;
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
  
  .user-info {
    position: static;
    width: auto;
    margin-left: auto;
  }
  
  .nav-pills .nav-link {
    font-size: 0.75rem;
    padding: 6px 10px;
  }
}

/* Mejoras para tablets en orientación vertical */
@media (min-width: 769px) and (max-width: 992px) {
  .table thead th,
  .table tbody td {
    font-size: 0.8rem;
    padding: 10px;
  }
  
  .nav-pills .nav-link {
    font-size: 0.85rem;
    padding: 8px 15px;
  }
}

/* Accesibilidad táctil */
@media (hover: none) and (pointer: coarse) {
  .btn-logout,
  .nav-pills .nav-link {
    min-height: 44px;
    min-width: 44px;
  }
}

/* Mejora de hover solo en desktop */
@media (hover: hover) and (pointer: fine) {
  .table tbody tr:hover {
    background: rgba(155, 124, 184, 0.05);
  }
  
  .btn-logout:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
  }
  
  .nav-pills .nav-link:hover {
    background: rgba(155, 124, 184, 0.1);
    border-color: #9b7cb8;
  }
}

/* Ajustes para pantallas muy anchas */
@media (min-width: 1400px) {
  .container {
    max-width: 1320px;
  }
  
  .info-box {
    padding: 25px;
  }
  
  .info-box h6 {
    font-size: 1.1rem;
  }
  
  .nav-pills .nav-link {
    font-size: 1rem;
    padding: 12px 25px;
  }
}

/* Optimización para impresión */
@media print {
  body {
    padding-top: 0;
  }
  
  .top-header,
  .nav-pills {
    display: none;
  }
  
  .card {
    box-shadow: none;
    border: 1px solid #ddd;
  }
  
  .table {
    page-break-inside: avoid;
  }
}
  </style>
</head>
<body>

<div class="top-header">
  <div class="container-fluid">
    <h2><i class="fas fa-users"></i> Mis Usuarios</h2>
    <div class="user-info">
      <a href="dashboard.php" class="btn-logout">⬅ Volver</a>
    </div>
  </div>
</div>

<div class="container">
  
  <!-- Info de unidades -->
  <div class="info-box">
    <h6><i class="fas fa-star"></i>  Mis Unidades Asignadas:</h6>
    <?php if(empty($mis_unidades)): ?>
      <p class="text-muted mb-0">No tienes unidades asignadas. Contacta al administrador.</p>
    <?php else: ?>
      <?php foreach($mis_unidades as $unidad): ?>
        <span class="unidad-badge"><i class="fas fa-star"></i>  <?= htmlspecialchars($unidad['nombre']) ?></span>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Tabla de usuarios agrupada por unidad -->
  <div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 style="color: #9b7cb8; font-weight: 600; margin: 0;"><i class="fas fa-list"></i> Usuarios de Mis Unidades</h5>
      <span class="badge"><?= count($usuarios) ?> usuarios</span>
    </div>

    <?php if(empty($mis_unidades)): ?>
      <div class="alert alert-info" style="border-radius: 15px;">
        <strong>¡Sin unidades asignadas!</strong><br>
        No tienes unidades asignadas. Por favor contacta al administrador para que te asigne unidades.
      </div>
    <?php else: ?>
      
      <!-- Pestañas por unidad -->
      <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="pills-todos-tab" data-bs-toggle="pill" data-bs-target="#pills-todos" type="button" role="tab">
            <i class="fas fa-chart-bar"></i> Todos
            <span class="badge bg-light text-dark ms-1"><?= count($usuarios) ?></span>
          </button>
        </li>
        <?php foreach($mis_unidades as $index => $unidad): ?>
          <?php 
          $usuarios_unidad = array_filter($usuarios, function($u) use ($unidad) {
              return $u['id_unidad'] == $unidad['id_unidad'];
          });
          ?>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="pills-unidad<?= $unidad['id_unidad'] ?>-tab" 
                    data-bs-toggle="pill" 
                    data-bs-target="#pills-unidad<?= $unidad['id_unidad'] ?>" 
                    type="button" role="tab">
              <i class="fas fa-star"></i> <?= htmlspecialchars($unidad['nombre']) ?>
              <span class="badge bg-light text-dark ms-1"><?= count($usuarios_unidad) ?></span>
            </button>
          </li>
        <?php endforeach; ?>
      </ul>

      <!-- Contenido de pestañas -->
      <div class="tab-content" id="pills-tabContent">
        
        <!-- Tab TODOS -->
        <div class="tab-pane fade show active" id="pills-todos" role="tabpanel">
          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Nombre</th>
                  <th>Correo</th>
                  <th>Área</th>
                  <th>Unidad</th>
                </tr>
              </thead>
              <tbody>
                <?php if(empty($usuarios)): ?>
                  <tr>
                    <td colspan="5" class="text-center text-muted">No hay usuarios en tus unidades</td>
                  </tr>
                <?php else: ?>
                  <?php $i=1; foreach($usuarios as $u): ?>
                  <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($u['nombre']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                      <?php if($u['area_nombre']): ?>
                        <span class="area-badge"><?= htmlspecialchars($u['area_nombre']) ?></span>
                      <?php else: ?>
                        <span class="no-area">—</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if($u['unidad_nombre']): ?>
                        <span class="unidad-badge"><i class="fas fa-star"></i> <?= htmlspecialchars($u['unidad_nombre']) ?></span>
                      <?php else: ?>
                        <span class="no-area">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Tabs por unidad -->
        <?php foreach($mis_unidades as $unidad): ?>
          <div class="tab-pane fade" id="pills-unidad<?= $unidad['id_unidad'] ?>" role="tabpanel">
            <div class="table-responsive">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Área</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $usuarios_unidad = array_filter($usuarios, function($u) use ($unidad) {
                      return $u['id_unidad'] == $unidad['id_unidad'];
                  });
                  
                  if(empty($usuarios_unidad)): 
                  ?>
                    <tr>
                      <td colspan="4" class="text-center text-muted">No hay usuarios en esta unidad</td>
                    </tr>
                  <?php else: ?>
                    <?php $i=1; foreach($usuarios_unidad as $u): ?>
                    <tr>
                      <td><?= $i++ ?></td>
                      <td><?= htmlspecialchars($u['nombre']) ?></td>
                      <td><?= htmlspecialchars($u['email']) ?></td>
                      <td>
                        <?php if($u['area_nombre']): ?>
                          <span class="area-badge"><?= htmlspecialchars($u['area_nombre']) ?></span>
                        <?php else: ?>
                          <span class="no-area">—</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endforeach; ?>

      </div>
    <?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>