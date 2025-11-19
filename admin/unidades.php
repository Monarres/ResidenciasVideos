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

// 🔹 Insertar nueva unidad
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['accion']) && $_POST['accion'] === "agregar") {
    $nombre = trim($_POST['nombre']);
    $direccion = trim($_POST['direccion']);
    $franquiciatarios = isset($_POST['franquiciatarios']) ? $_POST['franquiciatarios'] : [];

    if (!empty($nombre) && !empty($direccion)) {
        try {
            $pdo->beginTransaction();
            
            // Insertar unidad
            $stmt = $pdo->prepare("INSERT INTO unidades (nombre, direccion) VALUES (:nombre, :direccion)");
            $stmt->execute(['nombre' => $nombre, 'direccion' => $direccion]);
            $id_unidad = $pdo->lastInsertId();
            
            // Insertar relación con franquiciatarios
            if (!empty($franquiciatarios)) {
                $stmt = $pdo->prepare("INSERT INTO unidad_franquiciatarios (id_unidad, id_usuario) VALUES (?, ?)");
                foreach ($franquiciatarios as $id_franq) {
                    $stmt->execute([$id_unidad, $id_franq]);
                }
            }
            
            $pdo->commit();
            header("Location: unidades.php?msg=success");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $mensaje = "Error al insertar unidad: " . $e->getMessage();
            $tipo_mensaje = "danger";
        }
    } else {
        $mensaje = "El nombre y la dirección son obligatorios.";
        $tipo_mensaje = "danger";
    }
}

