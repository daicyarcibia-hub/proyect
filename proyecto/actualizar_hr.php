<?php
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_solicitud = intval($_POST['id_solicitud']);
    $procedencia = trim($_POST['procedencia']);
    $fecha_recepcion = trim($_POST['fecha_recepcion']);
    $hora_recepcion = trim($_POST['hora_recepcion']);
    $referencia = trim($_POST['referencia']);
    $documentos_adjuntos = trim($_POST['documentos_adjuntos']);
    $fojas = intval($_POST['fojas']);
    $tipo_correspondencia = trim($_POST['tipo_correspondencia']);

    if (empty($id_solicitud)) {
        die("Error: No se especificó el identificador de la solicitud a actualizar.");
    }

    $sql = "UPDATE solicitud 
            SET procedencia = ?, 
                fecha_recepcion = ?, 
                hora_recepcion = ?, 
                referencia = ?, 
                documentos_adjuntos = ?, 
                fojas = ?, 
                tipo_correspondencia = ? 
            WHERE id = ?";

    if ($stmt = $conexion->prepare($sql)) {
        $stmt->bind_param("sssssisi", $procedencia, $fecha_recepcion, $hora_recepcion, $referencia, $documentos_adjuntos, $fojas, $tipo_correspondencia, $id_solicitud);
        
        if ($stmt->execute()) {
            echo "<script>
                    alert('Los cambios de la Hoja de Ruta han sido guardados exitosamente.');
                    window.location.href = 'secretaria.php';
                  </script>";
            exit();
        } else {
            echo "Error al actualizar los datos: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "Error en la preparación de la consulta: " . $conexion->error;
    }

    $conexion->close();
    exit();
} else {
    header("Location: secretaria.php");
    exit();
}