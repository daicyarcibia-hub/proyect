<?php
include 'conexion.php';

header('Content-Type: application/json');

if (isset($_GET['nro_hr'])) {
    $nro_hr = intval($_GET['nro_hr']);
    
    // 1. Consulta mediante INNER JOIN entre solicitud y hoja_ruta
    $sql = "SELECT hr.numero_hoja_ruta, s.id AS id_solicitud, s.procedencia, s.fecha_recepcion, s.hora_recepcion, 
                   s.referencia, s.documentos_adjuntos, s.fojas, s.tipo_correspondencia, s.estado, s.documento_pdf
            FROM hoja_ruta hr
            INNER JOIN solicitud s ON hr.id_solicitud = s.id
            WHERE hr.numero_hoja_ruta = ? LIMIT 1";
    
    if ($stmt = $conexion->prepare($sql)) {
        $stmt->bind_param("i", $nro_hr);
        $stmt->execute();
        $resultado = $stmt->get_result();
        
        if ($resultado->num_rows > 0) {
            $datos = $resultado->fetch_assoc();
            $stmt->close();

            // 2. Obtener la lista de todos los destinatarios asignados
            $sql_dest = "SELECT nombre, documentos_adjuntos, instruccion, fecha_salida, hora_salida 
                         FROM destinatarios 
                         WHERE numero_hoja_ruta = ? 
                         ORDER BY id_destinatario ASC";
            
            if ($stmt_dest = $conexion->prepare($sql_dest)) {
                $stmt_dest->bind_param("i", $nro_hr);
                $stmt_dest->execute();
                $res_dest = $stmt_dest->get_result();
                
                $destinatarios = [];
                while ($fila_dest = $res_dest->fetch_assoc()) {
                    $destinatarios[] = $fila_dest;
                }
                $stmt_dest->close();

                // Adjuntar la lista al objeto de respuesta
                $datos['destinatarios'] = $destinatarios;
            } else {
                $datos['destinatarios'] = [];
            }

            echo json_encode($datos);
        } else {
            $stmt->close();
            echo json_encode(["error" => "No se encontró ninguna Hoja de Ruta con el número proporcionado."]);
        }
    } else {
        echo json_encode(["error" => "Error interno en la consulta del servidor."]);
    }
} else {
    echo json_encode(["error" => "Petición no válida."]);
}

$conexion->close();
exit();