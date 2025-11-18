<?php
session_start();
header('Content-Type: application/json');
require_once("conexion.php");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Método no permitido']);
  exit;
}

$email = trim($_POST['email'] ?? '');
$pass = $_POST['password'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user && password_verify($pass, $user['contrasena'])) {
  $_SESSION['id_usuario'] = $user['id_usuario'];
  $_SESSION['rol'] = $user['rol'];
  $_SESSION['nombre'] = $user['nombre'];
  $_SESSION['id_carpeta'] = $user['id_carpeta'];

  // Determinar redirección según el rol
  $redirect = 'usuario/dashboard.php'; // Por defecto
  
  switch($user['rol']) {
    case 'admin':
      $redirect = 'admin/dashboard.php';
      break;
    case 'franquiciatario':
      $redirect = 'franquiciatario/dashboard.php';
      break;
    case 'usuario':
      $redirect = 'usuario/dashboard.php';
      break;
  }

  echo json_encode([
    'success' => true,
    'message' => "Bienvenido {$user['nombre']}",
    'redirect' => $redirect
  ]);
} else {
  echo json_encode(['success' => false, 'message' => 'Correo o contraseña incorrectos']);
}
?>