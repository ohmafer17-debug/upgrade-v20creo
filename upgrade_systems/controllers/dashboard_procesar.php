<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once "conexion.php";

// Recibir el JSON del Frontend con el codigo de la empresa (Ej: WAL-TOL)
$inputRaw = file_get_contents("php://input");
$datosRecibidos = json_decode($inputRaw, true);

if (!isset($datosRecibidos['empresa_cod'])) {
    echo json_encode(["status" => "error", "message" => "Falta el código de la empresa."]);
    exit;
}

$empresa_cod = $conexion->real_escape_string(trim($datosRecibidos['empresa_cod']));

// 1. Inicializar los contadores en cero
$rojo = 0;
$amarillo = 0;
$verde = 0;

// 2. Obtener la fecha de hoy en el servidor (Año 2026)
$fecha_hoy = date('Y-m-d');

// 3. Traer todos los documentos activos de esta empresa específica
$query = "SELECT fecha_vencimiento FROM documentos WHERE empresa_cod = '$empresa_cod' AND activo = 1";
$resultado = $conexion->query($query);

if ($resultado) {
    while ($doc = $resultado->fetch_assoc()) {
        $vencimiento = $doc['fecha_vencimiento'];
        
        // Calcular la diferencia de días entre hoy y la fecha de vencimiento
        $diff = (strtotime($vencimiento) - strtotime($fecha_hoy)) / 86400; // 86400 segundos tiene un día

        if ($diff <= 0) {
            $rojo++; // Ya venció o vence hoy
        } elseif ($diff > 0 && $diff <= 30) {
            $amarillo++; // Vence en los próximos 30 días
        } else {
            $verde++; // Está vigente y seguro
        }
    }
    
    echo json_encode([
        "status" => "success",
        "contadores" => [
            "rojo" => $rojo,
            "amarillo" => $amarillo,
            "verde" => $verde,
            "total" => $resultado->num_rows
        ]
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Error al consultar documentos: " . $conexion->error]);
}
?>