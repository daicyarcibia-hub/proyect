<?php
session_start();
include 'conexion.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: login_process.php");
    exit();
}

$anio_actual = date("Y");
$proximo_nro_hr = $anio_actual . "-001"; // Valor por defecto si no hay registros

// Buscar el último registro de hoja de ruta basado en la tabla real 'hoja_ruta'
$sql_max = "SELECT numero_hoja_ruta FROM hoja_ruta ORDER BY numero_hoja_ruta DESC LIMIT 1";
$resultado_max = $conexion->query($sql_max);

if ($resultado_max && $resultado_max->num_rows > 0) {
    $fila = $resultado_max->fetch_assoc();
    $ultimo_id = intval($fila['numero_hoja_ruta']);
    $proximo_id = $ultimo_id + 1;
    $proximo_nro_hr = $anio_actual . "-" . str_pad($proximo_id, 3, "0", STR_PAD_LEFT);
}

// PROCESAR EL FORMULARIO DE NUEVA HOJA DE RUTA (MÉTODO POST UNIFICADO)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['procedencia'])) {
    $procedencia          = trim($_POST['procedencia'] ?? '');
    $fecha_recepcion      = trim($_POST['fecha_recepcion'] ?? '');
    $hora_recepcion       = trim($_POST['hora_recepcion'] ?? '');
    $referencia           = trim($_POST['referencia'] ?? '');
    $documentos_adjuntos  = trim($_POST['documentos_adjuntos'] ?? '');
    $fojas                = intval($_POST['fojas'] ?? 1);
    $tipo_correspondencia = trim($_POST['tipo_correspondencia'] ?? '');
    $estado               = 'En Despacho de Dirección';
    $pdf_path             = "";

    // Manejo de la subida del archivo PDF
    if (isset($_FILES['documento_pdf']) && $_FILES['documento_pdf']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath   = $_FILES['documento_pdf']['tmp_name'];
        $fileName      = $_FILES['documento_pdf']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($fileExtension === 'pdf') {
            $uploadFileDir = 'uploads/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }

            $newFileName = md5(time() . $fileName) . '.pdf';
            $dest_path   = $uploadFileDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $pdf_path = $dest_path;
            } else {
                echo "<script>alert('Error: No se pudo mover el archivo PDF al directorio de subidas.'); window.history.back();</script>";
                exit();
            }
        } else {
            echo "<script>alert('Error: Solo se permiten archivos en formato PDF.'); window.history.back();</script>";
            exit();
        }
    } else {
        echo "<script>alert('Error: El documento digitalizado en PDF es obligatorio.'); window.history.back();</script>";
        exit();
    }

    // Iniciar una transacción para guardar en las dos tablas vinculadas ('solicitud' y 'hoja_ruta')
    $conexion->begin_transaction();

    try {
        // 1. Insertar en la tabla 'solicitud'
        $sql_solicitud = "INSERT INTO solicitud (procedencia, fecha_recepcion, hora_recepcion, referencia, documentos_adjuntos, fojas, tipo_correspondencia, documento_pdf, estado) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_sol = $conexion->prepare($sql_solicitud);
        $stmt_sol->bind_param("sssssisss", $procedencia, $fecha_recepcion, $hora_recepcion, $referencia, $documentos_adjuntos, $fojas, $tipo_correspondencia, $pdf_path, $estado);
        $stmt_sol->execute();
        $id_solicitud_generado = $stmt_sol->insert_id;
        $stmt_sol->close();

        // 2. Insertar en la tabla 'hoja_ruta' vinculando el ID de la solicitud
        $sql_hr = "INSERT INTO hoja_ruta (id_solicitud) VALUES (?)";
        $stmt_hr = $conexion->prepare($sql_hr);
        $stmt_hr->bind_param("i", $id_solicitud_generado);
        $stmt_hr->execute();
        $stmt_hr->close();

        // Confirmar transacción
        $conexion->commit();

        echo "<script>
                alert('Hoja de Ruta registrada y enviada a Dirección exitosamente.');
                window.location.href = 'secretaria.php';
              </script>";
        exit();

    } catch (Exception $e) {
        $conexion->rollback();
        echo "<script>alert('Error al registrar en la base de datos: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
        exit();
    }

    // Línea solicitada mantenida
    
        $cerrar-> session_destroy();
    
}

// CONSULTAS REALES PARA LOS CONTADORES DEL PANEL GENERAL (Usando la tabla 'solicitud')
$sql_hoy = "SELECT COUNT(*) as total FROM solicitud WHERE DATE(fecha_recepcion) = CURDATE()";
$res_hoy = $conexion->query($sql_hoy);
$total_registrados_hoy = ($res_hoy) ? $res_hoy->fetch_assoc()['total'] : 0;

