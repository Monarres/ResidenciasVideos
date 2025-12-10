<?php
session_start();

// Headers para prevenir caché
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once("../conexion.php");

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../index.php");
    exit;
}

$nombre = $_SESSION['nombre'] ?? "Usuario";
?>
<!DOCTYPE html>
<html lang="es">
<head>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v6.5.1/css/all.css">

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Usuario</title>
  <!-- Agregar meta tags anti-caché -->
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
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
  color: #333;
  cursor: pointer;
}

.user-dropdown-item:last-child {
  margin-bottom: 0;
}

.user-dropdown-item:hover {
  background: linear-gradient(135deg, rgba(245, 163, 199, 0.2), rgba(155, 124, 184, 0.2));
  transform: translateX(5px);
  color: #9b7cb8;
}

.user-dropdown-item.logout {
  color: #dc3545;
  background: linear-gradient(135deg, rgba(220, 53, 69, 0.05), rgba(245, 163, 199, 0.1));
}

.user-dropdown-item.logout:hover {
  background: linear-gradient(135deg, rgba(220, 53, 69, 0.15), rgba(245, 163, 199, 0.2));
  color: #dc3545;
}

.container {
  max-width: 1200px;
  padding: 20px 15px;
}

.welcome-card {
  background: linear-gradient(135deg, rgba(245, 163, 199, 0.2), rgba(155, 124, 184, 0.2));
  border: none;
  border-radius: 20px;
  padding: 40px;
  text-align: center;
  margin-bottom: 30px;
}

.welcome-card h1 {
  color: #9b7cb8;
  font-weight: 700;
  font-size: 2.5rem;
  margin-bottom: 15px;
}

.welcome-card p {
  color: #666;
  font-size: 1.1rem;
}

.action-card {
  border: none;
  border-radius: 20px;
  box-shadow: 0 5px 20px rgba(155, 124, 184, 0.2);
  transition: all 0.3s ease;
  overflow: hidden;
  background: white;
}

.action-card:hover {
  transform: translateY(-10px);
  box-shadow: 0 10px 30px rgba(155, 124, 184, 0.3);
}

.action-card .card-icon {
  font-size: 80px;
  margin: 30px 0 20px 0;
}

.action-card .card-title {
  color: #9b7cb8;
  font-weight: 700;
  font-size: 1.5rem;
  margin-bottom: 15px;
}

.action-card .card-text {
  color: #666;
  font-size: 1rem;
  margin-bottom: 25px;
}

