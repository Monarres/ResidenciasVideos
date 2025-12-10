<?php
session_start();

// Headers para prevenir caché
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once("../conexion.php");

// Verificar sesión y rol de franquiciatario
if(!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'franquiciatario'){ 
    header("Location: ../index.php"); 
    exit; 
}

$id_franquiciatario = $_SESSION['id_usuario'];

// Obtener áreas
$stmt = $pdo->query("SELECT * FROM carpetas WHERE id_padre IS NULL ORDER BY nombre ASC");
$areas = $stmt->fetchAll();
?>
<!doctype html>
<html lang="es">
<head>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Franquiciatario - Dashboard</title>

<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
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
  justify-content: center;
  align-items: center;
  padding: 0 30px;
  position: relative;
}

.top-header h2 {
  color: white;
  font-weight: 600;
  margin: 0;
  font-size: 1.5rem;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 15px;
  position: absolute;
  right: 30px;
}

.btn-users {
  background: white;
  color: #9b7cb8;
  padding: 8px 20px;
  border-radius: 25px;
  font-weight: 500;
  border: none;
  cursor: pointer;
  transition: 0.3s;
  text-decoration: none !important;
}

.btn-users:hover {
  background: #f8f9fa;
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(0,0,0,0.2);
  text-decoration: none !important;
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
}

.container {
  max-width: 1200px;
  padding: 20px 15px;
}

.area-card .card {
  cursor: pointer;
  transition: 0.3s;
  border-radius: 15px;
}

.area-card .card:hover {
  transform: translateY(-5px);
  box-shadow: 0 5px 20px rgba(155,124,184,0.3);
}

.folder-icon {
  font-size: 4rem;
  margin: 20px 0 10px 0;
}

.area-card h5 {
  color: #9b7cb8;
  font-weight: 600;
  margin-bottom: 20px;
}
.icono-ubicacion:hover {
    animation: fa-beat 1s infinite;
}
/* ========== RESPONSIVO ========== */

/* Tablets y dispositivos medianos */
@media (max-width: 992px) {
  .top-header h2 {
    font-size: 1.3rem;
  }
  
  .btn-users {
    padding: 6px 15px;
    font-size: 0.9rem;
  }
  
  .user-toggle {
    padding: 6px 15px;
    font-size: 0.9rem;
  }
  
  .header-right {
    right: 20px;
    gap: 10px;
  }
  
  .folder-icon {
    font-size: 3.5rem;
  }
  
  .area-card h5 {
    font-size: 1rem;
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
    text-align: center;
    width: 100%;
  }
  
  .header-right {
    position: static;
    justify-content: center;
    width: 100%;
  }
  
  .btn-users {
    padding: 6px 12px;
    font-size: 0.8rem;
  }
  
  .user-toggle {
    padding: 6px 12px;
    font-size: 0.8rem;
    gap: 5px;
  }
  
  .user-section {
    width: auto;
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
  
  /* Cards de áreas */
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
  
  /* Grid de áreas más compacto */
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
  
  .btn-users {
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
  
  .header-right {
    gap: 8px;
  }
  
  .user-dropdown {
    min-width: 160px;
  }
  
  .user-dropdown-item {
    padding: 8px 12px;
    font-size: 0.8rem;
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
  
  .header-right {
    flex-wrap: wrap;
  }
  
  .btn-users,
  .user-toggle {
    font-size: 0.7rem;
    padding: 5px 8px;
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
    width: auto;
  }
  
  .header-right {
    position: static;
    width: auto;
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
  .btn-users,
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
  
  .btn-users:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
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
}
</style>
</head>
<body>

<div class="top-header">
  <div class="container-fluid">
    <h2>Áreas de Contenido</h2>

    <div class="header-right">
      <!-- Usuario -->
      <div class="user-section">
        <button class="user-toggle" id="userToggle">
        <i class="fa-solid fa-user" style="color: #B197FC; vertical-align: middle;"></i><?=htmlspecialchars($_SESSION['nombre'])?> <i class="fa-solid fa-caret-down" style="color: #B197FC;"></i>    
        </button>
        <div class="user-dropdown" id="userDropdown">
          <a href="usuarios_franquiciatario.php" class="user-dropdown-item" style="color: #9b7cb8;"><i class="fas fa-users"></i> Mis Usuarios</a>
          <a href="../logout.php" class="user-dropdown-item"><i class="fa-solid fa-door-open" style="color: #ef061d;"></i> Cerrar sesión</a>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="container">
  <div class="row" id="areasGrid">
    <?php foreach($areas as $a): ?>
      <div class="col-lg-3 col-md-4 col-sm-6 mb-4 area-card">
        <div class="card p-3 text-center" onclick="location.href='area.php?id=<?= $a['id_carpeta'] ?>'">
          <div class="folder-icon"><i class="fa-solid fa-location-dot icono-ubicacion" style="color: #B197FC;"></i></div>
          <h5><?=htmlspecialchars($a['nombre'])?></h5>
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

</body>
</html>