$sql_dir = "SELECT COUNT(*) as total FROM solicitud WHERE estado LIKE '%Dirección%' OR estado LIKE '%Despacho%'";
$res_dir = $conexion->query($sql_dir);
$total_enviados_direccion = ($res_dir) ? $res_dir->fetch_assoc()['total'] : 0;

$sql_pend = "SELECT COUNT(*) as total FROM solicitud WHERE estado = 'Pendiente' OR estado = 'En Proceso'";
$res_pend = $conexion->query($sql_pend);
$total_pendientes = ($res_pend) ? $res_pend->fetch_assoc()['total'] : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo de Secretaría - GADP Potosí</title>
    <!-- Tipografía formal y limpia -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/estilossecre.css">
</head>
<body>

    <!-- ENCABEZADO INSTITUCIONAL -->
    <header class="top-navbar">
        <div class="brand-logo">
            <h1>GOBIERNO AUTÓNOMO DEPARTAMENTAL DE POTOSÍ</h1>
        </div>
        <div class="user-profile">
            <div class="user-info">
                <div class="name"><?php echo htmlspecialchars($_SESSION['usuario'] ?? 'Delia'); ?></div>
                <div class="role"><?php echo htmlspecialchars(ucfirst($_SESSION['rol'] ?? 'Secretaria')); ?></div>
            </div>
            <a href="login_process.php" class="btn-logout">Salir</a>
        </div>
    </header>

    <!-- LAYOUT PRINCIPAL -->
    <div class="main-layout">
        
        <!-- MENÚ LATERAL FORMAL -->
        <aside class="sidebar">
            <div class="sidebar-title">Menú Principal</div>
            <a class="menu-item active" onclick="cambiarVista('vista-inicio', this)">Panel General</a>
            <a class="menu-item" onclick="cambiarVista('vista-nueva', this)">Nueva Hoja de Ruta</a>
            <a class="menu-item" onclick="cambiarVista('vista-buscar', this)">Buscar y Editar Hoja de Ruta</a>
            <a class="menu-item" onclick="cambiarVista('vista-listado', this)">Listado de Trámites</a>
        </aside>

        <!-- CONTENIDO -->
        <main class="content-area">

           <!-- VISTA 1: PANEL GENERAL (DASHBOARD) -->
            <section id="vista-inicio" class="view-section active">
                <div style="background: linear-gradient(135deg, #1b2a47 0%, #2a3f68 100%); color: white; padding: 35px 20px; border-radius: 6px; margin-bottom: 25px; border-bottom: 4px solid #c59b27; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                    <h2 style="font-size: 1.6rem; font-weight: 700; letter-spacing: 0.8px; margin-bottom: 8px; font-family: 'Roboto', sans-serif; text-transform: uppercase; color: #ffffff;">
                        Bienvenido a la Plataforma Web
                    </h2>
                    <p style="font-size: 0.95rem; color: #cbd5e1; font-weight: 300; letter-spacing: 0.4px;">
                        Gobierno Autónomo Departamental de Potosí &bull; Control y Seguimiento de Hoja de Ruta
                    </p>
                </div>

                <h3 style="font-size: 1.15rem; color: #1b2a47; margin-bottom: 15px; font-weight: 700;">Resumen de Correspondencia</h3>
                <div class="dashboard-grid">
                    <div class="stat-box" style="border-left: 4px solid #1b2a47;">
                        <h3>Registrados Hoy</h3>
                        <div class="val"><?php echo $total_registrados_hoy; ?></div>
                    </div>
                    <div class="stat-box" style="border-left: 4px solid #0d9488;">
                        <h3>Enviados a Dirección</h3>
                        <div class="val"><?php echo $total_enviados_direccion; ?></div>
                    </div>
                    <div class="stat-box" style="border-left: 4px solid #d97706;">
                        <h3>Pendientes</h3>
                        <div class="val"><?php echo $total_pendientes; ?></div>
                    </div>
                </div>
            </section>

            <!-- VISTA 2: NUEVA HOJA DE RUTA -->
            <section id="vista-nueva" class="view-section">
                <div class="form-card">
                    <form action="" method="POST" enctype="multipart/form-data">
                        
                        <div class="form-header-title">
                            <h2>REGISTRO DE CORRESPONDENCIA - HOJA DE RUTA</h2>
                            <div class="hr-number-box">
                                <label>N° H.R. (Sugerido):</label>
                                <input type="text" name="nro_hr_mostrado" value="<?php echo $proximo_nro_hr; ?>" readonly>
                            </div>
                        </div>

                        <!-- Fila 1 -->
                        <div class="form-row">
                            <div class="form-group col-flex">
                                <label>Procedencia:</label>
                                <input type="text" name="procedencia" placeholder="Ej. Secretaría Departamental de Obras Públicas" required>
                            </div>
                            <div class="form-group col-medium">
                                <label>Fecha de Recepción:</label>
                                <input type="date" name="fecha_recepcion" id="f_ingreso" required>
                            </div>
                            <div class="form-group col-medium">
                                <label>Hora de Recepción:</label>
                                <input type="time" name="hora_recepcion" id="h_ingreso" required>
                            </div>
                        </div>

                        <!-- Fila 2 -->
                        <div class="form-row">
                            <div class="form-group col-flex">
                                <label>Referencia / Asunto:</label>
                                <textarea name="referencia" placeholder="Detalle el asunto del documento..." required></textarea>
                            </div>
                        </div>

                        <!-- Fila 3 -->
                        <div class="form-row">
                            <div class="form-group col-flex">
                                <label>Documentos Adjuntos:</label>
                                <input type="text" name="documentos_adjuntos" placeholder="Ej. Informes, Anexos, CD">
                            </div>
                            <div class="col-small">
                                <div class="form-group">
                                    <label>Fojas:</label>
                                    <input type="number" name="fojas" min="1" value="1" required>
                                </div>
                            </div>
                            <div class="form-group col-flex">
                                <label>Tipo de Correspondencia:</label>
                                <input type="text" name="tipo_correspondencia" placeholder="Ej. Nota Externa / Memorándum" required>
                            </div>
                        </div>

                        <!-- CAMPO PARA SUBIR ARCHIVO PDF ORIGINAL -->
                        <div class="form-row">
                            <div class="form-group col-flex">
                                <label>Adjuntar Documento Digitalizado (PDF):</label>
                                <input type="file" name="documento_pdf" accept=".pdf" required>
                                <small style="color: #6c757d; font-size: 0.7rem; margin-top: 3px;">Subir archivo escaneado original de la solicitud o nota de procedencia.</small>
                            </div>
                        </div>

                        <button type="submit" class="btn-official">Guardar y Enviar al Director</button>
                    </form>
                </div>
            </section>

            <!-- VISTA 3: BUSCAR Y EDITAR HOJA DE RUTA -->
            <section id="vista-buscar" class="view-section">
                <div class="form-card">
                    <h2 style="font-size: 1rem; color: #1b2a47; margin-bottom: 15px; font-weight: 700;">Buscar Hoja de Ruta para Editar</h2>
                    
                    <div class="search-box-group">
                        <input type="text" id="input_buscar" placeholder="Ingrese el N° de Hoja de Ruta (Ej. 1)...">
                        <button type="button" class="btn-official" onclick="buscarHojaRuta()">Buscar</button>
                    </div>

                    <!-- El contenedor enviará datos reales mediante atributos 'name' a actualizar_hr.php -->
                    <div id="resultado_edicion" style="display: none; border-top: 2px dashed #ced4da; padding-top: 20px; margin-top: 10px;">
                        <div class="form-header-title">
                            <h2 style="color: #0284c7;">MODIFICAR REGISTRO - HOJA DE RUTA</h2>
                            <div class="hr-number-box">
                                <label>N° H.R.:</label>
                                <input type="text" id="edit_hr_num" name="numero_hoja_ruta" readonly>
                            </div>
                        </div>

                        <form action="actualizar_hr.php" method="POST">
                            <!-- Input oculto para asegurar el paso de la llave primaria -->
                            <input type="hidden" id="edit_hr_hidden" name="nro_hr_original">
                            
                            <div class="form-row">
                                <div class="form-group col-flex">
                                    <label>Procedencia:</label>
                                    <input type="text" id="edit_procedencia" name="procedencia" required>
                                </div>
                                <div class="form-group col-medium">
                                    <label>Fecha:</label>
                                    <input type="date" id="edit_fecha" name="fecha_recepcion" required>
                                </div>
                                <div class="form-group col-medium">
                                    <label>Hora:</label>
                                    <input type="time" id="edit_hora" name="hora_recepcion" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-flex">
                                    <label>Referencia / Asunto:</label>
                                    <textarea id="edit_referencia" name="referencia" required></textarea>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-flex">
                                    <label>Documentos Adjuntos:</label>
                                    <input type="text" id="edit_adjuntos" name="documentos_adjuntos">
                                </div>
                                <div class="col-small">
                                    <div class="form-group">
                                        <label>Fojas:</label>
                                        <input type="number" id="edit_fojas" name="fojas" min="1" required>
                                    </div>
                                </div>
                                <div class="form-group col-flex">
                                    <label>Tipo de Correspondencia:</label>
                                    <input type="text" id="edit_tipo" name="tipo_correspondencia" required>
                                </div>
                            </div>

                            <button type="submit" class="btn-official btn-edit">Guardar Cambios de la Hoja de Ruta</button>
                        </form>
                    </div>

                </div>
            </section>

            <!-- VISTA 4: LISTADO DE TRÁMITES REAL -->
            <section id="vista-listado" class="view-section">
                <h2 style="font-size: 1.15rem; color: #1b2a47; margin-bottom: 15px; font-weight: 700;">Listado General de Correspondencia</h2>
                <table class="table-official">
                    <thead>
                        <tr>
                            <th>N° Hoja de Ruta</th>
                            <th>Procedencia</th>
                            <th>Fecha / Hora</th>
                            <th>Estado Actual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Consulta uniendo las tablas reales 'hoja_ruta' y 'solicitud'
                        $sql_listar = "SELECT hr.numero_hoja_ruta, s.procedencia, s.fecha_recepcion, s.hora_recepcion, s.estado 
                                       FROM hoja_ruta hr 
                                       INNER JOIN solicitud s ON hr.id_solicitud = s.id 
                                       ORDER BY s.fecha_recepcion DESC, s.hora_recepcion DESC";
                        $resultado_lista = $conexion->query($sql_listar);

                        if ($resultado_lista && $resultado_lista->num_rows > 0) {
                            while ($fila = $resultado_lista->fetch_assoc()) {
                                $fecha_formateada = date("d/m/Y", strtotime($fila['fecha_recepcion']));
                                $hora_formateada = substr($fila['hora_recepcion'], 0, 5);
                                
                                echo "<tr>";
                                echo "<td><b>" . htmlspecialchars($fila['numero_hoja_ruta']) . "</b></td>";
                                echo "<td>" . htmlspecialchars($fila['procedencia']) . "</td>";
                                echo "<td>" . $fecha_formateada . " " . $hora_formateada . "</td>";
                                echo "<td><span style='color: #b45309; font-weight: 700;'>" . htmlspecialchars($fila['estado']) . "</span></td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' style='text-align:center; color:#6c757d;'>No hay hojas de ruta registradas en el sistema.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </section>

        </main>
    </div>

    <!-- PIE DE PÁGINA INSTITUCIONAL -->
    <footer class="footer-bar">
        Gobierno Autónomo Departamental de Potosí &bull; Dirección Departamental de Auditoría Interna &copy; 2026
    </footer>

    <!-- SCRIPT DE INTERACTIVIDAD -->
    <script>
        function cambiarVista(idVista, elemento) {
            document.querySelectorAll('.view-section').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.menu-item').forEach(m => m.classList.remove('active'));
            document.getElementById(idVista).classList.add('active');
            if(elemento) {
                elemento.classList.add('active');
            }
        }

        function buscarHojaRuta() {
            const val = document.getElementById('input_buscar').value.trim();
            if(val === "") {
                alert("Por favor, ingrese un número de Hoja de Ruta para buscar.");
                return;
            }

            fetch('obtener_hr.php?nro_hr=' + encodeURIComponent(val))
                .then(response => response.json())
                .then(data => {
                    if(data.error) {
                        alert(data.error);
                        document.getElementById('resultado_edicion').style.display = 'none';
                    } else {
                        document.getElementById('edit_hr_num').value = data.numero_hoja_ruta;
                        document.getElementById('edit_hr_hidden').value = data.numero_hoja_ruta;
                        document.getElementById('edit_procedencia').value = data.procedencia;
                        document.getElementById('edit_fecha').value = data.fecha_recepcion;
                        document.getElementById('edit_hora').value = data.hora_recepcion;
                        document.getElementById('edit_referencia').value = data.referencia;
                        document.getElementById('edit_adjuntos').value = data.documentos_adjuntos;
                        document.getElementById('edit_fojas').value = data.fojas;
                        document.getElementById('edit_tipo').value = data.tipo_correspondencia;
                        
                        document.getElementById('resultado_edicion').style.display = 'block';
                    }
                })
                .catch(err => {
                    console.error("Error en la petición:", err);
                    alert("Ocurrió un error al procesar la búsqueda.");
                });
        }

        document.addEventListener("DOMContentLoaded", function() {
            const now = new Date();
            const fecha = now.toISOString().split('T')[0];
            const hora = now.toTimeString().split(' ')[0].substring(0, 5);
            if(document.getElementById('f_ingreso')) {
                document.getElementById('f_ingreso').value = fecha;
                document.getElementById('h_ingreso').value = hora;
            }
        });
    </script>
</body>
</html>