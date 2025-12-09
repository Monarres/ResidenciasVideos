<?php
session_start();
require_once("../conexion.php");

if(!isset($_SESSION['id_usuario']) || $_SESSION['rol']!=='admin'){
  header("Location: ../index.php");
  exit;
}

$id_area = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id_area <= 0){
  header("Location: dashboard.php");
  exit;
}

try {
  // Obtener info del área
  $stmt = $pdo->prepare("SELECT * FROM carpetas WHERE id_carpeta = ?");
  $stmt->execute([$id_area]);
  $area = $stmt->fetch(PDO::FETCH_ASSOC);
  if(!$area) {
    header("Location: dashboard.php");
    exit;
  }

  // Obtener módulos
  $stmt = $pdo->prepare("SELECT * FROM carpetas WHERE id_padre = ? ORDER BY nombre ASC");
  $stmt->execute([$id_area]);
  $modulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  die("Error al cargar datos: " . htmlspecialchars($e->getMessage()));
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Área - <?= htmlspecialchars($area['nombre']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v6.5.1/css/all.css">

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { background:#f0d5e8; font-family:'Poppins',sans-serif; min-height:100vh; padding-top:100px; }
.top-header { position:fixed; top:0; left:0; right:0; background:linear-gradient(135deg,#b893cc,#f5a3c7); box-shadow:0 2px 10px rgba(0,0,0,0.15); z-index:1000; padding:20px 0; margin:15px; border-radius:20px; }
.top-header .container-fluid { display:flex; justify-content:center; align-items:center; padding:0 30px; }
.top-header h2 { color:white; font-weight:600; margin:0; font-size:1.5rem; }
.top-header h2 {
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
}

.header-right {
  margin-left: auto;
}
.header-right {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-left: auto;
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
  color: #dc3545;
  cursor: pointer;
  background: linear-gradient(135deg, rgba(220, 53, 69, 0.05), rgba(245, 163, 199, 0.1));
}

.user-dropdown-item:hover {
  background: linear-gradient(135deg, rgba(220, 53, 69, 0.15), rgba(245, 163, 199, 0.2));
  transform: translateX(5px);
}

.btn-logout { background:white; border:none; color:#9b7cb8; font-weight:500; border-radius:25px; padding:8px 20px; transition:0.3s; text-decoration:none; display:inline-block; }
.btn-logout:hover { background:#f8f9fa; transform:translateY(-2px); box-shadow:0 5px 15px rgba(0,0,0,0.2); color:#9b7cb8; }

.container { max-width:1200px; padding:20px 15px; }
.card { border:none; border-radius:15px; box-shadow:0 2px 10px rgba(0,0,0,0.1); background:white; }
.form-control { border-radius:25px; padding:10px 20px; border:1px solid #ddd; }
.btn-primary {
  background: linear-gradient(135deg,#f5a3c7,#9b7cb8);
  border:none; color:white; font-weight:500; border-radius:25px; padding:10px 25px; transition:0.3s; white-space:nowrap;
}
.btn-primary:hover { background: linear-gradient(135deg,#9b7cb8,#f5a3c7); transform:translateY(-2px); box-shadow:0 5px 15px rgba(155,124,184,0.3); }

.folder-icon { font-size:4rem; margin:20px 0 10px 0; }
.area-card .card { cursor:pointer; transition:all 0.3s ease; height:100%; position:relative; }
.area-card .card:hover { transform:translateY(-5px); box-shadow:0 5px 20px rgba(155,124,184,0.3); }
.area-card h5 { color:#9b7cb8; font-weight:600; margin-bottom:20px; }
.dropdown { position:absolute; top:10px; right:10px; }
.dropdown-toggle { background:transparent !important; border:none !important; color:#9b7cb8 !important; font-size:1.5rem; font-weight:bold; width:30px; height:30px; padding:0 !important; line-height:1; box-shadow:none !important; }
.dropdown-toggle::after { display:none !important; }
.dropdown-menu { border-radius:15px; border:none; box-shadow:0 5px 20px rgba(155,124,184,0.3); min-width:200px; padding:10px; background:white !important; z-index:10000 !important; position:absolute !important; inset:0px auto auto 0px !important; transform:translate(-170px, 30px) !important; }.dropdown-item { padding:12px 20px; transition:0.3s; border-radius:10px; margin-bottom:5px; font-weight:500; display:flex; align-items:center; gap:10px; }
.dropdown-item:last-child { margin-bottom:0; }
.dropdown-item:hover { background:linear-gradient(135deg,rgba(245,163,199,0.2),rgba(155,124,184,0.2)); transform:translateX(5px); color:#9b7cb8; }
.dropdown-item.text-danger:hover { background:linear-gradient(135deg,rgba(220,53,69,0.1),rgba(245,163,199,0.2)); color:#dc3545; }
.dropdown-divider { border-top:2px solid #f5c6d9; margin:10px 0; }

#modulosGrid .col-md-3 { display:flex; }
.folder-card { flex:1; }
.modal-content { border-radius:15px; border:none; }
.modal-content h5 { color:#9b7cb8; font-weight:600; margin-bottom:12px; }
.btn-secondary { border-radius:25px; padding:8px 20px; }

/* Estilos personalizados para SweetAlert2 */
.swal2-popup { border-radius:20px !important; font-family:'Poppins',sans-serif !important; padding:30px !important; background:#ffffff !important; box-shadow:0 10px 40px rgba(0,0,0,0.2) !important; }
.swal2-title { color:#9b7cb8 !important; font-weight:700 !important; font-size:1.8rem !important; margin-bottom:20px !important; }
.swal2-html-container { color:#666 !important; font-size:1rem !important; font-weight:500 !important; }
.swal2-icon.swal2-warning { border-color:#f5a3c7 !important; color:#f5a3c7 !important; }
.swal2-icon.swal2-success { border-color:#9b7cb8 !important; }
.swal2-icon.swal2-success [class^='swal2-success-line'] { background-color:#9b7cb8 !important; }
.swal2-icon.swal2-success .swal2-success-ring { border-color:rgba(155,124,184,0.3) !important; }
.swal2-icon.swal2-error { border-color:#f5a3c7 !important; color:#f5a3c7 !important; }
.swal2-actions { gap:10px !important; margin-top:25px !important; }
.swal2-confirm { background:linear-gradient(135deg,#f5a3c7,#9b7cb8) !important; border:none !important; border-radius:25px !important; padding:12px 35px !important; font-weight:600 !important; font-size:1rem !important; box-shadow:0 4px 15px rgba(155,124,184,0.4) !important; transition:all 0.3s ease !important; }
.swal2-confirm:hover { background:linear-gradient(135deg,#9b7cb8,#f5a3c7) !important; transform:translateY(-2px) !important; box-shadow:0 6px 20px rgba(155,124,184,0.5) !important; }
.swal2-cancel { background:white !important; border:2px solid #9b7cb8 !important; border-radius:25px !important; padding:12px 35px !important; font-weight:600 !important; font-size:1rem !important; color:#9b7cb8 !important; transition:all 0.3s ease !important; }
.swal2-cancel:hover { background:#f8f9fa !important; transform:translateY(-2px) !important; box-shadow:0 4px 15px rgba(0,0,0,0.1) !important; }
.swal2-input { border-radius:25px !important; border:2px solid #ddd !important; padding:12px 20px !important; font-family:'Poppins',sans-serif !important; font-size:1rem !important; transition:all 0.3s ease !important; }
.swal2-input:focus { border-color:#9b7cb8 !important; box-shadow:0 0 0 4px rgba(155,124,184,0.15) !important; outline:none !important; }
.swal2-validation-message { background:#f5c6d9 !important; color:#9b7cb8 !important; border-radius:15px !important; font-weight:500 !important; }

@media (max-width:768px){
  body { padding-top:90px; }
  .top-header { margin:10px; border-radius:15px; }
  .top-header .container-fluid { padding:0 15px; }
  .top-header h2 { font-size:1.2rem; }
  .header-right { gap: 10px; }
  .user-toggle { padding: 6px 15px; font-size: 0.9rem; }
  .btn-logout { padding:6px 15px; font-size:0.9rem; }
}
.icono-libro:hover {
  animation: fa-beat 1s infinite;
}
</style>
</head>
<body>

<div class="top-header">
  <div class="container-fluid">
    <h2> <i class="fa-solid fa-location-dot fa-beat" style="color: #ffffffff;"></i> Área: <?= htmlspecialchars($area['nombre']) ?></h2>
    
    <div class="header-right">
      <!-- Sección de Usuario con Cerrar Sesión -->
      <div class="user-section">
        <button class="user-toggle" id="userToggle">
          <span><i class="fa-solid fa-user" style="color: #B197FC;"></i> </span> <?=htmlspecialchars($_SESSION['nombre'])?> <span style="font-size: 0.8em;"><i class="fa-solid fa-caret-down" style="color: #B197FC;"></i></span>
        </button>
        <div class="user-dropdown" id="userDropdown">
          <a href="../logout.php" class="user-dropdown-item">
            <span><i class="fa-solid fa-door-open" style="color: #ef061d;"></i> </span> Cerrar sesión
          </a>
        </div>
      </div>
      
      <a href="dashboard.php" class="btn-logout"><i class="fa-solid fa-angle-left" style="color: #B197FC;"></i> Volver</a>
    </div>
  </div>
</div>

<div class="container py-4">

  <div class="card p-4 mb-4">
    <form id="formCrearModulo" class="d-flex gap-2">
      <input name="nombre" class="form-control" placeholder="Nuevo módulo" required>
      <button id="btnCrearModulo" class="btn btn-primary" type="submit">Crear módulo</button>
    </form>
    <div id="moduloMsg"></div>
  </div>

  <div class="row" id="modulosGrid">
    <?php if(empty($modulos)): ?>
      <div class="col-12"><div class="card p-3">No hay módulos en esta área.</div></div>
    <?php endif; ?>

    <?php foreach($modulos as $m): ?>
      <div class="col-lg-3 col-md-4 col-sm-6 mb-4 area-card" data-id="<?= (int)$m['id_carpeta'] ?>">
        <div class="card p-3 text-center folder-card" onclick="location.href='carpeta.php?id=<?= (int)$m['id_carpeta'] ?>'">
          <div class="dropdown" onclick="event.stopPropagation()">
            <button class="dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">⋮</button>
            <ul class="dropdown-menu">
              <li><a href="#" class="dropdown-item btn-edit-modulo" data-id="<?= (int)$m['id_carpeta'] ?>"><span><i class="fas fa-edit"></i></span> Editar</a></li>
              <li><a href="#" class="dropdown-item text-danger btn-del-modulo" data-id="<?= (int)$m['id_carpeta'] ?>"><span><i class="fas fa-trash-alt"></i></span> Eliminar</a></li>
            </ul>
          </div>
          <div class="folder-icon"><i class="fa-solid fa-book icono-libro" style="color: #B197FC;"></i></div>
          <h5><?= htmlspecialchars($m['nombre']) ?></h5>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

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

const areaId = <?= (int)$id_area ?>;

document.getElementById('formCrearModulo').addEventListener('submit', async function(e){
  e.preventDefault();
  const btn = document.getElementById('btnCrearModulo');
  btn.disabled = true;
  const fd = new FormData(e.target);
  fd.append('action','create');
  fd.append('id_padre', areaId);

  try {
    const res = await fetch('api/carpetas.php', { method: 'POST', body: fd });
    const j = await res.json();
    document.getElementById('moduloMsg').innerHTML = `<div class="alert alert-${j.success ? 'success' : 'danger'}">${j.message}</div>`;
    if(j.success) setTimeout(()=> location.reload(), 1000);
  } catch (err) {
    console.error(err);
    document.getElementById('moduloMsg').innerHTML = `<div class="alert alert-danger">Error de conexión</div>`;
  } finally {
    btn.disabled = false;
  }
});

// Eliminar módulo con SweetAlert2
document.querySelectorAll('.btn-del-modulo').forEach(btn=>{
  btn.addEventListener('click', async (e)=> {
    e.preventDefault();
    e.stopPropagation();
    const id = btn.dataset.id;
    
    const result = await Swal.fire({
      title: '¿Eliminar módulo?',
      text: 'Esto también eliminará su contenido.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    });

    if (result.isConfirmed) {
      try {
        const res = await fetch('api/carpetas.php',{method:'POST',body:new URLSearchParams({action:'delete',id})});
        const j = await res.json();
        
        if(j.success) {
          await Swal.fire({
            icon: 'success',
            title: '¡Eliminado!',
            text: j.message,
            confirmButtonText: 'OK'
          });
          location.reload();
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: j.message,
            confirmButtonText: 'OK'
          });
        }
      } catch (err) {
        Swal.fire({
          icon: 'error',
          title: 'Error de conexión',
          text: err.message,
          confirmButtonText: 'OK'
        });
      }
    }
  });
});

// Editar módulo con SweetAlert2
let editingId=null;
document.querySelectorAll('.btn-edit-modulo').forEach(b=>{
  b.addEventListener('click', async (e)=>{
    e.preventDefault();
    e.stopPropagation();
    const id = b.dataset.id;
    const currentName = b.closest('.area-card').querySelector('h5').textContent.trim();
    
    const { value: nuevoNombre } = await Swal.fire({
      title: '<i class="fas fa-edit"></i> Editar módulo',
      input: 'text',
      inputValue: currentName,
      inputPlaceholder: 'Escribe el nuevo nombre',
      showCancelButton: true,
      confirmButtonText: 'Guardar',
      cancelButtonText: 'Cancelar',
      inputValidator: (value) => {
        if (!value || value.trim() === '') {
          return '¡El nombre no puede estar vacío!';
        }
      }
    });

    if (nuevoNombre && nuevoNombre.trim() !== currentName) {
      try {
        const res = await fetch('api/carpetas.php',{method:'POST',body:new URLSearchParams({action:'edit',id:id,nombre:nuevoNombre.trim()})});
        const j = await res.json();
        
        if(j.success) {
          await Swal.fire({
            icon: 'success',
            title: '¡Actualizado!',
            text: j.message,
            confirmButtonText: 'OK'
          });
          location.reload();
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: j.message,
            confirmButtonText: 'OK'
          });
        }
      } catch (err) {
        Swal.fire({
          icon: 'error',
          title: 'Error de conexión',
          text: err.message,
          confirmButtonText: 'OK'
        });
      }
    }
  });
});
</script>
</body>
</html>