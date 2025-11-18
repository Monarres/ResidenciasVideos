<?php
require_once("../conexion.php");

$data = json_decode(file_get_contents("php://input"), true);
if(!$data) {
  echo json_encode(["success" => false, "error" => "No data"]);
  exit;
}

$id_video = $data['id_video'] ?? null;
$pregunta = $data['pregunta'] ?? '';
$opcion_a = $data['opcion_a'] ?? '';
$opcion_b = $data['opcion_b'] ?? '';
$opcion_c = $data['opcion_c'] ?? '';
$respuesta_correcta = $data['respuesta_correcta'] ?? '';

if(!$id_video || !$pregunta){
  echo json_encode(["success" => false, "error" => "Campos faltantes"]);
  exit;
}

$stmt = $conn->prepare("INSERT INTO cuestionarios (id_video, pregunta, opcion_a, opcion_b, opcion_c, respuesta_correcta, fecha_creacion)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())");
$ok = $stmt->execute([$id_video, $pregunta, $opcion_a, $opcion_b, $opcion_c, $respuesta_correcta]);

echo json_encode([
  "success" => $ok,
  "data" => [
    "pregunta" => $pregunta,
    "opcion_a" => $opcion_a,
    "opcion_b" => $opcion_b,
    "opcion_c" => $opcion_c,
    "respuesta_correcta" => $respuesta_correcta
  ]
]);
