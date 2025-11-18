<?php
require_once("conexion.php");
$nombre='Administrador';
$email='admin@local';
$password='123456';
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email=?");
$stmt->execute([$email]);
if($stmt->fetchColumn()>0){ echo "Ya existe"; exit; }
$pdo->prepare("INSERT INTO usuarios (nombre,email,contrasena,rol) VALUES (?,?,?,?)")
    ->execute([$nombre,$email,$hash,'admin']);
echo "Admin creado: $email / $password - elimina este archivo";
