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




  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v6.5.1/css/all.css">



<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Área - <?= htmlspecialchars($area['nombre']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
.icono-libro:hover {
  animation: fa-beat 1s infinite;
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
  
  .user-toggle {
    padding: 6px 15px;
    font-size: 0.9rem;
  }
  
  .folder-icon {
    font-size: 3.5rem;
  }
  
  .area-card h5 {
    font-size: 1rem;
  }
  
  .readonly-badge {
    padding: 6px 15px;
    font-size: 0.9rem;
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
  
  .user-info {
    justify-content: center;
    width: 100%;
    margin-left: 0;
  }
  
  .btn-logout {
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
    padding: 6px 12px;
    font-size: 0.8rem;
    gap: 5px;
  }
  
  /* Cards de módulos */
  .area-card .card {
    padding: 15px !important;
    border-radius: 12px;
  }
  
  .folder-icon {
    font-size: 3rem;
    margin: 15px 0 8px 0;
  }
  
  .area-card h5 {
    font-size: 0.95rem;
    margin-bottom: 15px;
  }
  
  /* Grid de módulos más compacto */
  .col-sm-6 {
    padding-left: 8px;
    padding-right: 8px;
  }
  
  .area-card {
    margin-bottom: 15px !important;
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
  
  .btn-logout {
    font-size: 0.75rem;
    padding: 5px 10px;
  }
  
  .user-toggle {
    font-size: 0.75rem;
    padding: 5px 10px;
  }
  
  .user-toggle i:last-child {
    display: none; /* Ocultar flecha en móviles muy pequeños */
  }
  
  .user-info {
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
    padding: 5px 10px;
    font-size: 0.75rem;
  }
  
  .folder-icon {
    font-size: 2.5rem;
    margin: 10px 0 5px 0;
  }
  
  .area-card h5 {
    font-size: 0.9rem;
    margin-bottom: 10px;
  }
  
  .area-card .card {
    padding: 12px !important;
  }
  
  /* Ajustar grid para móviles pequeños */
  .area-card {
    margin-bottom: 12px !important;
  }
  
  .col-sm-6 {
    padding-left: 5px;
    padding-right: 5px;
  }
  
  /* Card vacío */
  .card.p-3 {
    padding: 15px !important;
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
  
  .user-info {
    flex-wrap: wrap;
  }
  
  .btn-logout,
  .user-toggle {
    font-size: 0.7rem;
    padding: 5px 8px;
  }
  
  .readonly-badge {
    font-size: 0.7rem;
    padding: 4px 8px;
  }
  
  .folder-icon {
    font-size: 2.2rem;
  }
  
  .area-card h5 {
    font-size: 0.85rem;
  }
  
  .area-card .card {
    padding: 10px !important;
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
  
  .user-dropdown {
    right: 0;
    left: auto;
    transform: none;
  }
  
  .folder-icon {
    font-size: 2.5rem;
    margin: 10px 0 5px 0;
  }
}

/* Tablets en orientación vertical */
@media (min-width: 769px) and (max-width: 992px) {
  .col-md-4 {
    flex: 0 0 33.333333%;
    max-width: 33.333333%;
  }
}

/* Accesibilidad táctil */
@media (hover: none) and (pointer: coarse) {
  .btn-logout,
  .user-toggle,
  .user-dropdown-item,
  .area-card .card {
    min-height: 44px;
    min-width: 44px;
  }
  
  .area-card .card {
    padding: 20px !important;
  }
}

/* Mejora de hover solo en desktop */
@media (hover: hover) and (pointer: fine) {
  .area-card .card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(155,124,184,0.3);
  }
  
  .btn-logout:hover {
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
  
  .folder-icon {
    font-size: 4.5rem;
  }
  
  .area-card h5 {
    font-size: 1.1rem;
  }
  
  .readonly-badge {
    padding: 10px 25px;
    font-size: 1rem;
  }
}

/* Sin módulos - mensaje */
@media (max-width: 576px) {
  .col-12 .card.p-3 {
    text-align: center;
    padding: 20px !important;
  }
}
</style>
</head>
<body>

<div class="top-header">
  <div class="container-fluid">
    <h2> <i class="fa-solid fa-location-dot fa-beat" style="color: #ffffffff;"></i> Área: <?= htmlspecialchars($area['nombre']) ?></h2>
    <div class="user-info">
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
      <a href="dashboard.php" class="btn-logout"><i class="fa-solid fa-angle-left" style="color: #B197FC;"></i> Volver</a>
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
          <div class="folder-icon">  <i class="fa-solid fa-book icono-libro" style="color: #B197FC;"></i>        </div>
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