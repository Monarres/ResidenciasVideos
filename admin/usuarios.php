<?php
session_start();
require_once("../conexion.php");

// Verificar si es admin
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$mensaje = "";
$tipo_mensaje = "";

// 🔹 Insertar nuevo usuario
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['accion']) && $_POST['accion'] === "agregar") {
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $contrasena = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $rol = $_POST['rol'];
    $id_carpeta = ($rol === 'usuario' && !empty($_POST['id_carpeta'])) ? (int)$_POST['id_carpeta'] : null;
    $id_unidad = ($rol === 'usuario' && !empty($_POST['id_unidad'])) ? (int)$_POST['id_unidad'] : null;

    if (!empty($nombre) && !empty($email) && !empty($_POST['password'])) {
        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, contrasena, rol, id_carpeta, id_unidad) 
                               VALUES (:nombre, :email, :contrasena, :rol, :id_carpeta, :id_unidad)");
        try {
            $stmt->execute([
                'nombre' => $nombre,
                'email' => $email,
                'contrasena' => $contrasena,
                'rol' => $rol,
                'id_carpeta' => $id_carpeta,
                'id_unidad' => $id_unidad
            ]);
            header("Location: usuarios.php?msg=success");
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $mensaje = "El correo ya está registrado.";
                $tipo_mensaje = "danger";
            } else {
                $mensaje = "Error: " . $e->getMessage();
                $tipo_mensaje = "danger";
            }
        }
    } else {
        $mensaje = "Todos los campos son obligatorios.";
        $tipo_mensaje = "danger";
    }
}

// 🔹 Editar usuario
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['accion']) && $_POST['accion'] === "editar") {
    $id = (int) $_POST['id_usuario'];
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $rol = $_POST['rol'];
    $id_carpeta = ($rol === 'usuario' && !empty($_POST['id_carpeta'])) ? (int)$_POST['id_carpeta'] : null;
    $id_unidad = ($rol === 'usuario' && !empty($_POST['id_unidad'])) ? (int)$_POST['id_unidad'] : null;

    if (!empty($_POST['password'])) {
        $contrasena = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql = "UPDATE usuarios SET nombre = :nombre, email = :email, contrasena = :contrasena, rol = :rol, id_carpeta = :id_carpeta, id_unidad = :id_unidad WHERE id_usuario = :id";
        $params = ['nombre' => $nombre, 'email' => $email, 'contrasena' => $contrasena, 'rol' => $rol, 'id_carpeta' => $id_carpeta, 'id_unidad' => $id_unidad, 'id' => $id];
    } else {
        $sql = "UPDATE usuarios SET nombre = :nombre, email = :email, rol = :rol, id_carpeta = :id_carpeta, id_unidad = :id_unidad WHERE id_usuario = :id";
        $params = ['nombre' => $nombre, 'email' => $email, 'rol' => $rol, 'id_carpeta' => $id_carpeta, 'id_unidad' => $id_unidad, 'id' => $id];
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        header("Location: usuarios.php?msg=updated");
        exit;
    } catch (PDOException $e) {
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// 🔹 Eliminar usuario
if (isset($_GET['eliminar'])) {
    $id = (int) $_GET['eliminar'];
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT 
                              (SELECT COUNT(*) FROM respuestas_usuario WHERE id_usuario = ?) as total_incisos,
                              (SELECT COUNT(*) FROM respuestas_archivo WHERE id_usuario = ?) as total_archivos");
        $stmt->execute([$id, $id]);
        $totales = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $tiene_respuestas = ($totales['total_incisos'] > 0 || $totales['total_archivos'] > 0);
        
        if ($tiene_respuestas) {
            $stmt = $pdo->prepare("SELECT ruta_archivo FROM respuestas_archivo WHERE id_usuario = ?");
            $stmt->execute([$id]);
            $archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($archivos as $archivo) {
                if (file_exists($archivo['ruta_archivo'])) {
                    unlink($archivo['ruta_archivo']);
                }
            }
            
            $stmt = $pdo->prepare("DELETE FROM respuestas_archivo WHERE id_usuario = ?");
            $stmt->execute([$id]);
            
            $stmt = $pdo->prepare("DELETE FROM respuestas_usuario WHERE id_usuario = ?");
            $stmt->execute([$id]);
        }
        
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$id]);
        
        $pdo->commit();
        header("Location: usuarios.php?msg=deleted");
        exit;
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// Mensajes
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'success': $mensaje = "✅ Usuario agregado"; $tipo_mensaje = "success"; break;
        case 'updated': $mensaje = "✅ Usuario actualizado"; $tipo_mensaje = "success"; break;
        case 'deleted': $mensaje = "✅ Usuario eliminado"; $tipo_mensaje = "success"; break;
    }
}

