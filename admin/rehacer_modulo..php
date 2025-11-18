<?php
session_start();
require_once("../conexion.php");

// Solo admin puede hacerlo
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$id_usuario = $_GET['id_usuario'] ?? null;
$id_modulo  = $_GET['id_modulo'] ?? null;

if ($id_usuario && $id_modulo) {
    // Borrar todas las respuestas del usuario en ese módulo
    $stmt = $pdo->prepare("
        DELETE FROM respuestas_usuario 
        WHERE id_usuario = ? 
        AND id_cuestionario IN (
            SELECT c.id_cuestionario
            FROM cuestionarios c
            INNER JOIN videos v ON c.id_video = v.id_video
            WHERE v.id_carpeta = ?
        )
    ");
    $stmt->execute([$id_usuario, $id_modulo]);

    header("Location: respuestas.php?id_usuario=$id_usuario&msg=rehacer_modulo_ok");
    exit;
} else {
    header("Location: respuestas.php?error=missing_params");
    exit;
}
?>
