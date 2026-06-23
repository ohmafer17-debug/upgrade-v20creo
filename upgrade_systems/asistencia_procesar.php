<?php
// =================================================================
// ASISTENCIA Y REPORTES DE SINIESTROS: asistencia_procesar.php
// =================================================================
ini_set('display_errors', 0);
error_reporting(0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once "conexion.php";

// Sincronizado con la acción del formulario: reportar_sismo_emergencia
if (isset($_POST['accion']) && $_POST['accion'] === 'reportar_sismo_emergencia') {
    $empresa       = $conexion->real_escape_string($_POST['empresa_cod']);
    $zona_afectada = $conexion->real_escape_string($_POST['zona_afectada']);
    $clasificacion = $conexion->real_escape_string($_POST['clasificacion']); // 🚀 NUEVO: Mueble/Inmueble
    $comentarios   = $conexion->real_escape_string($_POST['comentarios']);
    $lat           = $conexion->real_escape_string($_POST['latitud']);
    $lng           = $conexion->real_escape_string($_POST['longitud']);

    $dest_path = "./uploads_sismos/";
    if (!is_dir($dest_path)) { mkdir($dest_path, 0777, true); }

    if (isset($_FILES['evidencia']) && $_FILES['evidencia']['error'] === UPLOAD_ERR_OK) {
        $fileName = $_FILES['evidencia']['name'];
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        
        // Formamos un nombre único y seguro para el archivo de evidencia
        $nuevo_nombre = "SISMO_" . $empresa . "_" . time() . "." . $ext;
        
        if (move_uploaded_file($_FILES['evidencia']['tmp_name'], $dest_path . $nuevo_nombre)) {
            
            // 🚀 INSERT ACTUALIZADO: Registra la clasificación del daño y comentarios
            $query = "INSERT INTO reportes_sismo (empresa_cod, zona, clasificacion, descripcion, ruta_archivo, latitud, longitud) 
                      VALUES ('$empresa', '$zona_afectada', '$clasificacion', '$comentarios', '$nuevo_nombre', '$lat', '$lng')";
            
            if ($conexion->query($query)) {
                echo json_encode(["status" => "success", "message" => "¡Reporte de siniestro levantado exitosamente con ubicación GPS!"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Error al guardar en la base de datos: " . $conexion->error]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "No se pudo mover el archivo de evidencia al servidor."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Archivo de evidencia no recibido o dañado."]);
    }
    exit;
}
?>