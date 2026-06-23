<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once "conexion.php";

$inputRaw = file_get_contents("php://input");
$data = json_decode($inputRaw, true);

if (!isset($data['nombre']) || !isset($data['email']) || !isset($data['pass']) || !isset($data['rol']) || !isset($data['empresa_cod'])) {
    echo json_encode(["status" => "error", "message" => "Todos los campos son obligatorios para el registro."]);
    exit;
}

$nombre = $conexion->real_escape_string(trim($data['nombre']));
$email = $conexion->real_escape_string(trim($data['email']));
$pass = $conexion->real_escape_string(trim($data['pass']));
$rol = $conexion->real_escape_string(trim($data['rol'])); // Responsable Nacional, Tipo 1, Tipo 2, Tipo 3
$empresa_cod = $conexion->real_escape_string(trim($data['empresa_cod']));

// Verificar que el correo no esté repetido
$checkEmail = $conexion->query("SELECT id FROM usuarios_clientes WHERE email = '$email'");
if ($checkEmail->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Este correo electrónico ya está registrado en el sistema."]);
    exit;
}

// Insertar el nuevo miembro del personal con el rol seleccionado
$query = "INSERT INTO usuarios_clientes (nombre, email, pass, rol, status, empresa_cod) 
          VALUES ('$nombre', '$email', '$pass', '$rol', 1, '$empresa_cod')";

if ($conexion->query($query)) {
    echo json_encode(["status" => "success", "message" => "¡Colaborador registrado exitosamente en tu organización!"]);
} else {
    echo json_encode(["status" => "error", "message" => "Error interno en la base de datos: " . $conexion->error]);
}
?>