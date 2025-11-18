<?php
session_start();
require_once("../conexion.php");

header('Content-Type: application/json');

if(!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
  echo json_encode(['success'=>false, 'message'=>'No autorizado']);
  exit;
}

$nombre = $_POST['nombre'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$id_carpeta = $_POST['id_carpeta'] ?? null;

if(!$nombre || !$email || !$password || !$id_carpeta) {
  echo json_encode(['success'=>false, 'message'=>'Faltan datos requeridos']);
  exit;
}

try {
  // Aquí hash de la contraseña
  $passHash = password_hash($password, PASSWORD_DEFAULT);
  
  $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, id_carpeta) VALUES (?, ?, ?, ?)");
  $stmt->execute([$nombre, $email, $passHash, $id_carpeta]);

  echo json_encode(['success'=>true, 'message'=>'Usuario agregado correctamente']);
} catch(PDOException $e) {
  echo json_encode(['success'=>false, 'message'=>'Error: '.$e->getMessage()]);
}