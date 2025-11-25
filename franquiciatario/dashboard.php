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
          👤 <?=htmlspecialchars($_SESSION['nombre'])?> ▼
        </button>

        <div class="user-dropdown" id="userDropdown">
          <a href="usuarios_franquiciatario.php" class="user-dropdown-item" style="color: #9b7cb8;">👥 Mis Usuarios</a>
          <a href="../logout.php" class="user-dropdown-item">🚪 Cerrar sesión</a>
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
          <div class="folder-icon">🪪</div>
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