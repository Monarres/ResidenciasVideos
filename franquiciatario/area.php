<?php
session_start();
require_once("../conexion.php");

if(!isset($_SESSION['id_usuario']) || $_SESSION['rol']!=='franquiciatario'){
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
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { background:#f0d5e8; font-family:'Poppins',sans-serif; min-height:100vh; padding-top:100px; }
.top-header { position:fixed; top:0; left:0; right:0; background:linear-gradient(135deg,#b893cc,#f5a3c7); box-shadow:0 2px 10px rgba(0,0,0,0.15); z-index:1000; padding:20px 0; margin:15px; border-radius:20px; }
.top-header .container-fluid { display:flex; justify-content:space-between; align-items:center; padding:0 30px; position:relative; }
.top-header h2 { color:white; font-weight:600; margin:0; font-size:1.5rem; position:absolute; left:50%; transform:translateX(-50%); }

.user-info { display:flex; align-items:center; gap:15px; margin-left:auto; }

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

.btn-logout { 
  background:white; 
  border:none; 
  color:#9b7cb8; 
  font-weight:500; 
  border-radius:25px; 
  padding:8px 20px; 
  transition:0.3s; 
  text-decoration:none; 
  display:inline-block; 
}

.btn-logout:hover { 
  background:#f8f9fa; 
  transform:translateY(-2px); 
  box-shadow:0 5px 15px rgba(0,0,0,0.2); 
  color:#9b7cb8; 
}

.container { max-width:1200px; padding:20px 15px; }
.card { border:none; border-radius:15px; box-shadow:0 2px 10px rgba(0,0,0,0.1); background:white; }

/* Badge de solo lectura */
.readonly-badge {
  background: linear-gradient(135deg, #ffd700, #ffed4e);
  color: #856404;
  padding: 8px 20px;
  border-radius: 25px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 20px;
  box-shadow: 0 2px 10px rgba(255, 215, 0, 0.3);
}

.folder-icon { font-size:4rem; margin:20px 0 10px 0; }
.area-card .card { cursor:pointer; transition:all 0.3s ease; height:100%; position:relative; }
.area-card .card:hover { transform:translateY(-5px); box-shadow:0 5px 20px rgba(155,124,184,0.3); }
.area-card h5 { color:#9b7cb8; font-weight:600; margin-bottom:20px; }

#modulosGrid .col-md-3 { display:flex; }
.folder-card { flex:1; }

@media (max-width:768px){
  body { padding-top:90px; }
  .top-header { margin:10px; border-radius:15px; }
  .top-header .container-fluid { padding:0 15px; }
  .top-header h2 { font-size:1.2rem; }
  .btn-logout { padding:6px 15px; font-size:0.9rem; }
}
</style>
</head>
<body>

<div class="top-header">
  <div class="container-fluid">
    <h2>Área: <?= htmlspecialchars($area['nombre']) ?></h2>
    <div class="user-info">
      <!-- Usuario Desplegable -->
      <div class="user-section">
        <button class="user-toggle" id="userToggle">
          👤 <?= htmlspecialchars($_SESSION['nombre'] ?? 'Franquiciatario') ?> ▼
        </button>

        <div class="user-dropdown" id="userDropdown">
          <a href="usuarios_franquiciatario.php" class="user-dropdown-item" style="color: #9b7cb8;">👥 Mis Usuarios</a>
          <a href="../logout.php" class="user-dropdown-item">🚪 Cerrar sesión</a>
        </div>
      </div>

      <!-- Botón Volver -->
      <a href="dashboard.php" class="btn-logout">⬅ Volver</a>
    </div>
  </div>
</div>

<div class="container py-4">

  <div class="row" id="modulosGrid">
    <?php if(empty($modulos)): ?>
      <div class="col-12"><div class="card p-3">No hay módulos en esta área.</div></div>
    <?php endif; ?>

    <?php foreach($modulos as $m): ?>
      <div class="col-lg-3 col-md-4 col-sm-6 mb-4 area-card" data-id="<?= (int)$m['id_carpeta'] ?>">
        <div class="card p-3 text-center folder-card" onclick="location.href='carpeta.php?id=<?= (int)$m['id_carpeta'] ?>'">
          <div class="folder-icon">📚</div>
          <h5><?= htmlspecialchars($m['nombre']) ?></h5>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

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