<?php
session_start();
require_once 'conexion.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST['usuario'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');

    if (!empty($usuario) && !empty($contrasena)) {
        $stmt = $conexion->prepare("SELECT id_usuario, usuario, contrasena, rol FROM usuario WHERE TRIM(usuario) = ?");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 1) {
            $datos = $resultado->fetch_assoc();

            if ($contrasena === $datos['contrasena'] || password_verify($contrasena, $datos['contrasena'])) {
                // Asignar los datos a la sesión actual
                $_SESSION['id_usuario'] = $datos['id_usuario'];
                $_SESSION['usuario'] = $datos['usuario'];
                $_SESSION['rol'] = strtolower(trim($datos['rol']));

                $rol = $_SESSION['rol'];

                // Redirección según rol
                if ($rol === 'auditor' || $rol === 'tecnico') {
                    header("Location: auditor.php");
                    exit();
                } elseif ($rol === 'secretaria') {
                    header("Location: secretaria.php");
                    exit();
                } elseif ($rol === 'director') {
                    header("Location: director.php");
                    exit();
                } else {
                    $error = "El rol de usuario no está autorizado.";
                }
            } else {
                $error = "Contraseña incorrecta.";
            }
        } else {
            $error = "El usuario no existe.";
        }
        $stmt->close();
    } else {
        $error = "Por favor completa todos los campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plataforma Web de Hoja de Ruta - GADP</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
    <div class="card-container">
        <header class="header">
           <div class="header-logo"><img src="img/logo.jpg" alt="Logo Gobernación"></div>
          <div class="header-title">
                <h1>PLATAFORMA WEB DE HOJA DE RUTA</h1>
                <p>Seguimiento y Control Documental</p>
            </div>
        </header>
        <main class="content">
            <div class="image-box"><img src="img/image.png" alt="Edificio"></div>
            <form class="form-box" action="login_process.php" method="POST">
                <div class="form-header">
                    <h2>ACCESO AL SISTEMA</h2>
                    <span class="form-subtitle">Ingrese sus credenciales autorizadas</span>
                </div>
                <?php if (!empty($error)): ?>
                    <div style="background-color: #fee2e2; color: #991b1b; padding: 10px; border-radius: 4px; font-size: 0.8rem; margin-bottom: 15px; text-align: center; font-weight: 500;">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                <div class="form-group">
                    <label for="usuario">Usuario</label>
                    <input type="text" id="usuario" name="usuario" placeholder="Ej. ninfa000" autocomplete="off" required>
                </div>
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="contrasena" name="contrasena" placeholder="••••••••••••" required>
                </div>
                <button type="submit" class="btn-submit">INGRESAR</button>
            </form>
        </main>
        <footer class="footer">
            <p class="footer-title">Gobierno Autónomo Departamental de Potosí</p>
            <p class="footer-subtitle">Dirección Departamental de Auditoría Interna &bull; Gestión 2026</p>
        </footer>
    </div>
</body>
</html>