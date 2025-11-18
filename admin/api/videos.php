<?php
session_start(); require_once("../../conexion.php");
header('Content-Type: application/json');
if(!isset($_SESSION['id_usuario'])||$_SESSION['rol']!=='admin'){ echo json_encode(['success'=>false,'message'=>'No autorizado']); exit; }

$action = $_POST['action'] ?? '';
if($_SERVER['REQUEST_METHOD']==='POST' && empty($action)){
  // subir video + crear cuestionario (form-data)
  $id_carpeta = (int)($_POST['id_carpeta'] ?? 0);
  $titulo = trim($_POST['titulo'] ?? '');
  if(!isset($_FILES['video_file'])) { echo json_encode(['success'=>false,'message'=>'Archivo requerido']); exit; }
  $f = $_FILES['video_file'];
  if($f['error']!==0){ echo json_encode(['success'=>false,'message'=>'Error al subir']); exit; }
  $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
  $targetDir = __DIR__ . '/../../videos/';
  if(!is_dir($targetDir)) mkdir($targetDir,0755,true);
  $name = uniqid('vid_').'.'. $ext;
  $dest = $targetDir . $name;
  move_uploaded_file($f['tmp_name'], $dest);
  $ruta = "videos/" . $name;

  // insertar video
  $pdo->prepare("INSERT INTO videos (id_carpeta,titulo,ruta) VALUES (?,?,?)")
      ->execute([$id_carpeta,$titulo,$ruta]);
  $id_video = $pdo->lastInsertId();

  // crear cuestionario
  $preg = trim($_POST['pregunta'] ?? '');
  $a = trim($_POST['opcion_a'] ?? '');
  $b = trim($_POST['opcion_b'] ?? '');
  $c = trim($_POST['opcion_c'] ?? '');
  $rc = trim($_POST['respuesta_correcta'] ?? 'A');
  $pdo->prepare("INSERT INTO cuestionarios (id_video,pregunta,opcion_a,opcion_b,opcion_c,respuesta_correcta) VALUES (?,?,?,?,?,?)")
      ->execute([$id_video,$preg,$a,$b,$c,$rc]);

  echo json_encode(['success'=>true,'message'=>'Video y cuestionario creados']); exit;
}

if($action==='delete'){
  $id = (int)($_POST['id'] ?? 0);
  // eliminar video y su archivo (opcional)
  $stmt = $pdo->prepare("SELECT ruta FROM videos WHERE id_video = ?");
  $stmt->execute([$id]); $r = $stmt->fetchColumn();
  if($r && file_exists(__DIR__ . '/../../' . $r)) unlink(__DIR__ . '/../../' . $r);
  $pdo->prepare("DELETE FROM videos WHERE id_video = ?")->execute([$id]);
  echo json_encode(['success'=>true,'message'=>'Video eliminado']); exit;
}
echo json_encode(['success'=>false,'message'=>'Operación no soportada']);