.btn-primary-custom {
  background: linear-gradient(135deg, #f5a3c7, #9b7cb8);
  border: none;
  color: white;
  font-weight: 600;
  border-radius: 25px;
  padding: 12px 40px;
  font-size: 1.1rem;
  transition: 0.3s;
  text-decoration: none;
  display: inline-block;
}

.btn-primary-custom:hover {
  background: linear-gradient(135deg, #9b7cb8, #f5a3c7);
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(155, 124, 184, 0.4);
  color: white;
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
  
  .header-right {
    gap: 10px;
  }
  
  .user-toggle {
    padding: 6px 15px;
    font-size: 0.9rem;
  }
  
  .welcome-card h1 {
    font-size: 1.8rem;
  }
  
  .action-card .card-icon {
    font-size: 60px;
  }
}
/* ========== RESPONSIVO ========== */

/* Tablets y dispositivos medianos */
@media (max-width: 992px) {
  .top-header h2 {
    font-size: 1.3rem;
  }
  
  .user-toggle {
    padding: 6px 15px;
    font-size: 0.9rem;
  }
  
  .header-right {
    gap: 15px;
  }
  
  .welcome-card {
    padding: 30px;
  }
  
  .welcome-card h1 {
    font-size: 2rem;
  }
  
  .welcome-card p {
    font-size: 1rem;
  }
  
  .action-card .card-icon {
    font-size: 70px;
    margin: 25px 0 15px 0;
  }
  
  .action-card .card-title {
    font-size: 1.3rem;
  }
  
  .btn-primary-custom {
    padding: 10px 35px;
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
    position: static;
    transform: none;
    text-align: center;
    width: 100%;
  }
  
  .top-header h2 i {
    font-size: 1rem;
  }
  
  .header-right {
    justify-content: center;
    width: 100%;
    margin-left: 0;
    gap: 10px;
  }
  
  .user-toggle {
    padding: 6px 12px;
    font-size: 0.8rem;
    gap: 5px;
  }
  
  .user-toggle span:last-child {
    font-size: 0.7em !important;
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
  
  /* Welcome card */
  .welcome-card {
    padding: 25px 20px;
    margin-bottom: 20px;
    border-radius: 15px;
  }
  
  .welcome-card h1 {
    font-size: 1.6rem;
    margin-bottom: 12px;
  }
  
  .welcome-card p {
    font-size: 0.95rem;
  }
  
  /* Action cards */
  .action-card {
    border-radius: 15px;
    margin-bottom: 15px;
  }
  
  .action-card .card-body {
    padding: 20px 15px;
  }
  
  .action-card .card-icon {
    font-size: 60px;
    margin: 20px 0 15px 0;
  }
  
  .action-card .card-title {
    font-size: 1.2rem;
    margin-bottom: 12px;
  }
  
  .action-card .card-text {
    font-size: 0.9rem;
    margin-bottom: 20px;
  }
  
  .btn-primary-custom {
    padding: 10px 30px;
    font-size: 0.95rem;
  }
  
  /* Row ajuste */
  .row.justify-content-center {
    margin: 0 -5px;
  }
  
  .row.justify-content-center > div {
    padding: 0 5px;
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
  
  .user-toggle {
    font-size: 0.75rem;
    padding: 5px 10px;
  }
  
  .user-dropdown {
    min-width: 160px;
  }
  
  .user-dropdown-item {
    padding: 8px 12px;
    font-size: 0.8rem;
  }
  
  /* Welcome card más compacto */
  .welcome-card {
    padding: 20px 15px;
  }
  
  .welcome-card h1 {
    font-size: 1.4rem;
    margin-bottom: 10px;
  }
  
  .welcome-card p {
    font-size: 0.9rem;
  }
  
  /* Action cards más compactas */
  .action-card .card-body {
    padding: 20px 12px;
  }
  
  .action-card .card-icon {
    font-size: 50px;
    margin: 15px 0 12px 0;
  }
  
  .action-card .card-title {
    font-size: 1.1rem;
    margin-bottom: 10px;
  }
  
  .action-card .card-text {
    font-size: 0.85rem;
    margin-bottom: 18px;
  }
  
  .btn-primary-custom {
    padding: 8px 25px;
    font-size: 0.9rem;
  }
  
  .btn-primary-custom i {
    font-size: 0.85rem;
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
  
  .user-toggle {
    font-size: 0.7rem;
    padding: 5px 8px;
  }
  
  .welcome-card {
    padding: 18px 12px;
  }
  
  .welcome-card h1 {
    font-size: 1.3rem;
  }
  
  .welcome-card p {
    font-size: 0.85rem;
  }
  
  .action-card .card-icon {
    font-size: 45px;
  }
  
  .action-card .card-title {
    font-size: 1rem;
  }
  
  .action-card .card-text {
    font-size: 0.8rem;
  }
  
  .btn-primary-custom {
    padding: 8px 20px;
    font-size: 0.85rem;
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
  
  .header-right {
    position: static;
    width: auto;
    margin-left: auto;
  }
  
  .user-dropdown {
    right: 0;
    left: auto;
    transform: none;
  }
  
  .welcome-card {
    padding: 20px;
  }
  
  .welcome-card h1 {
    font-size: 1.5rem;
  }
  
  .action-card .card-icon {
    font-size: 50px;
    margin: 15px 0 10px 0;
  }
}

/* Tablets en orientación vertical */
@media (min-width: 769px) and (max-width: 992px) {
  .col-md-6 {
    max-width: 80%;
    margin: 0 auto;
  }
}

/* Accesibilidad táctil */
@media (hover: none) and (pointer: coarse) {
  .user-toggle,
  .user-dropdown-item,
  .btn-primary-custom {
    min-height: 44px;
    min-width: 44px;
  }
  
  .action-card {
    padding: 10px;
  }
}

/* Mejora de hover solo en desktop */
@media (hover: hover) and (pointer: fine) {
  .action-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 10px 30px rgba(155, 124, 184, 0.3);
  }
  
  .user-toggle:hover {
    background: white;
    color: #9b7cb8;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
  }
  
  .user-dropdown-item:hover {
    background: linear-gradient(135deg, rgba(245, 163, 199, 0.2), rgba(155, 124, 184, 0.2));
    transform: translateX(5px);
  }
  
  .btn-primary-custom:hover {
    background: linear-gradient(135deg, #9b7cb8, #f5a3c7);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(155, 124, 184, 0.4);
  }
}

/* Ajustes para pantallas muy anchas */
@media (min-width: 1400px) {
  .container {
    max-width: 1320px;
  }
  
  .welcome-card {
    padding: 50px;
  }
  
  .welcome-card h1 {
    font-size: 2.8rem;
  }
  
  .welcome-card p {
    font-size: 1.2rem;
  }
  
  .action-card .card-icon {
    font-size: 90px;
    margin: 35px 0 25px 0;
  }
  
  .action-card .card-title {
    font-size: 1.7rem;
  }
  
  .action-card .card-text {
    font-size: 1.1rem;
  }
  
  .btn-primary-custom {
    padding: 14px 45px;
    font-size: 1.2rem;
  }
}

/* Optimización para impresión */
@media print {
  body {
    padding-top: 0;
    background: white;
  }
  
  .top-header {
    position: static;
    margin: 0;
    background: #9b7cb8;
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
  }
  
  .user-dropdown {
    display: none !important;
  }
  
  .btn-primary-custom {
    display: none;
  }
  
  .action-card {
    page-break-inside: avoid;
  }
}
  </style>
</head>
<body>

<div class="top-header">
  <div class="container-fluid">
    <h2><i class="fa-solid fa-user" style="color: #fbfbfbff;"></i> Panel de Usuario</h2>
    <div class="header-right">
      <!-- Menú de usuario -->
      <div class="user-section">
        <button class="user-toggle" id="userToggle">
          <span><i class="fa-solid fa-user" style="color: #B197FC;"></i></span> <?= htmlspecialchars($nombre) ?> <span style="font-size: 0.8em;"><i class="fa-solid fa-caret-down" style="color: #B197FC;"></i></span>
        </button>
        <div class="user-dropdown" id="userDropdown">
          <a href="perfil_usuario.php" class="user-dropdown-item">
            <span><i class="fa-solid fa-user" style="color: #B197FC;"></i></span> Mi Perfil
          </a>
          <a href="../logout.php" class="user-dropdown-item logout">
            <span><i class="fa-solid fa-door-open" style="color: #ef061d;"></i></span> Cerrar sesión
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="container">
  
  <!-- Tarjeta de bienvenida -->
  <div class="welcome-card">
    <h1>¡Bienvenido, <?= htmlspecialchars($nombre) ?>!</h1>
    <p>Accede a tus módulos de capacitación y completa los cuestionarios para avanzar en tu formación</p>
  </div>

  <!-- Tarjetas de acción -->
  <div class="row justify-content-center">
    <div class="col-md-6 mb-4">
      <div class="card action-card text-center">
        <div class="card-body">
          <div class="card-icon"><i class="fa-solid fa-book" style="color: #B197FC;"></i></div>
          <h5 class="card-title">Módulos de Capacitación</h5>
          <p class="card-text">
            Explora todos los módulos disponibles de tu área y comienza tu aprendizaje.
          </p>
          <a href="carpetas.php" class="btn btn-primary-custom">
            Ver Módulos <i class="fa-solid fa-arrow-right-long" style="color: #ffffffff;"></i>
          </a>
        </div>
      </div>
    </div>
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
</script>
</body>
</html>