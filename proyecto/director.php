<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
require_once 'conexion.php';

// 1. Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: login_process.php");
    exit();
}

// 2. BLOQUEO DE SEGURIDAD ESTRICTO POR ROL: 
// Si el usuario actual no es director, se rechaza el acceso y se redirige
$rol_actual = strtolower(trim($_SESSION['rol'] ?? ''));
if ($rol_actual !== 'director') {
    echo "<script>
            alert('Acceso denegado: Su usuario tiene el rol [ " . htmlspecialchars($rol_actual) . " ] y no cuenta con permisos para ingresar al Módulo de Dirección.');
            window.location.href = 'login_process.php';
          </script>";
    exit();
}

// Procesar el registro del primer destinatario y derivación (Método POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion']) && $_POST['accion'] === 'derivar') {
    $numero_hoja_ruta    = intval($_POST['numero_hoja_ruta']);
    $nombre              = trim($_POST['nombre']);
    $documentos_adjuntos = trim($_POST['documentos_adjuntos']);
    $instruccion         = trim($_POST['instruccion']);
    $fecha_salida        = trim($_POST['fecha_salida']);
    $hora_salida         = trim($_POST['hora_salida']);
    $nuevo_estado        = trim($_POST['nuevo_estado']);

    $conexion->begin_transaction();

    try {
        // 1. Insertar el registro en la tabla `destinatarios` (Primer Destinatario)
        $sql_destinatario = "INSERT INTO destinatarios (numero_hoja_ruta, nombre, documentos_adjuntos, instruccion, fecha_salida, hora_salida) 
                             VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conexion->prepare($sql_destinatario);
        $stmt->bind_param("isssss", $numero_hoja_ruta, $nombre, $documentos_adjuntos, $instruccion, $fecha_salida, $hora_salida);
        $stmt->execute();
        $stmt->close();

        // 2. Actualizar el estado de la solicitud vinculada a esa hoja de ruta
        $sql_estado = "UPDATE solicitud s 
                       INNER JOIN hoja_ruta hr ON s.id = hr.id_solicitud 
                       SET s.estado = ? 
                       WHERE hr.numero_hoja_ruta = ?";
        $stmt_estado = $conexion->prepare($sql_estado);
        $stmt_estado->bind_param("si", $nuevo_estado, $numero_hoja_ruta);
        $stmt_estado->execute();
        $stmt_estado->close();

        $conexion->commit();

        echo "<script>
                alert('Trámite atendido y derivado exitosamente al primer destinatario.');
                window.location.href = 'director.php';
              </script>";
        exit();
 if (isset($cerrar)) {
        $cerrar-> session_destroy();
    }
    } catch (Exception $e) {
        $conexion->rollback();
        echo "Error al procesar la derivación: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo de Dirección - GADP Potosí</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/director.css">
</head>
<body>

    <!-- ENCABEZADO INSTITUCIONAL -->
    <header class="top-navbar">
        <div class="brand-logo">
            <h1>PLATAFORMA WEB DE CONTROL Y SEGUIMIENTO DE HOJA DE RUTA</h1>
        </div>
        <div class="user-profile">
            <div class="user-info">
                <div class="name"><?php echo htmlspecialchars($_SESSION['usuario'] ?? 'Lic. Willy Cueto'); ?></div>
                <div class="role"><?php echo htmlspecialchars(ucfirst($_SESSION['rol'] ?? 'Director')); ?></div>
            </div>
            <a href="<?php $cerrar?>" class="btn-logout">Salir</a>
        </div>
    </header>

    <!-- LAYOUT PRINCIPAL -->
    <div class="main-layout">
        
        <!-- MENÚ LATERAL -->
        <aside class="sidebar">
            <div class="sidebar-title">Módulo Dirección</div>
            <a class="menu-item active" onclick="cambiarVista('vista-bandeja', this)">Bandeja de Entrada</a>
            <a class="menu-item" onclick="cambiarVista('vista-derivar', this)">Atender y Derivar Trámite</a>
        </aside>

        <!-- CONTENIDO -->
        <main class="content-area">

            <!-- VISTA 1: BANDEJA DE ENTRADA -->
            <section id="vista-bandeja" class="view-section active">
                <h2 style="font-size: 1.15rem; color: #1b2a47; margin-bottom: 15px; font-weight: 700;">Bandeja de Correspondencia en Despacho</h2>
                <table class="table-official">
                    <thead>
                        <tr>
                            <th>N° Hoja de Ruta</th>
                            <th>Procedencia</th>
                            <th>Referencia</th>
                            <th>Fecha Recepción</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql_bandeja = "SELECT hr.numero_hoja_ruta, s.procedencia, s.referencia, s.fecha_recepcion 
                                        FROM hoja_ruta hr 
                                        INNER JOIN solicitud s ON hr.id_solicitud = s.id 
                                        WHERE s.estado = 'En Despacho de Dirección' 
                                        ORDER BY s.fecha_recepcion DESC";
                        $resultado_bandeja = $conexion->query($sql_bandeja);

                        if ($resultado_bandeja && $resultado_bandeja->num_rows > 0) {
                            while ($fila = $resultado_bandeja->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td><b>" . htmlspecialchars($fila['numero_hoja_ruta']) . "</b></td>";
                                echo "<td>" . htmlspecialchars($fila['procedencia']) . "</td>";
                                echo "<td>" . htmlspecialchars($fila['referencia']) . "</td>";
                                echo "<td>" . htmlspecialchars($fila['fecha_recepcion']) . "</td>";
                                echo "<td>";
                                echo "<button type='button' style='padding: 4px 8px; background: #0284c7; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 0.75rem;' onclick=\"cargarParaDerivar('" . htmlspecialchars($fila['numero_hoja_ruta']) . "')\">Atender y Derivar</button>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' style='text-align:center; color:#6c757d;'>No hay correspondencia pendiente en despacho.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </section>

            <!-- VISTA 2: ATENDER Y DERIVAR TRÁMITE (CON BUSCADOR Y PRIMER DESTINATARIO) -->
            <section id="vista-derivar" class="view-section">
                <div class="form-card">
                    
                    <!-- BUSCADOR DE HOJA DE RUTA -->
                    <h2 style="font-size: 1rem; color: #1b2a47; margin-bottom: 15px; font-weight: 700;">Buscar Hoja de Ruta para Atender</h2>
                    <div class="search-box-group">
                        <input type="number" id="input_buscar_hr" placeholder="Ingrese el N° de Hoja de Ruta (Ej. 1)...">
                        <button type="button" class="btn-official" onclick="buscarHojaRutaDirector()">Buscar</button>
                    </div>

                    <!-- FORMULARIO DE ATENCIÓN Y PRIMER DESTINATARIO (OCULTO HASTA ENCONTRAR) -->
                    <div id="contenedor_formulario_atencion" style="display: none; border-top: 2px dashed #ced4da; padding-top: 20px; margin-top: 10px;">
                        <form action="" method="POST">
                            <input type="hidden" name="accion" value="derivar">
                            
                            <div class="form-header-title">
                                <h2>HOJA DE RUTA - ATENCIÓN Y PRIMER DESTINATARIO</h2>
                                <div class="hr-number-box">
                                    <label>N° H.R.:</label>
                                    <input type="text" id="dir_numero_hr" name="numero_hoja_ruta" readonly required>
                                </div>
                            </div>

                            <!-- DATOS DE SECRETARÍA (SOLO LECTURA CARGADOS DE LA BD) -->
                            <div class="form-row">
                                <div class="form-group col-flex">
                                    <label>Procedencia:</label>
                                    <input type="text" id="dir_procedencia" class="readonly-field" readonly>
                                </div>
                                <div class="form-group col-medium">
                                    <label>Fecha Recepción:</label>
                                    <input type="text" id="dir_fecha" class="readonly-field" readonly>
                                </div>
                                <div class="form-group col-medium">
                                    <label>Hora Recepción:</label>
                                    <input type="text" id="dir_hora" class="readonly-field" readonly>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-flex">
                                    <label>Referencia / Asunto:</label>
                                    <textarea id="dir_referencia" class="readonly-field" readonly></textarea>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-flex">
                                    <label>Documentos Adjuntos (Originales):</label>
                                    <input type="text" id="dir_adjuntos_orig" class="readonly-field" readonly>
                                </div>
                                <div class="col-small">
                                    <div class="form-group">
                                        <label>Fojas:</label>
                                        <input type="text" id="dir_fojas" class="readonly-field" readonly>
                                    </div>
                                </div>
                                <div class="form-group col-flex">
                                    <label>Tipo de Correspondencia:</label>
                                    <input type="text" id="dir_tipo" class="readonly-field" readonly>
                                </div>
                            </div>

                            <!-- BLOQUE DEL PRIMER DESTINATARIO -->
                            <div class="section-bar">REGISTRO DEL PRIMER DESTINATARIO</div>

                            <div class="form-row">
                                <div class="form-group col-flex">
                                    <label>Derivar A / Responder A (Destinatario):</label>
                                    <select name="nombre" required>
                                        <option value="" disabled selected>-- Seleccione funcionario o destino --</option>
                                        <option value="Lic. Hugo Santiago - Auditor Supervisor">Lic. Hugo Santiago - Auditor Supervisor</option>
                                        <option value="Lic. Delia - Auditor Técnico">Lic. Delia - Auditor Técnico</option>
                                        <option value="Secretaría (Archivo de Antecedentes)">Secretaría (Archivo de Antecedentes)</option>
                                    </select>
                                </div>

                                <div class="form-group col-flex">
                                    <label>Docs. Adjuntos Entregados:</label>
                                    <input type="text" name="documentos_adjuntos" placeholder="Detalle de adjuntos entregados">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-flex">
                                    <label>Instrucciones / Informe Técnico de Respuesta:</label>
                                    <textarea name="instruccion" placeholder="Redacte la instrucción o derivación asignada..." required></textarea>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-flex">
                                    <label>Fecha de Salida:</label>
                                    <input type="date" name="fecha_salida" id="f_respuesta" required>
                                </div>

                                <div class="form-group col-flex">
                                    <label>Hora de Salida:</label>
                                    <input type="time" name="hora_salida" id="h_respuesta" required>
                                </div>

                                <div class="form-group col-flex">
                                    <label>Nuevo Estado del Trámite:</label>
                                    <select name="nuevo_estado" required>
                                        <option value="Derivado">Derivado</option>
                                        <option value="Concluido">Concluido</option>
                                        <option value="En Proceso">En Proceso</option>
                                    </select>
                                </div>
                            </div>

                            <button type="submit" class="btn-official">Guardar y Enviar Primer Destinatario</button>
                        </form>
                    </div>

                </div>
            </section>

        </main>
    </div>

    <!-- PIE DE PÁGINA -->
    <footer class="footer-bar">
        Gobierno Autónomo Departamental de Potosí &bull; Despacho de Dirección Departamental de Auditoría Interna &copy; 2026
    </footer>

    <!-- SCRIPT DE INTERACTIVIDAD Y AJAX -->
    <script>
        function cambiarVista(idVista, elemento) {
            document.querySelectorAll('.view-section').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.menu-item').forEach(m => m.classList.remove('active'));
            document.getElementById(idVista).classList.add('active');
            if(elemento) {
                elemento.classList.add('active');
            }
        }

        // Función para buscar la hoja de ruta mediante AJAX (usando obtener_hr.php)
        function buscarHojaRutaDirector() {
            const val = document.getElementById('input_buscar_hr').value.trim();
            if(val === "") {
                alert("Por favor, ingrese un número de Hoja de Ruta válido.");
                return;
            }

            fetch('obtener_hr.php?nro_hr=' + encodeURIComponent(val))
                .then(response => response.json())
                .then(data => {
                    if(data.error) {
                        alert(data.error);
                        document.getElementById('contenedor_formulario_atencion').style.display = 'none';
                    } else {
                        // Cargar datos en los campos de lectura del formato oficial
                        document.getElementById('dir_numero_hr').value = data.numero_hoja_ruta;
                        document.getElementById('dir_procedencia').value = data.procedencia;
                        document.getElementById('dir_fecha').value = data.fecha_recepcion;
                        document.getElementById('dir_hora').value = data.hora_recepcion;
                        document.getElementById('dir_referencia').value = data.referencia;
                        document.getElementById('dir_adjuntos_orig').value = data.documentos_adjuntos;
                        document.getElementById('dir_fojas').value = data.fojas;
                        document.getElementById('dir_tipo').value = data.tipo_correspondencia;
                        
                        // Mostrar el formulario de atención y primer destinatario
                        document.getElementById('contenedor_formulario_atencion').style.display = 'block';
                    }
                })
                .catch(err => {
                    console.error("Error en la petición:", err);
                    alert("Ocurrió un error al conectar con el servidor.");
                });
        }

        function cargarParaDerivar(numeroHR) {
            const itemMenuDerivar = document.querySelectorAll('.menu-item')[1];
            cambiarVista('vista-derivar', itemMenuDerivar);
            document.getElementById('input_buscar_hr').value = numeroHR;
            buscarHojaRutaDirector();
        }

        // Inicializar fecha y hora actual al cargar la página
        document.addEventListener("DOMContentLoaded", function() {
            const now = new Date();
            const fecha = now.toISOString().split('T')[0];
            const hora = now.toTimeString().split(' ')[0].substring(0, 5);
            
            if(document.getElementById('f_respuesta')) {
                document.getElementById('f_respuesta').value = fecha;
            }
            if(document.getElementById('h_respuesta')) {
                document.getElementById('h_respuesta').value = hora;
            }
        });
    </script>
</body>
</html>