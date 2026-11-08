<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "proyecto1"; // Nombre actualizado a proyecto1

$conexion = new mysqli($host, $user, $password, $database);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}
?>