// 🔹 Listar usuarios
$stmt = $pdo->query("SELECT u.*, 
                     c.nombre as area_nombre, 
                     un.nombre as unidad_nombre,
                     GROUP_CONCAT(DISTINCT un_franq.nombre SEPARATOR ', ') as unidades_franquiciatario
                     FROM usuarios u 
                     LEFT JOIN carpetas c ON u.id_carpeta = c.id_carpeta 
                     LEFT JOIN unidades un ON u.id_unidad = un.id_unidad
                     LEFT JOIN unidad_franquiciatarios uf ON u.id_usuario = uf.id_usuario
                     LEFT JOIN unidades un_franq ON uf.id_unidad = un_franq.id_unidad
                     GROUP BY u.id_usuario
                     ORDER BY u.id_usuario DESC");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt_areas = $pdo->query("SELECT id_carpeta, nombre FROM carpetas WHERE id_padre IS NULL ORDER BY nombre ASC");
$areas = $stmt_areas->fetchAll(PDO::FETCH_ASSOC);

$stmt_unidades = $pdo->query("SELECT id_unidad, nombre, direccion FROM unidades ORDER BY nombre ASC");
$unidades_disponibles = $stmt_unidades->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestión de Usuarios</title>
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

.form-control, .form-select {
  border-radius: 25px;
  padding: 10px 20px;
  border: 1px solid #ddd;
}

.btn-primary {
  background: linear-gradient(135deg, #f5a3c7, #9b7cb8);
  border: none;
  color: white;
  font-weight: 500;
  border-radius: 25px;
  padding: 10px 25px;
  transition: 0.3s;
}

.btn-primary:hover {
  background: linear-gradient(135deg, #9b7cb8, #f5a3c7);
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(155, 124, 184, 0.3);
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

.btn-warning {
  background: linear-gradient(135deg, #ffc107, #ff9800);
  border: none;
  color: white;
  font-weight: 500;
  border-radius: 20px;
  padding: 6px 15px;
  transition: 0.3s;
}

.btn-warning:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(255, 193, 7, 0.4);
}

.btn-danger {
  background: linear-gradient(135deg, #dc3545, #c82333);
  border: none;
  color: white;
  font-weight: 500;
  border-radius: 20px;
  padding: 6px 15px;
  transition: 0.3s;
}

.btn-danger:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
}

.btn-reset {
  background: linear-gradient(135deg, #17a2b8, #138496);
  border: none;
  color: white;
  font-weight: 500;
  border-radius: 20px;
  padding: 6px 15px;
  transition: 0.3s;
}

.btn-reset:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(23, 162, 184, 0.4);
}

.alert {
  border-radius: 15px;
  border: none;
}

.modal-content {
  border-radius: 20px;
  border: none;
}

.modal-header {
  background: linear-gradient(135deg, #f5a3c7, #9b7cb8);
  color: white;
  border-radius: 20px 20px 0 0;
}

.modal-header .btn-close {
  filter: brightness(0) invert(1);
}

.badge {
  background: linear-gradient(135deg, #f5a3c7, #9b7cb8);
  font-weight: 500;
  padding: 8px 15px;
  border-radius: 20px;
}

.select-area-container {
  display: none;
  margin-top: 15px;
  padding: 15px;
  background: linear-gradient(135deg, rgba(245, 163, 199, 0.1), rgba(155, 124, 184, 0.1));
  border-radius: 15px;
  border: 2px dashed #9b7cb8;
}

.select-area-container.show {
  display: block;
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

/* ========== RESPONSIVO ========== */

@media (max-width: 992px) {
  .table thead th,
  .table tbody td {
    font-size: 0.85rem;
    padding: 10px 8px;
  }
  
  .btn-warning,
  .btn-danger,
  .btn-reset {
    padding: 5px 10px;
    font-size: 0.8rem;
  }
}

@media (max-width: 768px) {
  body {
    padding-top: 140px;
  }
  
  .top-header {
    margin: 10px;
    padding: 10px 0;
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
  }
  
  .btn-logout {
    padding: 6px 12px;
    font-size: 0.8rem;
  }
  
  .table {
    font-size: 0.75rem;
    min-width: 650px;
  }
  
  .table thead th,
  .table tbody td {
    padding: 8px 5px;
    font-size: 0.75rem;
  }
  
  .btn-warning,
  .btn-danger,
  .btn-reset {
    padding: 5px 8px;
    font-size: 0.7rem;
    margin: 2px;
  }
  
  .nav-pills .nav-link {
    font-size: 0.75rem;
    padding: 6px 12px;
    margin-right: 0;
    margin-bottom: 5px;
  }
  
  .area-badge,
  .unidad-badge {
    font-size: 0.75rem;
    padding: 4px 10px;
  }
}

@media (max-width: 576px) {
  body {
    padding-top: 150px;
  }
  
  .top-header h2 {
    font-size: 1rem;
  }
  
  .btn-warning,
  .btn-danger,
  .btn-reset {
    padding: 8px;
    border-radius: 50%;
    width: 35px;
    height: 35px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  
  .btn-warning span,
  .btn-danger span,
  .btn-reset span {
    display: none;
  }
  
  .table {
    min-width: 600px;
  }
}

</style>
</head>
<body>

<div class="top-header">
  <div class="container-fluid">
    <h2><i class="fas fa-users"></i> Gestión de Usuarios</h2>
    <div class="user-info">
      <a href="unidades.php" class="btn-logout"><i class="fas fa-star"></i> Sucursales</a>
      <a href="dashboard.php" class="btn-logout"><i class="fas fa-arrow-left"></i> Volver</a>
    </div>
  </div>
</div>

<div class="container">
  <!-- Mensajes -->
  <?php if (!empty($mensaje)): ?>
    <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show" role="alert">
      <?= $mensaje ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- Formulario para agregar -->
  <div class="card p-4 mb-4">
    <h5 class="mb-3" style="color: #9b7cb8; font-weight: 600;"><i class="fas fa-plus-circle"></i> Agregar Nuevo Usuario</h5>
    <form method="POST" id="formAgregarUsuario">
      <input type="hidden" name="accion" value="agregar">
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label fw-bold">Nombre</label>
          <input type="text" name="nombre" class="form-control" placeholder="Nombre completo" required>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold">Correo</label>
          <input type="email" name="email" class="form-control" placeholder="correo@ejemplo.com" required>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-bold">Contraseña</label>
          <input type="password" name="password" class="form-control" placeholder="●●●●●●" required>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-bold">Rol</label>
          <select name="rol" id="rolSelect" class="form-select" required>
            <option value="">Seleccionar...</option>
            <option value="admin">Administrador</option>
            <option value="franquiciatario">Franquiciatario</option>
            <option value="usuario">Usuario</option>
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button class="btn btn-primary w-100" type="submit">Agregar Usuario</button>
        </div>
      </div>
      
      <div id="areaUnidadContainer" class="select-area-container">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-bold" style="color: #9b7cb8;"><i class="fas fa-credit-card"></i>  Seleccionar Área de Acceso</label>
            <select name="id_carpeta" id="areaSelect" class="form-select">
              <option value="">-- Seleccionar área --</option>
              <?php foreach($areas as $area): ?>
                <option value="<?= $area['id_carpeta'] ?>"><?= htmlspecialchars($area['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted d-block mt-2">¡Área a la que tendrá acceso el usuario!</small>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold" style="color: #138496;"><i class="fas fa-star"></i> Seleccionar Sucursal</label>
            <select name="id_unidad" id="unidadSelect" class="form-select">
              <option value="">-- Seleccionar Sucursal --</option>
              <?php foreach($unidades_disponibles as $unidad): ?>
                <option value="<?= $unidad['id_unidad'] ?>">
                  <?= htmlspecialchars($unidad['nombre']) ?> 
                  <small>(<?= htmlspecialchars($unidad['direccion']) ?>)</small>
                </option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted d-block mt-2">¡Sucursal a la que pertenece el usuario!</small>
          </div>
        </div>
      </div>
    </form>
  </div>
<!-- Tabla de usuarios con pestañas -->
  <div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 style="color: #9b7cb8; font-weight: 600; margin: 0;"><i class="fas fa-list"></i>  Lista de Usuarios</h5>
      <span class="badge"><?= count($usuarios) ?> usuarios totales</span>
    </div>
    
    <!-- Pestañas de navegación -->
    <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="pills-usuarios-tab" data-bs-toggle="pill" data-bs-target="#pills-usuarios" type="button" role="tab">
          <i class="fas fa-users"></i> Usuarios
          <span class="badge bg-light text-dark ms-1">
            <?php 
            $count_usuarios = count(array_filter($usuarios, function($u) {
                return $u['rol'] === 'usuario';
            }));
            echo $count_usuarios;
            ?>
          </span>
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="pills-franquiciatarios-tab" data-bs-toggle="pill" data-bs-target="#pills-franquiciatarios" type="button" role="tab">
          <i class="fas fa-star"></i> Franquiciatarios
          <span class="badge bg-light text-dark ms-1">
            <?php 
            $count_franq = count(array_filter($usuarios, function($u) {
                return $u['rol'] === 'franquiciatario';
            }));
            echo $count_franq;
            ?>
          </span>
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="pills-admin-tab" data-bs-toggle="pill" data-bs-target="#pills-admin" type="button" role="tab">
          <i class="fa-solid fa-crown"></i> Administradores
          <span class="badge bg-light text-dark ms-1">
            <?php 
            $count_admin = count(array_filter($usuarios, function($u) {
                return $u['rol'] === 'admin';
            }));
            echo $count_admin;
            ?>
          </span>
        </button>
      </li>
    </ul>

    <!-- Contenido de las pestañas -->
    <div class="tab-content" id="pills-tabContent">
      
      <!-- Tab Usuarios -->
      <div class="tab-pane fade show active" id="pills-usuarios" role="tabpanel">
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>#</th>
                <th>Nombre</th>
                <th>Correo</th>
                <th>Área</th>
                <th>Sucursal</th>
                <th style="text-align: center;">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              $usuarios_filtrados = array_filter($usuarios, function($u) {
                  return $u['rol'] === 'usuario';
              });
              if(empty($usuarios_filtrados)): 
              ?>
                <tr>
                  <td colspan="6" class="text-center text-muted">No hay usuarios registrados</td>
                </tr>
              <?php else: ?>
                <?php $i=1; foreach ($usuarios_filtrados as $u): ?>
                <tr>
                  <td><?= $i++ ?></td>
                  <td><?= htmlspecialchars($u['nombre']) ?></td>
                  <td><?= htmlspecialchars($u['email']) ?></td>
                  <td>
                    <?php if($u['area_nombre']): ?>
                      <span class="area-badge"><i class="fas fa-credit-card"></i> <?= htmlspecialchars($u['area_nombre']) ?></span>
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
                  <td style="text-align: center;">
                    <button class="btn btn-warning btn-sm me-1"
                            data-bs-toggle="modal"
                            data-bs-target="#modalEditar"
                            data-id="<?= $u['id_usuario'] ?>"
                            data-nombre="<?= htmlspecialchars($u['nombre']) ?>"
                            data-email="<?= htmlspecialchars($u['email']) ?>"
                            data-rol="<?= $u['rol'] ?>"
                            data-carpeta="<?= $u['id_carpeta'] ?? '' ?>"
                            data-unidad="<?= $u['id_unidad'] ?? '' ?>">
                      <i class="fas fa-edit"></i> Editar
                    </button>
                    
                    <button class="btn btn-danger btn-sm btn-delete me-1"
                            data-id="<?= $u['id_usuario'] ?>"
                            data-nombre="<?= htmlspecialchars($u['nombre']) ?>">
                      <i class="fas fa-trash-alt"></i> Eliminar
                    </button>
                    
                    <button class="btn btn-reset btn-sm"
                            data-id="<?= $u['id_usuario'] ?>"
                            data-nombre="<?= htmlspecialchars($u['nombre']) ?>">
                      <i class="fa-solid fa-rotate-right"></i> Rehacer
                    </button>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tab Franquiciatarios -->
      <div class="tab-pane fade" id="pills-franquiciatarios" role="tabpanel">
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>#</th>
                <th>Nombre</th>
                <th>Correo</th>
                <th>Sucursales Asignadas</th>
                <th style="text-align: center;">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              $franquiciatarios_filtrados = array_filter($usuarios, function($u) {
                  return $u['rol'] === 'franquiciatario';
              });
              if(empty($franquiciatarios_filtrados)): 
              ?>
                <tr>
                  <td colspan="5" class="text-center text-muted">No hay franquiciatarios registrados</td>
                </tr>
              <?php else: ?>
                <?php $i=1; foreach ($franquiciatarios_filtrados as $u): ?>
                <tr>
                  <td><?= $i++ ?></td>
                  <td><?= htmlspecialchars($u['nombre']) ?></td>
                  <td><?= htmlspecialchars($u['email']) ?></td>
                  <td>
                    <?php if($u['unidades_franquiciatario']): ?>
                      <?php 
                        $unidades = explode(', ', $u['unidades_franquiciatario']);
                        foreach($unidades as $unidad): 
                      ?>
                        <span class="unidad-badge"><i class="fas fa-star"></i> <?= htmlspecialchars($unidad) ?></span>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <span class="no-area">Sin Sucursales asignadas</span>
                    <?php endif; ?>
                  </td>
                  <td style="text-align: center;">
                    <button class="btn btn-warning btn-sm me-1"
                            data-bs-toggle="modal"
                            data-bs-target="#modalEditar"
                            data-id="<?= $u['id_usuario'] ?>"
                            data-nombre="<?= htmlspecialchars($u['nombre']) ?>"
                            data-email="<?= htmlspecialchars($u['email']) ?>"
                            data-rol="<?= $u['rol'] ?>"
                            data-carpeta="<?= $u['id_carpeta'] ?? '' ?>"
                            data-unidad="<?= $u['id_unidad'] ?? '' ?>">
                      <i class="fas fa-edit"></i> Editar
                    </button>
                    
                    <button class="btn btn-danger btn-sm btn-delete"
                            data-id="<?= $u['id_usuario'] ?>"
                            data-nombre="<?= htmlspecialchars($u['nombre']) ?>">
                      <i class="fas fa-trash-alt"></i> Eliminar
                    </button>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tab Administradores -->
      <div class="tab-pane fade" id="pills-admin" role="tabpanel">
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>#</th>
                <th>Nombre</th>
                <th>Correo</th>
                <th style="text-align: center;">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              $admin_filtrados = array_filter($usuarios, function($u) {
                  return $u['rol'] === 'admin';
              });
              if(empty($admin_filtrados)): 
              ?>
                <tr>
                  <td colspan="4" class="text-center text-muted">No hay administradores registrados</td>
                </tr>
              <?php else: ?>
                <?php $i=1; foreach ($admin_filtrados as $u): ?>
                <tr>
                  <td><?= $i++ ?></td>
                  <td><?= htmlspecialchars($u['nombre']) ?></td>
                  <td><?= htmlspecialchars($u['email']) ?></td>
                  <td style="text-align: center;">
                    <button class="btn btn-warning btn-sm me-1"
                            data-bs-toggle="modal"
                            data-bs-target="#modalEditar"
                            data-id="<?= $u['id_usuario'] ?>"
                            data-nombre="<?= htmlspecialchars($u['nombre']) ?>"
                            data-email="<?= htmlspecialchars($u['email']) ?>"
                            data-rol="<?= $u['rol'] ?>"
                            data-carpeta="<?= $u['id_carpeta'] ?? '' ?>"
                            data-unidad="<?= $u['id_unidad'] ?? '' ?>">
                      <i class="fas fa-edit"></i> Editar
                    </button>
                    
                    <button class="btn btn-danger btn-sm btn-delete"
                            data-id="<?= $u['id_usuario'] ?>"
                            data-nombre="<?= htmlspecialchars($u['nombre']) ?>">
                      <i class="fas fa-trash-alt"></i> Eliminar
                    </button>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>
<!-- Modal Editar -->
<div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="POST" class="modal-content" id="formEditarUsuario">
      <input type="hidden" name="accion" value="editar">
      <input type="hidden" name="id_usuario" id="edit-id">
      <div class="modal-header">
        <h5 class="modal-title"> Editar Usuario</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-bold">Nombre</label>
            <input type="text" class="form-control" name="nombre" id="edit-nombre" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold">Correo</label>
            <input type="email" class="form-control" name="email" id="edit-email" required>
          </div>
        </div>
        <div class="mb-3 mt-3">
          <label class="form-label fw-bold">Nueva Contraseña (opcional)</label>
          <input type="password" class="form-control" name="password" placeholder="Dejar vacío para no cambiar">
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">Rol</label>
          <select name="rol" id="edit-rol" class="form-select" required>
            <option value="admin">Administrador</option>
            <option value="franquiciatario">Franquiciatario</option>
            <option value="usuario">Usuario</option>
          </select>
        </div>
        
        <!-- Panel de selección de área y unidad en el modal -->
        <div id="editAreaUnidadContainer" class="select-area-container">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-bold" style="color: #9b7cb8;">
                 Área de Acceso
              </label>
              <select name="id_carpeta" id="edit-area" class="form-select">
                <option value="">-- Seleccionar área --</option>
                <?php foreach($areas as $area): ?>
                  <option value="<?= $area['id_carpeta'] ?>"><?= htmlspecialchars($area['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold" style="color: #138496;">
                Sucursal
              </label>
              <select name="id_unidad" id="edit-unidad" class="form-select">
                <option value="">-- Seleccionar Sucursal --</option>
                <?php foreach($unidades_disponibles as $unidad): ?>
                  <option value="<?= $unidad['id_unidad'] ?>">
                    <?= htmlspecialchars($unidad['nombre']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
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

<!-- Modal para Resetear Respuestas -->
<div class="modal fade" id="modalResetear" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"> Resetear Respuestas de Usuario</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="reset-id-usuario">
        
        <div class="alert alert-warning" style="border-radius: 15px;">
          <strong> ¡Advertencia!:</strong><br>
          Se eliminarán todas las respuestas (cuestionarios y archivos) del módulo seleccionado.
        </div>
        
        <div class="mb-3">
          <label class="form-label fw-bold">Usuario:</label>
          <p id="reset-nombre-usuario" class="text-muted"></p>
        </div>
        
        <div class="mb-3">
          <label class="form-label fw-bold" style="color: #9b7cb8;"><i class="fa-solid fa-book icono-libro" style="color: #B197FC;"></i>Módulo a Resetear</label>
          <select id="reset-modulo" class="form-select" required>
            <option value="">-- Seleccionar módulo --</option>
          </select>
        </div>
        
        <div id="reset-detalles" style="display: none; background: #f8f9fa; padding: 15px; border-radius: 10px; margin-top: 15px;">
          <strong>Respuestas a eliminar:</strong>
          <ul style="margin-top: 10px; margin-bottom: 0;">
            <li><i class="fa-solid fa-clipboard-list" style="color: #B197FC;"></i> <span id="reset-total-incisos">0</span> respuesta(s) de cuestionarios</li>
            <li><i class="fa-solid fa-paperclip" style="color: #B197FC;"></i> <span id="reset-total-archivos">0</span> archivo(s) subido(s)</li>
          </ul>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="btnConfirmarReset" disabled>
          <i class="fa-solid fa-trash-can" style="color: #ff0000;"></i> Eliminar Respuestas
        </button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Mostrar/ocultar selector de área y unidad según el rol (formulario agregar)
const rolSelect = document.getElementById('rolSelect');
const areaUnidadContainer = document.getElementById('areaUnidadContainer');
const areaSelect = document.getElementById('areaSelect');
const unidadSelect = document.getElementById('unidadSelect');
const areaColumn = areaSelect.closest('.col-md-6');

rolSelect.addEventListener('change', function() {
  if (this.value === 'usuario') {
    // Usuario: mostrar ÁREA y UNIDAD
    areaUnidadContainer.classList.add('show');
    areaColumn.style.display = 'block';
    areaSelect.required = false;
    unidadSelect.required = false;
  } else if (this.value === 'franquiciatario') {
    // Franquiciatario: mostrar SOLO UNIDAD (ocultar columna de área)
    areaUnidadContainer.classList.add('show');
    areaColumn.style.display = 'none';
    areaSelect.value = '';
    areaSelect.required = false;
    unidadSelect.required = false;
  } else {
    // Admin: ocultar todo
    areaUnidadContainer.classList.remove('show');
    areaColumn.style.display = 'block';
    areaSelect.required = false;
    unidadSelect.required = false;
    areaSelect.value = '';
    unidadSelect.value = '';
  }
});

// Mostrar/ocultar selector de área y unidad según el rol (modal editar)
const editRolSelect = document.getElementById('edit-rol');
const editAreaUnidadContainer = document.getElementById('editAreaUnidadContainer');
const editAreaSelect = document.getElementById('edit-area');
const editUnidadSelect = document.getElementById('edit-unidad');
const editAreaColumn = editAreaSelect.closest('.col-md-6');

editRolSelect.addEventListener('change', function() {
  if (this.value === 'usuario') {
    // Usuario: mostrar ÁREA y UNIDAD
    editAreaUnidadContainer.classList.add('show');
    editAreaColumn.style.display = 'block';
    editAreaSelect.required = false;
    editUnidadSelect.required = false;
  } else if (this.value === 'franquiciatario') {
    // Franquiciatario: mostrar SOLO UNIDAD (ocultar columna de área)
    editAreaUnidadContainer.classList.add('show');
    editAreaColumn.style.display = 'none';
    editAreaSelect.value = '';
    editAreaSelect.required = false;
    editUnidadSelect.required = false;
  } else {
    // Admin: ocultar todo
    editAreaUnidadContainer.classList.remove('show');
    editAreaColumn.style.display = 'block';
    editAreaSelect.required = false;
    editUnidadSelect.required = false;
    editAreaSelect.value = '';
    editUnidadSelect.value = '';
  }
});

// Pasar datos al modal de edición
const modalEditar = document.getElementById('modalEditar');
modalEditar.addEventListener('show.bs.modal', event => {
  const button = event.relatedTarget;
  const rol = button.getAttribute('data-rol');
  
  document.getElementById('edit-id').value = button.getAttribute('data-id');
  document.getElementById('edit-nombre').value = button.getAttribute('data-nombre');
  document.getElementById('edit-email').value = button.getAttribute('data-email');
  document.getElementById('edit-rol').value = rol;
  
  const carpetaId = button.getAttribute('data-carpeta');
  const unidadId = button.getAttribute('data-unidad');
  document.getElementById('edit-area').value = carpetaId || '';
  document.getElementById('edit-unidad').value = unidadId || '';
  
  // Mostrar u ocultar el selector de área y unidad según el rol
  if (rol === 'usuario') {
    // Usuario: mostrar área y unidad
    editAreaUnidadContainer.classList.add('show');
    editAreaColumn.style.display = 'block';
    editAreaSelect.required = false;
    editUnidadSelect.required = false;
  } else if (rol === 'franquiciatario') {
    // Franquiciatario: mostrar solo unidad
    editAreaUnidadContainer.classList.add('show');
    editAreaColumn.style.display = 'none';
    editAreaSelect.required = false;
    editUnidadSelect.required = false;
  } else {
    // Admin: ocultar todo
    editAreaUnidadContainer.classList.remove('show');
    editAreaColumn.style.display = 'block';
    editAreaSelect.required = false;
    editUnidadSelect.required = false;
  }
});

// Eliminar usuario con SweetAlert2
document.querySelectorAll('.btn-delete').forEach(btn => {
  btn.addEventListener('click', async (e) => {
    e.preventDefault();
    const id = btn.dataset.id;
    const nombre = btn.dataset.nombre;
    
    try {
      const response = await fetch(`verificar_respuestas.php?id_usuario=${id}`);
      const data = await response.json();
      
      let mensaje = `Se eliminará al usuario: <strong>${nombre}</strong>`;
      let icon = 'warning';
      
      if (data.tiene_respuestas) {
        mensaje += `<br><br><div style="background: #fff3cd; padding: 15px; border-radius: 10px; margin-top: 15px;">
          <strong><i class="fa-solid fa-triangle-exclamation" style="color: #FFD43B;"></i> ATENCIÓN:</strong><br>
          Este usuario tiene respuestas guardadas:<br>
          <ul style="text-align: left; margin-top: 10px;">
            <li><i class="fa-solid fa-clipboard-list" style="color: #B197FC;"></i> ${data.total_incisos} respuesta(s) de cuestionarios</li>
            <li><i class="fa-solid fa-paperclip" style="color: #B197FC;"></i> ${data.total_archivos} archivo(s) subido(s)</li>
          </ul>
          <strong style="color: #856404;">Todas estas respuestas serán eliminadas permanentemente.</strong>
        </div>`;
        icon = 'error';
      }
      
      const result = await Swal.fire({
        title: '¿Eliminar usuario?',
        html: mensaje,
        icon: icon,
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar todo',
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        width: '600px'
      });

      if (result.isConfirmed) {
        Swal.fire({
          title: 'Eliminando...',
          text: 'Por favor espera',
          allowOutsideClick: false,
          allowEscapeKey: false,
          showConfirmButton: false,
          willOpen: () => {
            Swal.showLoading();
          }
        });
        
        window.location.href = `usuarios.php?eliminar=${id}`;
      }
      
    } catch (error) {
      console.error('Error:', error);
      const result = await Swal.fire({
        title: '¿Eliminar usuario?',
        text: `Se eliminará a ${nombre} y todas sus respuestas`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
      });

      if (result.isConfirmed) {
        window.location.href = `usuarios.php?eliminar=${id}`;
      }
    }
  });
});
// Abrir modal de resetear respuestas
document.querySelectorAll('.btn-reset').forEach(btn => {
  btn.addEventListener('click', async () => {
    const idUsuario = btn.dataset.id;
    const nombreUsuario = btn.dataset.nombre;
    
    document.getElementById('reset-id-usuario').value = idUsuario;
    document.getElementById('reset-nombre-usuario').textContent = nombreUsuario;
    document.getElementById('reset-detalles').style.display = 'none';
    document.getElementById('btnConfirmarReset').disabled = true;
    
    try {
      const response = await fetch(`obtener_modulos_usuario.php?id_usuario=${idUsuario}`);
      const data = await response.json();
      
      const selectModulo = document.getElementById('reset-modulo');
      selectModulo.innerHTML = '<option value="">-- Seleccionar módulo --</option>';
      
      if (data.modulos && data.modulos.length > 0) {
        data.modulos.forEach(modulo => {
          const option = document.createElement('option');
          option.value = modulo.id_carpeta;
          option.textContent = `${modulo.nombre} (${modulo.total_respuestas} respuestas)`;
          option.dataset.incisos = modulo.total_incisos;
          option.dataset.archivos = modulo.total_archivos;
          selectModulo.appendChild(option);
        });
        
        const modal = new bootstrap.Modal(document.getElementById('modalResetear'));
        modal.show();
      } else {
        Swal.fire({
          title: 'Sin respuestas',
          text: 'Este usuario no tiene respuestas registradas en ningún módulo.',
          icon: 'info',
          confirmButtonText: 'Entendido'
        });
      }
    } catch (error) {
      console.error('Error:', error);
      Swal.fire({
        title: 'Error',
        text: 'No se pudieron cargar los módulos del usuario.',
        icon: 'error'
      });
    }
  });
});

// Mostrar detalles al seleccionar módulo
document.getElementById('reset-modulo').addEventListener('change', function() {
  const selectedOption = this.options[this.selectedIndex];
  const detallesDiv = document.getElementById('reset-detalles');
  const btnConfirmar = document.getElementById('btnConfirmarReset');
  
  if (this.value) {
    const totalIncisos = selectedOption.dataset.incisos || 0;
    const totalArchivos = selectedOption.dataset.archivos || 0;
    
    document.getElementById('reset-total-incisos').textContent = totalIncisos;
    document.getElementById('reset-total-archivos').textContent = totalArchivos;
    
    detallesDiv.style.display = 'block';
    btnConfirmar.disabled = false;
  } else {
    detallesDiv.style.display = 'none';
    btnConfirmar.disabled = true;
  }
});

// Confirmar reseteo
document.getElementById('btnConfirmarReset').addEventListener('click', async function() {
  const idUsuario = document.getElementById('reset-id-usuario').value;
  const idModulo = document.getElementById('reset-modulo').value;
  const nombreModulo = document.getElementById('reset-modulo').options[document.getElementById('reset-modulo').selectedIndex].text;
  
  const result = await Swal.fire({
    title: '¿Confirmar eliminación?',
    html: `Se eliminarán <strong>todas las respuestas</strong> del módulo:<br><br><strong>${nombreModulo}</strong><br><br>El usuario deberá volver a contestar este módulo completo.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, eliminar',
    confirmButtonColor: '#dc3545',
    cancelButtonText: 'Cancelar',
    reverseButtons: true
  });
  
  if (result.isConfirmed) {
    Swal.fire({
      title: 'Eliminando respuestas...',
      text: 'Por favor espera',
      allowOutsideClick: false,
      allowEscapeKey: false,
      showConfirmButton: false,
      willOpen: () => {
        Swal.showLoading();
      }
    });
    
    try {
      const response = await fetch('resetear_respuestas_modulo.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id_usuario=${idUsuario}&id_modulo=${idModulo}`
      });
      
      const data = await response.json();
      
      if (data.success) {
        Swal.fire({
          title: 'Respuestas eliminadas',
          text: `Se eliminaron ${data.total_eliminadas} respuesta(s) del módulo.`,
          icon: 'success',
          confirmButtonText: 'Aceptar'
        }).then(() => {
          bootstrap.Modal.getInstance(document.getElementById('modalResetear')).hide();
          location.reload();
        });
      } else {
        Swal.fire({
          title: 'Error',
          text: data.error || 'No se pudieron eliminar las respuestas',
          icon: 'error'
        });
      }
    } catch (error) {
      console.error('Error:', error);
      Swal.fire({
        title: 'Error',
        text: 'Ocurrió un error al eliminar las respuestas',
        icon: 'error'
      });
    }
  }
});
</script>
</body>
</html>