<?php
require_once("../../conexion.php");

$action = $_POST['action'] ?? '';
if($action === 'guardar'){
  $id_video = (int)($_POST['id_video'] ?? 0);
  $instrucciones = trim($_POST['instrucciones'] ?? '');
  $preguntas = json_decode($_POST['preguntas'] ?? '[]', true);

  foreach($preguntas as $p){
    $stmt = $pdo->prepare("INSERT INTO cuestionarios (id_video, pregunta, opcion_a, opcion_b, opcion_c, respuesta_correcta, fecha_creacion)
                           VALUES (?,?,?,?,?,?,NOW())");
    $opA = $p['opciones'][0] ?? null;
    $opB = $p['opciones'][1] ?? null;
    $opC = $p['opciones'][2] ?? null;
    $stmt->execute([$id_video, $p['pregunta'], $opA, $opB, $opC, $p['respuesta']]);
  }

  echo json_encode(['success'=>true,'message'=>'Cuestionario guardado correctamente.']);
}
