<?php
include "conexion.php";
session_start();

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la sesión
session_destroy();

// Redirigir al inicio de sesión
header("Location: login_process.php");
exit();
?>