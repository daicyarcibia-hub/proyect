<?php
session_start();

// 1. CABECERAS ANTI-CACHÉ (Evitan que el navegador guarde la página en el historial)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once 'conexion.php';

// 2. VALIDAR SI EL USUARIO NO HA INICIADO SESIÓN
// Si no existe la variable de sesión 'usuario' o 'rol', lo expulsamos al login inmediatamente.
if (!isset($_SESSION['usuario']) || !isset($_SESSION['rol'])) {
    header("Location: login_process.php");
    exit(); // Detiene la ejecución para que no cargue la página de auditoría
}
 if (isset($cerrar)) {
        $cerrar-> session_destroy();
    }
// 3. BLOQUE DE SEGURIDAD POR ROL (Solo permite 'auditor' o 'tecnico')
$rol_actual = strtolower(trim($_SESSION['rol']));
if ($rol_actual !== 'auditor' && $rol_actual !== 'tecnico') {
    echo "<script>
            alert('Acceso denegado: Su usuario tiene el rol [ " . htmlspecialchars($rol_actual) . " ] y no cuenta con permisos para ingresar al Módulo de Auditoría.');
            window.location.href = 'login_process.php';
          </script>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo de Auditores - GADP Potosí</title>
    <!-- Tipografía formal y limpia -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Enlace a la hoja de estilos separada -->
    <link rel="stylesheet" href="css/auditor.css">
</head>
<body>

    <header class="top-navbar">
        <div class="brand-logo">
            <h1>GOBIERNO AUTÓNOMO DEPARTAMENTAL DE POTOSÍ</h1>
        </div>
        <div class="user-profile">
            <div class="user-info">
                <div class="name"><?php echo htmlspecialchars($_SESSION['usuario']); ?></div>
                <div class="role"><?php echo htmlspecialchars(ucfirst($_SESSION['rol'])); ?> / Auditoría Interna</div>
            </div>
            <a href="<?php $cerrar?>" class="btn-logout">Salir</a>
        </div>
    </header>

    <div class="main-layout">
        <!-- MENÚ LATERAL DEL AUDITOR -->
        <aside class="sidebar">
            <div class="sidebar-title">Módulo Auditoría</div>
            <a class="menu-item active" onclick="cambiarVista('vista-bandeja', this)">Trámites Asignados (Pendientes)</a>
            <a class="menu-item" onclick="cambiarVista('vista-atender', this)">Atender y Responder Trámite</a>
            <a class="menu-item" onclick="cambiarVista('vista-historial', this)">Historial de Respondidos</a>
        </aside>

        <main class="content-area">
            <!-- VISTA 1: BANDEJA -->
            <section id="vista-bandeja" class="view-section active">
                <h2 style="font-size: 1.15rem; color: #1b2a47; margin-bottom: 20px; font-weight: 700;">Bandeja de Trámites Asignados</h2>
                <table class="table-official">
                    <thead>
                        <tr>
                            <th>N° Hoja de Ruta</th>
                            <th>Procedencia / Remitente</th>
                            <th>Instrucción Recibida</th>
                            <th>Prioridad</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Consulta para mostrar los registros reales de destinatarios
                        $sql_audit = "SELECT d.id_destinatario, d.numero_hoja_ruta, d.nombre, d.documentos_adjuntos, d.instruccion, d.fecha_salida 
                                      FROM destinatarios d 
                                      ORDER BY d.fecha_salida DESC";
                        $resultado_audit = $conexion->query($sql_audit);

                        if ($resultado_audit && $resultado_audit->num_rows > 0) {
                            while ($fila = $resultado_audit->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td><b>" . htmlspecialchars($fila['numero_hoja_ruta']) . "</b></td>";
                                echo "<td>" . htmlspecialchars($fila['nombre']) . "</td>";
                                echo "<td>" . htmlspecialchars($fila['instruccion']) . "</td>";
                                echo "<td><span class=\"badge-urgente\">URGENTE</span></td>";
                                echo "<td><span class=\"badge-pendiente\">Pendiente</span></td>";
                                echo "<td>";
                                echo "<button type='button' style='padding: 4px 10px; background: #1b2a47; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 0.78rem;' onclick=\"abrirAtencion('" . htmlspecialchars($fila['numero_hoja_ruta']) . "')\">Atender</button>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' style='text-align:center; color:#6c757d;'>No hay trámites asignados en este momento.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </section>

            <!-- VISTA 2: ATENDER Y RESPONDER TRÁMITE -->
            <section id="vista-atender" class="view-section">
                <div class="form-card">
                    <form action="guardar_respuesta_auditor.php" method="POST">
                        <div class="form-header-title">
                            <h2>GOBIERNO AUTÓNOMO DEPARTAMENTAL DE POTOSÍ</h2>
                            <div class="hr-number-box">
                                <label>N° H.R.:</label>
                                <input type="text" id="form_num_hr" name="numero_hoja_ruta" value="2026-001" readonly>
                            </div>
                        </div>

                        <!-- BLOQUE HISTORIAL COMPLETO (SECRETARÍA + DIRECCIÓN + PDF ORIGINAL) -->
                        <div id="bloque-historial-completo">
                            <!-- Datos de Secretaría -->
                            <div class="form-row">
                                <div class="form-group col-flex">
                                    <label>PROCEDENCIA:</label>
                                    <input type="text" class="readonly-field" value="Secretaría Departamental de Obras Públicas" readonly>
                                </div>
                                <div class="form-group col-medium">
                                    <label>FECHA:</label>
                                    <input type="text" class="readonly-field" value="29/10/2026" readonly>
                                </div>
                                <div class="form-group col-medium">
                                    <label>HORA:</label>
                                    <input type="text" class="readonly-field" value="09:30" readonly>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-flex">
                                    <label>REFERENCIA:</label>
                                    <textarea class="readonly-field" readonly>Remisión de informe de avance financiero de obras públicas departamentales.</textarea>
                                </div>
                            </div>

                            <!-- VISOR DEL PDF SUBIDO POR SECRETARÍA -->
                            <div class="form-row">
                                <div class="form-group col-flex">
                                    <label>DOCUMENTO DIGITALIZADO (ORIGINAL):</label>
                                    <div class="pdf-viewer-box">
                                        <span>📎</span> <a href="#" target="_blank" style="color: #0d9488; font-weight: 700; text-decoration: none;">Ver_Documento_Secretaria_2026-001.pdf</a>
                                    </div>
                                </div>
                            </div>

                            <!-- Primer Destinatario (Director) -->
                            <div class="section-bar">PRIMER DESTINATARIO (DIRECCIÓN)</div>
                            <div class="form-row">
                                <div class="form-group col-flex">
                                    <label>DESTINATARIO:</label>
                                    <input type="text" class="readonly-field" value="Lic. Hugo Santiago - Auditor Supervisor" readonly>
                                </div>
                                <div class="form-group col-flex">
                                    <label>DOCS. ADJUNTOS:</label>
                                    <input type="text" class="readonly-field" value="Ninguno" readonly>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-flex">
                                    <label>ACCIÓN / INSTRUCCIÓN PRINCIPAL:</label>
                                    <input type="text" class="readonly-field" value="Elaborar informe técnico / legal" readonly>
                                </div>
                            </div>
                        </div>

                        <!-- BLOQUE 2: SEGUNDO DESTINATARIO (ACCIÓN DEL AUDITOR) -->
                        <div id="bloque-segundo-destinatario">
                            <div class="section-bar">SEGUNDO DESTINATARIO (AUDITORÍA / TÉCNICO)</div>

                            <div class="form-row">
                                <div class="form-group col-flex">
                                    <label>DERIVAR A / RESPONDER A:</label>
                                    <select name="destino_respuesta" required>
                                        <option value="" disabled selected>-- Seleccione destino de respuesta --</option>
                                        <option value="1">Director Departamental - Lic. Willy Cueto</option>
                                        <option value="2">Secretaría (Archivo de Antecedentes)</option>
                                    </select>
                                </div>
                                <div class="form-group col-flex">
                                    <label>DOCS. ADJUNTOS:</label>
                                    <input type="text" name="documentos_adjuntos_respuesta" placeholder="Detalle de adjuntos entregados">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-flex">
                                    <label>INSTRUCCIONES / INFORME TÉCNICO DE RESPUESTA:</label>
                                    <textarea name="instruccion_respuesta" placeholder="Redacte el informe o respuesta del trámite asignado..." required></textarea>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-flex">
                                    <label>FECHA DE SALIDA:</label>
                                    <input type="date" name="fecha_salida" id="f_respuesta" required>
                                </div>
                                <div class="form-group col-flex">
                                    <label>HORA DE SALIDA:</label>
                                    <input type="time" name="hora_salida" id="h_respuesta" required>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn-official">Guardar y Enviar Respuesta</button>
                    </form>

                    <!-- OPCIONES DE IMPRESIÓN -->
                    <div class="btn-print-group">
                        <button type="button" class="btn-print" onclick="window.print()">🖨️ Imprimir Hoja Completa</button>
                        <button type="button" class="btn-print" style="background-color: #1b2a47;" onclick="imprimirSoloRespuestaAlineada()">🖨️ Imprimir Solo Respuesta</button>
                    </div>
                </div>
            </section>

            <!-- VISTA 3: HISTORIAL -->
            <section id="vista-historial" class="view-section">
                <h2 style="font-size: 1.15rem; color: #1b2a47; margin-bottom: 15px; font-weight: 700;">Historial de Trámites Atendidos</h2>
                <table class="table-official">
                    <thead>
                        <tr>
                            <th>N° Hoja de Ruta</th>
                            <th>Referencia</th>
                            <th>Fecha de Respuesta</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><b>2026-001</b></td>
                            <td>Remisión de informe financiero...</td>
                            <td>29/10/2026 16:20</td>
                            <td><span style="color: #059669; font-weight: 700;">Respondido</span></td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </main>
    </div>

    <footer class="footer-bar">
        Gobierno Autónomo Departamental de Potosí &bull; Dirección Departamental de Auditoría Interna &copy; 2026
    </footer>

    <script>
        function cambiarVista(idVista, elemento) {
            document.querySelectorAll('.view-section').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.menu-item').forEach(m => m.classList.remove('active'));
            document.getElementById(idVista).classList.add('active');
            if(elemento) elemento.classList.add('active');
        }

        function abrirAtencion(numHR) {
            cambiarVista('vista-atender', document.querySelectorAll('.menu-item')[1]);
            document.getElementById('form_num_hr').value = numHR;
        }

        function imprimirSoloRespuestaAlineada() {
            document.body.classList.add('print-solo-respuesta');
            window.print();
            document.body.classList.remove('print-solo-respuesta');
        }

        document.addEventListener("DOMContentLoaded", function() {
            const now = new Date();
            const fecha = now.toISOString().split('T')[0];
            const hora = now.toTimeString().split(' ')[0].substring(0, 5);
            if(document.getElementById('f_respuesta')) {
                document.getElementById('f_respuesta').value = fecha;
                document.getElementById('h_respuesta').value = hora;
            }
        });
    </script>
</body>
</html>