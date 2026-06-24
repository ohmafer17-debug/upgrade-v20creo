<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once "conexion.php";

// Recibir el JSON desde el HTML con el código de la empresa
$inputRaw = file_get_contents("php://input");
$datosRecibidos = json_decode($inputRaw, true);

if (!isset($datosRecibidos['empresa_cod'])) {
    echo json_encode(["status" => "error", "message" => "Falta el código de la empresa."]);
    exit;
}

$empresa_cod = $conexion->real_escape_string(trim($datosRecibidos['empresa_cod']));
$fecha_hoy = date('Y-m-d');

// Consultar los documentos de la sucursal
$query = "SELECT id, nombre, archivo_path, fecha_subida, fecha_vencimiento 
          FROM documentos 
          WHERE empresa_cod = '$empresa_cod' AND activo = 1 
          ORDER BY fecha_vencimiento ASC";

$resultado = $conexion->query($query);

if ($resultado) {
    $listaDocumentos = [];
    
    while ($doc = $resultado->fetch_assoc()) {
        $vencimiento = $doc['fecha_vencimiento'];
        // Calcular la diferencia de días exactos
        $diff = (strtotime($vencimiento) - strtotime($fecha_hoy)) / 86400;
        
        // Determinar el estado y el color del semáforo individual
        if ($diff <= 0) {
            $semaforo = "Vencido";
            $color = "red";
        } elseif ($diff > 0 && $diff <= 30) {
            $semaforo = "Próximo a vencer";
            $color = "yellow";
        } else {
            $semaforo = "Vigente";
            $color = "green";
        }
        
        $listaDocumentos[] = [
            "id" => $doc['id'],
            "nombre" => $doc['nombre'],
            "fecha_subida" => $doc['fecha_subida'],
            "fecha_vencimiento" => $vencimiento,
            "archivo_path" => $doc['archivo_path'],
            "semaforo" => $semaforo,
            "color" => $color
        ];
    }
    
    echo json_encode([
        "status" => "success",
        "documentos" => $listaDocumentos
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Error al obtener documentos: " . $conexion->error]);
}
?>