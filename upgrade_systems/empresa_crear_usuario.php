<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once "conexion.php";

$inputRaw = file_get_contents("php://input");
$data = json_decode($inputRaw, true);

if (!isset($data['nombre']) || !isset($data['email']) || !isset($data['pass']) || !isset($data['rol']) || !isset($data['empresa_cod'])) {
    echo json_encode(["status" => "error", "message" => "Todos los campos obligatorios para el registro no fueron completados."]);
    exit;
}

$nombre            = $conexion->real_escape_string(trim($data['nombre']));
$email             = $conexion->real_escape_string(trim($data['email']));
$email_personal    = $conexion->real_escape_string(trim($data['email_personal']));
$telefono_empresa  = $conexion->real_escape_string(trim($data['telefono_empresa']));
$telefono_personal = $conexion->real_escape_string(trim($data['telefono_personal']));
$pass              = $conexion->real_escape_string(trim($data['pass']));
$rol               = $conexion->real_escape_string(trim($data['rol'])); 
$empresa_cod       = $conexion->real_escape_string(trim($data['empresa_cod']));

// Verificar duplicidad de login
$checkEmail = $conexion->query("SELECT id FROM usuarios_clientes WHERE email = '$email'");
if ($checkEmail->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Este correo electrónico corporativo ya está registrado en el sistema."]);
    exit;
}

// 🚀 INSERCIÓN EXTENDIDA COMPLETA CON COLUMNAS COMPLEMENTARIAS
$query = "INSERT INTO usuarios_clientes (nombre, email, email_personal, telefono_empresa, telefono_personal, pass, rol, status, empresa_cod) 
          VALUES ('$nombre', '$email', '$email_personal', '$telefono_empresa', '$telefono_personal', '$pass', '$rol', 1, '$empresa_cod')";

if ($conexion->query($query)) {
    echo json_encode(["status" => "success", "message" => "¡Colaborador registrado exitosamente en tu organización con sus perfiles de contacto!"]);
} else {
    echo json_encode(["status" => "error", "message" => "Error interno en la base de datos: " . $conexion->error]);
}
?>