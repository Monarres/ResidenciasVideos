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

    if (!empty($nombre) && !empty($email) && !empty($_POST['password'])) {
        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, contrasena, rol, id_carpeta) 
                               VALUES (:nombre, :email, :contrasena, :rol, :id_carpeta)");
        try {
            $stmt->execute([
                'nombre' => $nombre,
                'email' => $email,
                'contrasena' => $contrasena,
                'rol' => $rol,
                'id_carpeta' => $id_carpeta
            ]);
            $mensaje = "Usuario agregado correctamente";
            $tipo_mensaje = "success";
            header("Location: usuarios.php?msg=success");
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $mensaje = "El correo ya está registrado. Intenta con otro.";
                $tipo_mensaje = "danger";
            } else {
                $mensaje = "Error al insertar usuario: " . $e->getMessage();
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

    if (!empty($_POST['password'])) {
        $contrasena = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql = "UPDATE usuarios SET nombre = :nombre, email = :email, contrasena = :contrasena, rol = :rol, id_carpeta = :id_carpeta WHERE id_usuario = :id";
        $params = ['nombre' => $nombre, 'email' => $email, 'contrasena' => $contrasena, 'rol' => $rol, 'id_carpeta' => $id_carpeta, 'id' => $id];
    } else {
        $sql = "UPDATE usuarios SET nombre = :nombre, email = :email, rol = :rol, id_carpeta = :id_carpeta WHERE id_usuario = :id";
        $params = ['nombre' => $nombre, 'email' => $email, 'rol' => $rol, 'id_carpeta' => $id_carpeta, 'id' => $id];
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        header("Location: usuarios.php?msg=updated");
        exit;
    } catch (PDOException $e) {
        $mensaje = "Error al actualizar usuario: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// 🔹 Eliminar usuario
if (isset($_GET['eliminar'])) {
    $id = (int) $_GET['eliminar'];
    
    try {
        // Iniciar transacción
        $pdo->beginTransaction();
        
        // Primero verificar si el usuario tiene respuestas
        $stmt = $pdo->prepare("SELECT 
                              (SELECT COUNT(*) FROM respuestas_usuario WHERE id_usuario = ?) as total_incisos,
                              (SELECT COUNT(*) FROM respuestas_archivo WHERE id_usuario = ?) as total_archivos");
        $stmt->execute([$id, $id]);
        $totales = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $tiene_respuestas = ($totales['total_incisos'] > 0 || $totales['total_archivos'] > 0);
        
        // Si tiene respuestas, eliminarlas primero
        if ($tiene_respuestas) {
            // Obtener rutas de archivos para eliminarlos físicamente
            $stmt = $pdo->prepare("SELECT ruta_archivo FROM respuestas_archivo WHERE id_usuario = ?");
            $stmt->execute([$id]);
            $archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Eliminar archivos físicos
            foreach ($archivos as $archivo) {
                if (file_exists($archivo['ruta_archivo'])) {
                    unlink($archivo['ruta_archivo']);
                }
            }
            
            // Eliminar respuestas de archivo
            $stmt = $pdo->prepare("DELETE FROM respuestas_archivo WHERE id_usuario = ?");
            $stmt->execute([$id]);
            
            // Eliminar respuestas de cuestionarios
            $stmt = $pdo->prepare("DELETE FROM respuestas_usuario WHERE id_usuario = ?");
            $stmt->execute([$id]);
        }
        
        // Finalmente eliminar el usuario
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$id]);
        
        // Confirmar transacción
        $pdo->commit();
        
        header("Location: usuarios.php?msg=deleted");
        exit;
        
    } catch (PDOException $e) {
        // Revertir transacción en caso de error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        $mensaje = "Error al eliminar usuario: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// Mensajes desde redirect
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'success':
            $mensaje = "✅ Usuario agregado correctamente";
            $tipo_mensaje = "success";
            break;
        case 'updated':
            $mensaje = "✅ Usuario actualizado correctamente";
            $tipo_mensaje = "success";
            break;
        case 'deleted':
            $mensaje = "✅ Usuario eliminado correctamente";
            $tipo_mensaje = "success";
            break;
    }
}

// 🔹 Listar usuarios
$stmt = $pdo->query("SELECT u.*, c.nombre as area_nombre 
                     FROM usuarios u 
                     LEFT JOIN carpetas c ON u.id_carpeta = c.id_carpeta 
                     ORDER BY u.id_usuario DESC");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener todas las áreas para el selector
$stmt_areas = $pdo->query("SELECT id_carpeta, nombre FROM carpetas WHERE id_padre IS NULL ORDER BY nombre ASC");
$areas = $stmt_areas->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
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
  font-size: 1.8rem !important;
  margin-bottom: 20px !important;
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

.no-area {
  color: #999;
  font-style: italic;
  font-size: 0.9rem;
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
  
  .btn-logout {
    padding: 6px 15px;
    font-size: 0.9rem;
  }
  
  .table {
    font-size: 0.85rem;
  }
}
  </style>
</head>
<body>

<div class="top-header">
  <div class="container-fluid">
    <h2>👥 Gestión de Usuarios</h2>
    <div class="user-info">
      <a href="dashboard.php" class="btn-logout">⬅ Volver</a>
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
    <h5 class="mb-3" style="color: #9b7cb8; font-weight: 600;">➕ Agregar Nuevo Usuario</h5>
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
      
      <!-- Panel de selección de área (solo para usuarios) -->
      <div id="areaSelectContainer" class="select-area-container">
        <label class="form-label fw-bold" style="color: #9b7cb8;">
          📁 Seleccionar Área de Acceso
        </label>
        <select name="id_carpeta" id="areaSelect" class="form-select">
          <option value="">-- Seleccionar área --</option>
          <?php foreach($areas as $area): ?>
            <option value="<?= $area['id_carpeta'] ?>"><?= htmlspecialchars($area['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
        <small class="text-muted d-block mt-2">
          ℹ️ El usuario solo tendrá acceso al área seleccionada
        </small>
      </div>
    </form>
  </div>

  <!-- Tabla de usuarios -->
  <div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 style="color: #9b7cb8; font-weight: 600; margin: 0;">📋 Lista de Usuarios</h5>
      <span class="badge"><?= count($usuarios) ?> usuarios</span>
    </div>
    
    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>#</th>
            <th>Nombre</th>
            <th>Correo</th>
            <th>Rol</th>
            <th>Área</th>
            <th style="text-align: center;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php $i=1; foreach ($usuarios as $u): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= htmlspecialchars($u['nombre']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td>
              <span class="badge bg-secondary" style="font-size: 0.85rem;">
                <?php 
                  if($u['rol'] === 'admin') echo '👑 Administrador';
                  elseif($u['rol'] === 'franquiciatario') echo '🏢 Franquiciatario';
                  else echo '👤 Usuario';
                ?>
              </span>
            </td>
            <td>
              <?php if($u['area_nombre']): ?>
                <span class="area-badge">📁 <?= htmlspecialchars($u['area_nombre']) ?></span>
              <?php else: ?>
                <span class="no-area">— Acceso total —</span>
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
                      data-carpeta="<?= $u['id_carpeta'] ?? '' ?>">
                ✏️ Editar
              </button>
              
              <button class="btn btn-danger btn-sm btn-delete me-1"
                      data-id="<?= $u['id_usuario'] ?>"
                      data-nombre="<?= htmlspecialchars($u['nombre']) ?>">
                🗑️ Eliminar
              </button>
              
              <?php if($u['rol'] === 'usuario'): ?>
              <button class="btn btn-reset btn-sm"
                      data-id="<?= $u['id_usuario'] ?>"
                      data-nombre="<?= htmlspecialchars($u['nombre']) ?>">
                🔄 Resetear
              </button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Editar -->
<div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content" id="formEditarUsuario">
      <input type="hidden" name="accion" value="editar">
      <input type="hidden" name="id_usuario" id="edit-id">
      <div class="modal-header">
        <h5 class="modal-title">✏️ Editar Usuario</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-bold">Nombre</label>
          <input type="text" class="form-control" name="nombre" id="edit-nombre" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">Correo</label>
          <input type="email" class="form-control" name="email" id="edit-email" required>
        </div>
        <div class="mb-3">
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
        
        <!-- Panel de selección de área en el modal -->
        <div id="editAreaSelectContainer" class="select-area-container">
          <label class="form-label fw-bold" style="color: #9b7cb8;">
            📁 Seleccionar Área de Acceso
          </label>
          <select name="id_carpeta" id="edit-area" class="form-select">
            <option value="">-- Seleccionar área --</option>
            <?php foreach($areas as $area): ?>
              <option value="<?= $area['id_carpeta'] ?>"><?= htmlspecialchars($area['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
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
        <h5 class="modal-title">🔄 Resetear Respuestas de Usuario</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="reset-id-usuario">
        
        <div class="alert alert-warning" style="border-radius: 15px;">
          <strong>⚠️ Advertencia:</strong><br>
          Se eliminarán todas las respuestas (cuestionarios y archivos) del módulo seleccionado.
          El usuario deberá volver a contestar ese módulo completo.
        </div>
        
        <div class="mb-3">
          <label class="form-label fw-bold">Usuario:</label>
          <p id="reset-nombre-usuario" class="text-muted"></p>
        </div>
        
        <div class="mb-3">
          <label class="form-label fw-bold" style="color: #9b7cb8;">
            📁 Seleccionar Módulo a Resetear
          </label>
          <select id="reset-modulo" class="form-select" required>
            <option value="">-- Seleccionar módulo --</option>
          </select>
          <small class="text-muted d-block mt-2">
            ℹ️ Solo se mostrarán los módulos donde el usuario tiene respuestas registradas
          </small>
        </div>
        
        <div id="reset-detalles" style="display: none; background: #f8f9fa; padding: 15px; border-radius: 10px; margin-top: 15px;">
          <strong>📊 Respuestas a eliminar:</strong>
          <ul style="margin-top: 10px; margin-bottom: 0;">
            <li>📝 <span id="reset-total-incisos">0</span> respuesta(s) de cuestionarios</li>
            <li>📎 <span id="reset-total-archivos">0</span> archivo(s) subido(s)</li>
          </ul>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="btnConfirmarReset" disabled>
          🗑️ Eliminar Respuestas
        </button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Mostrar/ocultar selector de área según el rol (formulario agregar)
const rolSelect = document.getElementById('rolSelect');
const areaSelectContainer = document.getElementById('areaSelectContainer');
const areaSelect = document.getElementById('areaSelect');

rolSelect.addEventListener('change', function() {
  if (this.value === 'usuario') {
    areaSelectContainer.classList.add('show');
    areaSelect.required = true;
  } else {
    areaSelectContainer.classList.remove('show');
    areaSelect.required = false;
    areaSelect.value = '';
  }
});

// Mostrar/ocultar selector de área según el rol (modal editar)
const editRolSelect = document.getElementById('edit-rol');
const editAreaSelectContainer = document.getElementById('editAreaSelectContainer');
const editAreaSelect = document.getElementById('edit-area');

editRolSelect.addEventListener('change', function() {
  if (this.value === 'usuario') {
    editAreaSelectContainer.classList.add('show');
    editAreaSelect.required = true;
  } else {
    editAreaSelectContainer.classList.remove('show');
    editAreaSelect.required = false;
    editAreaSelect.value = '';
  }
});

// Pasar datos al modal de edición
const modalEditar = document.getElementById('modalEditar');
modalEditar.addEventListener('show.bs.modal', event => {
  const button = event.relatedTarget;
  document.getElementById('edit-id').value = button.getAttribute('data-id');
  document.getElementById('edit-nombre').value = button.getAttribute('data-nombre');
  document.getElementById('edit-email').value = button.getAttribute('data-email');
  document.getElementById('edit-rol').value = button.getAttribute('data-rol');
  
  const carpetaId = button.getAttribute('data-carpeta');
  document.getElementById('edit-area').value = carpetaId || '';
  
  // Mostrar u ocultar el selector de área según el rol
  if (button.getAttribute('data-rol') === 'usuario') {
    editAreaSelectContainer.classList.add('show');
    editAreaSelect.required = true;
  } else {
    editAreaSelectContainer.classList.remove('show');
    editAreaSelect.required = false;
  }
});

// Eliminar usuario con SweetAlert2 - Versión mejorada con advertencia de respuestas
document.querySelectorAll('.btn-delete').forEach(btn => {
  btn.addEventListener('click', async (e) => {
    e.preventDefault();
    const id = btn.dataset.id;
    const nombre = btn.dataset.nombre;
    
    // Primero verificar si el usuario tiene respuestas
    try {
      const response = await fetch(`verificar_respuestas.php?id_usuario=${id}`);
      const data = await response.json();
      
      let mensaje = `Se eliminará al usuario: <strong>${nombre}</strong>`;
      let icon = 'warning';
      
      if (data.tiene_respuestas) {
        mensaje += `<br><br><div style="background: #fff3cd; padding: 15px; border-radius: 10px; margin-top: 15px;">
          <strong>⚠️ ATENCIÓN:</strong><br>
          Este usuario tiene respuestas guardadas:<br>
          <ul style="text-align: left; margin-top: 10px;">
            <li>📝 ${data.total_incisos} respuesta(s) de cuestionarios</li>
            <li>📎 ${data.total_archivos} archivo(s) subido(s)</li>
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
        // Mostrar loading
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
      // Si falla la verificación, mostrar mensaje simple
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

// ========================================
// FUNCIONALIDAD DE RESETEAR RESPUESTAS
// ========================================

// Abrir modal de resetear respuestas
document.querySelectorAll('.btn-reset').forEach(btn => {
  btn.addEventListener('click', async () => {
    const idUsuario = btn.dataset.id;
    const nombreUsuario = btn.dataset.nombre;
    
    document.getElementById('reset-id-usuario').value = idUsuario;
    document.getElementById('reset-nombre-usuario').textContent = nombreUsuario;
    document.getElementById('reset-detalles').style.display = 'none';
    document.getElementById('btnConfirmarReset').disabled = true;
    
    // Obtener módulos con respuestas del usuario
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
        
        // Mostrar modal
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
    // Mostrar loading
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
          title: '✅ Respuestas eliminadas',
          text: `Se eliminaron ${data.total_eliminadas} respuesta(s) del módulo.`,
          icon: 'success',
          confirmButtonText: 'Aceptar'
        }).then(() => {
          // Cerrar modal y recargar
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