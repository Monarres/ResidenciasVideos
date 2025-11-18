<?php
require_once("../../conexion.php");
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);
$nombre = trim($_POST['nombre'] ?? '');
$id_padre = isset($_POST['id_padre']) ? (int)$_POST['id_padre'] : null;

try {
  if ($action === 'create') {
    if ($nombre === '') throw new Exception("El nombre no puede estar vacío.");
    $stmt = $pdo->prepare("INSERT INTO carpetas (nombre, id_padre) VALUES (?, ?)");
    $stmt->execute([$nombre, $id_padre]);
    echo json_encode(['success' => true, 'message' => 'Módulo creado con éxito']);
  }

  elseif ($action === 'edit') {
    if (!$id || $nombre === '') throw new Exception("Datos inválidos.");
    $stmt = $pdo->prepare("UPDATE carpetas SET nombre=? WHERE id_carpeta=?");
    $stmt->execute([$nombre, $id]);
    echo json_encode(['success' => true, 'message' => 'Nombre actualizado']);
  }

  elseif ($action === 'delete') {
    if (!$id) throw new Exception("ID no válido.");
    $stmt = $pdo->prepare("DELETE FROM carpetas WHERE id_carpeta=?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'Carpeta eliminada']);
  }

  else {
    throw new Exception("Acción no válida.");
  }

} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
