<?php
session_start(); require_once("../../conexion.php");
header('Content-Type: application/json');
if(!isset($_SESSION['id_usuario'])||$_SESSION['rol']!=='admin'){ echo json_encode(['success'=>false,'message'=>'No autorizado']); exit; }

$action = $_POST['action'] ?? '';
if($action==='create'){
  $n = trim($_POST['nombre'] ?? '');
  if(!$n){ echo json_encode(['success'=>false,'message'=>'Nombre requerido']); exit; }
  $pdo->prepare("INSERT INTO carpetas (nombre,id_padre) VALUES (?,NULL)")->execute([$n]);
  echo json_encode(['success'=>true,'message'=>'Área creada']);
  exit;
}
if($action==='delete'){
  $id = (int)($_POST['id'] ?? 0);
  $pdo->prepare("DELETE FROM carpetas WHERE id_carpeta = ?")->execute([$id]);
  echo json_encode(['success'=>true,'message'=>'Área eliminada']); exit;
}
if($action==='edit'){
  $id = (int)($_POST['id'] ?? 0);
  $n = trim($_POST['nombre'] ?? '');
  $pdo->prepare("UPDATE carpetas SET nombre=? WHERE id_carpeta=?")->execute([$n,$id]);
  echo json_encode(['success'=>true,'message'=>'Área actualizada']); exit;
}
echo json_encode(['success'=>false,'message'=>'Acción desconocida']);