// 🔹 Editar unidad
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['accion']) && $_POST['accion'] === "editar") {
    $id = (int) $_POST['id_unidad'];
    $nombre = trim($_POST['nombre']);
    $direccion = trim($_POST['direccion']);
    $franquiciatarios = isset($_POST['franquiciatarios']) ? $_POST['franquiciatarios'] : [];

    try {
        $pdo->beginTransaction();
        
        // Actualizar unidad
        $stmt = $pdo->prepare("UPDATE unidades SET nombre = :nombre, direccion = :direccion WHERE id_unidad = :id");
        $stmt->execute(['nombre' => $nombre, 'direccion' => $direccion, 'id' => $id]);
        
        // Eliminar relaciones anteriores
        $stmt = $pdo->prepare("DELETE FROM unidad_franquiciatarios WHERE id_unidad = ?");
        $stmt->execute([$id]);
        
        // Insertar nuevas relaciones
        if (!empty($franquiciatarios)) {
            $stmt = $pdo->prepare("INSERT INTO unidad_franquiciatarios (id_unidad, id_usuario) VALUES (?, ?)");
            foreach ($franquiciatarios as $id_franq) {
                $stmt->execute([$id, $id_franq]);
            }
        }
        
        $pdo->commit();
        header("Location: unidades.php?msg=updated");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $mensaje = "Error al actualizar unidad: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// 🔹 Eliminar unidad
if (isset($_GET['eliminar'])) {
    $id = (int) $_GET['eliminar'];
    
    try {
        $pdo->beginTransaction();
        
        // Eliminar relaciones con franquiciatarios
        $stmt = $pdo->prepare("DELETE FROM unidad_franquiciatarios WHERE id_unidad = ?");
        $stmt->execute([$id]);
        
        // Actualizar usuarios que pertenecían a esta unidad
        $stmt = $pdo->prepare("UPDATE usuarios SET id_unidad = NULL WHERE id_unidad = ?");
        $stmt->execute([$id]);
        
        // Eliminar la unidad
        $stmt = $pdo->prepare("DELETE FROM unidades WHERE id_unidad = ?");
        $stmt->execute([$id]);
        
        $pdo->commit();
        header("Location: unidades.php?msg=deleted");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $mensaje = "Error al eliminar unidad: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// Mensajes desde redirect
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'success':
            $mensaje = "✅ Unidad agregada correctamente";
            $tipo_mensaje = "success";
            break;
        case 'updated':
            $mensaje = "✅ Unidad actualizada correctamente";
            $tipo_mensaje = "success";
            break;
        case 'deleted':
            $mensaje = "✅ Unidad eliminada correctamente";
            $tipo_mensaje = "success";
            break;
    }
}

// 🔹 Listar unidades con sus franquiciatarios
$stmt = $pdo->query("SELECT u.*, 
                     GROUP_CONCAT(us.nombre SEPARATOR ', ') as franquiciatarios,
                     (SELECT COUNT(*) FROM usuarios WHERE id_unidad = u.id_unidad AND rol = 'usuario') as total_usuarios
                     FROM unidades u
                     LEFT JOIN unidad_franquiciatarios uf ON u.id_unidad = uf.id_unidad
                     LEFT JOIN usuarios us ON uf.id_usuario = us.id_usuario
                     GROUP BY u.id_unidad
                     ORDER BY u.id_unidad DESC");
$unidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener todos los franquiciatarios para el selector
$stmt_franq = $pdo->query("SELECT id_usuario, nombre, email FROM usuarios WHERE rol = 'franquiciatario' ORDER BY nombre ASC");
$franquiciatarios = $stmt_franq->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestión de Unidades</title>
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

.btn-info {
  background: linear-gradient(135deg, #17a2b8, #138496);
  border: none;
  color: white;
  font-weight: 500;
  border-radius: 20px;
  padding: 6px 15px;
  transition: 0.3s;
}

.btn-info:hover {
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

.franquiciatarios-list {
  background: linear-gradient(135deg, rgba(245, 163, 199, 0.1), rgba(155, 124, 184, 0.1));
  padding: 10px 15px;
  border-radius: 10px;
  margin-top: 10px;
}

.franq-checkbox {
  margin: 8px 0;
  padding: 8px 12px;
  background: white;
  border-radius: 8px;
  border: 2px solid #e0e0e0;
  transition: 0.3s;
}

.franq-checkbox:hover {
  border-color: #9b7cb8;
  background: rgba(155, 124, 184, 0.05);
}

.franq-checkbox input[type="checkbox"] {
  margin-right: 8px;
  cursor: pointer;
}

.franq-badge {
  background: linear-gradient(135deg, rgba(245, 163, 199, 0.3), rgba(155, 124, 184, 0.3));
  color: #9b7cb8;
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 0.85rem;
  font-weight: 500;
  display: inline-block;
  margin: 2px;
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
}
  </style>
</head>
<body>

<div class="top-header">
  <div class="container-fluid">
    <h2>🏢 Gestión de Unidades</h2>
    <div class="user-info">
      <a href="usuarios.php" class="btn-logout">👥 Usuarios</a>
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
    <h5 class="mb-3" style="color: #9b7cb8; font-weight: 600;">➕ Agregar Nueva Unidad</h5>
    <form method="POST">
      <input type="hidden" name="accion" value="agregar">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label fw-bold">Nombre de la Unidad</label>
          <input type="text" name="nombre" class="form-control" placeholder="Ej: Unidad Centro" required>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-bold">Dirección</label>
          <input type="text" name="direccion" class="form-control" placeholder="Calle, número, colonia, ciudad" required>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button class="btn btn-primary w-100" type="submit">Agregar</button>
        </div>
      </div>
      
      <div class="mt-3">
        <label class="form-label fw-bold" style="color: #9b7cb8;">
          👔 Franquiciatario/s Asignado/s
        </label>
        <div class="franquiciatarios-list">
          <?php if (empty($franquiciatarios)): ?>
            <p class="text-muted mb-0">No hay franquiciatarios disponibles. <a href="usuarios.php">Crear franquiciatario</a></p>
          <?php else: ?>
            <div class="row">
              <?php foreach($franquiciatarios as $franq): ?>
                <div class="col-md-6">
                  <div class="franq-checkbox">
                    <label style="cursor: pointer; margin: 0; width: 100%;">
                      <input type="checkbox" name="franquiciatarios[]" value="<?= $franq['id_usuario'] ?>">
                      <strong><?= htmlspecialchars($franq['nombre']) ?></strong>
                      <br><small class="text-muted"><?= htmlspecialchars($franq['email']) ?></small>
                    </label>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </form>
  </div>

  <!-- Tabla de unidades -->
  <div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 style="color: #9b7cb8; font-weight: 600; margin: 0;">📋 Lista de Unidades</h5>
      <span class="badge"><?= count($unidades) ?> unidades</span>
    </div>
    
    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>#</th>
            <th>Nombre</th>
            <th>Dirección</th>
            <th>Franquiciatarios</th>
            <th>Usuarios</th>
            <th style="text-align: center;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($unidades)): ?>
            <tr>
              <td colspan="6" class="text-center text-muted">No hay unidades registradas</td>
            </tr>
          <?php else: ?>
            <?php $i=1; foreach ($unidades as $u): ?>
            <tr>
              <td><?= $i++ ?></td>
              <td><strong><?= htmlspecialchars($u['nombre']) ?></strong></td>
              <td><?= htmlspecialchars($u['direccion']) ?></td>
              <td>
                <?php if($u['franquiciatarios']): ?>
                  <?php 
                    $franqs = explode(', ', $u['franquiciatarios']);
                    foreach($franqs as $franq): 
                  ?>
                    <span class="franq-badge">👔 <?= htmlspecialchars($franq) ?></span>
                  <?php endforeach; ?>
                <?php else: ?>
                  <span class="text-muted">Sin asignar</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge bg-info"><?= $u['total_usuarios'] ?> usuarios</span>
              </td>
              <td style="text-align: center;">
                <button class="btn btn-info btn-sm me-1"
                        onclick="verUsuarios(<?= $u['id_unidad'] ?>, '<?= htmlspecialchars($u['nombre']) ?>')">
                  👥 Ver Usuarios
                </button>
                <button class="btn btn-warning btn-sm me-1"
                        data-bs-toggle="modal"
                        data-bs-target="#modalEditar"
                        onclick="cargarDatosEdicion(<?= $u['id_unidad'] ?>, '<?= htmlspecialchars($u['nombre']) ?>', '<?= htmlspecialchars($u['direccion']) ?>')">
                  ✏️ Editar
                </button>
                <button class="btn btn-danger btn-sm btn-delete"
                        data-id="<?= $u['id_unidad'] ?>"
                        data-nombre="<?= htmlspecialchars($u['nombre']) ?>">
                  🗑️ Eliminar
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

<!-- Modal Editar -->
<div class="modal fade" id="modalEditar" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="POST" class="modal-content">
      <input type="hidden" name="accion" value="editar">
      <input type="hidden" name="id_unidad" id="edit-id">
      <div class="modal-header">
        <h5 class="modal-title">✏️ Editar Unidad</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-bold">Nombre de la Unidad</label>
          <input type="text" class="form-control" name="nombre" id="edit-nombre" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">Dirección</label>
          <input type="text" class="form-control" name="direccion" id="edit-direccion" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold" style="color: #9b7cb8;">
            👔 Franquiciatarios Asignados
          </label>
          <div class="franquiciatarios-list" id="edit-franquiciatarios-list">
            <?php if (!empty($franquiciatarios)): ?>
              <div class="row">
                <?php foreach($franquiciatarios as $franq): ?>
                  <div class="col-md-6">
                    <div class="franq-checkbox">
                      <label style="cursor: pointer; margin: 0; width: 100%;">
                        <input type="checkbox" name="franquiciatarios[]" value="<?= $franq['id_usuario'] ?>" class="edit-franq-check">
                        <strong><?= htmlspecialchars($franq['nombre']) ?></strong>
                        <br><small class="text-muted"><?= htmlspecialchars($franq['email']) ?></small>
                      </label>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
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

<!-- Modal Ver Usuarios -->
<div class="modal fade" id="modalUsuarios" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="tituloModalUsuarios">👥 Usuarios de la Unidad</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="contenidoUsuarios">
        <div class="text-center">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando...</span>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Cargar datos de edición
async function cargarDatosEdicion(id, nombre, direccion) {
  document.getElementById('edit-id').value = id;
  document.getElementById('edit-nombre').value = nombre;
  document.getElementById('edit-direccion').value = direccion;
  
  // Desmarcar todos los checkboxes
  document.querySelectorAll('.edit-franq-check').forEach(check => check.checked = false);
  
  // Obtener franquiciatarios de la unidad
  try {
    const response = await fetch(`obtener_franquiciatarios_unidad.php?id_unidad=${id}`);
    const data = await response.json();
    
    if (data.franquiciatarios) {
      data.franquiciatarios.forEach(franqId => {
        const checkbox = document.querySelector(`.edit-franq-check[value="${franqId}"]`);
        if (checkbox) checkbox.checked = true;
      });
    }
  } catch (error) {
    console.error('Error al cargar franquiciatarios:', error);
  }
}

// Ver usuarios de la unidad
async function verUsuarios(idUnidad, nombreUnidad) {
  document.getElementById('tituloModalUsuarios').textContent = `👥 Usuarios de: ${nombreUnidad}`;
  document.getElementById('contenidoUsuarios').innerHTML = `
    <div class="text-center">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Cargando...</span>
      </div>
    </div>
  `;
  
  const modal = new bootstrap.Modal(document.getElementById('modalUsuarios'));
  modal.show();
  
  try {
    const response = await fetch(`obtener_usuarios_unidad.php?id_unidad=${idUnidad}`);
    const data = await response.json();
    
    let html = '';
    if (data.usuarios && data.usuarios.length > 0) {
      html = '<div class="table-responsive"><table class="table table-hover"><thead class="table-light"><tr><th>Nombre</th><th>Email</th><th>Área</th></tr></thead><tbody>';
      data.usuarios.forEach(usuario => {
        html += `
          <tr>
            <td><strong>${usuario.nombre}</strong></td>
            <td>${usuario.email}</td>
            <td>${usuario.area_nombre ? '<span class="franq-badge">📁 ' + usuario.area_nombre + '</span>' : '<span class="text-muted">Sin área</span>'}</td>
          </tr>
        `;
      });
      html += '</tbody></table></div>';
    } else {
      html = '<div class="alert alert-info">Esta unidad no tiene usuarios asignados todavía.</div>';
    }
    
    document.getElementById('contenidoUsuarios').innerHTML = html;
  } catch (error) {
    document.getElementById('contenidoUsuarios').innerHTML = '<div class="alert alert-danger">Error al cargar usuarios</div>';
    console.error('Error:', error);
  }
}

// Eliminar unidad
document.querySelectorAll('.btn-delete').forEach(btn => {
  btn.addEventListener('click', async (e) => {
    e.preventDefault();
    const id = btn.dataset.id;
    const nombre = btn.dataset.nombre;
    
    const result = await Swal.fire({
      title: '¿Eliminar unidad?',
      html: `Se eliminará la unidad: <strong>${nombre}</strong><br><br><small>Los usuarios de esta unidad no serán eliminados, solo se desvinculará la unidad.</small>`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      confirmButtonColor: '#dc3545',
      cancelButtonText: 'Cancelar',
      reverseButtons: true
    });

    if (result.isConfirmed) {
      window.location.href = `unidades.php?eliminar=${id}`;
    }
  });
});
</script>
</body>
</html>