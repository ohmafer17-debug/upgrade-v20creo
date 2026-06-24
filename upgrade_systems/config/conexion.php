<?php
// =================================================================
// CONEXIÓN GLOBAL: conexion.php (NÚCLEO CON PUERTO REAL 3307)
// =================================================================

$servidor   = "localhost";
$usuario    = "root";
$password   = ""; 
$base_datos = "upgrade_systems_db"; 
$puerto     = 3307; // 🚀 INDISPENSABLE: Sincronizado con su puerto de XAMPP

// Pasamos el parámetro del puerto al final de la consulta
$conexion = new mysqli($servidor, $usuario, $password, $base_datos, $puerto);

if ($conexion->connect_error) {
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode([
        "status" => "error", 
        "message" => "Falla de enlace con el servidor local MySQL: " . $conexion->connect_error
    ]);
    exit;
}

$conexion->set_charset("utf8");
?>