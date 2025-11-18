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

.user-info {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-left: auto;
}

.user-name {
  color: white;
  font-weight: 500;
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
  
  .user-name {
    display: none;
  }
  
  .btn-logout {
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
  </style>
</head>
<body>

<div class="top-header">
  <div class="container-fluid">
    <h2>👤 Panel de Usuario</h2>
    <div class="user-info">
      <span class="user-name"><?= htmlspecialchars($nombre) ?></span>
      <a href="../logout.php" class="btn-logout">🚪 Cerrar sesión</a>
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
          <div class="card-icon">📚</div>
          <h5 class="card-title">Módulos de Capacitación</h5>
          <p class="card-text">
            Explora todos los módulos disponibles de tu área y comienza tu aprendizaje.
          </p>
          <a href="carpetas.php" class="btn btn-primary-custom">
            Ver Módulos →
          </a>
        </div>
      </div>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>