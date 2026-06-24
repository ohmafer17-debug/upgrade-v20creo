<?php
// ==========================================
// BACKEND: usuarios_procesar.php (CORREGIDO)
// ==========================================

ini_set('display_errors', 0);
error_reporting(0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// Conexión a la base de datos de Diego
require_once "conexion.php"; 

$inputRaw = file_get_contents("php://input");
$datos = json_decode($inputRaw, true);

if (!$datos || !isset($datos['accion'])) {
    echo json_encode(["status" => "error", "message" => "Petición inválida."]);
    exit;
}

$accion = $datos['accion'];

if ($accion === 'registrar_usuario_rol') {
    // Capturamos las variables enviadas de forma segura
    $rol_creador = $conexion->real_escape_string(trim($datos['rol_creador'])); 
    $nombre = $conexion->real_escape_string(trim($datos['nombre']));
    $email = $conexion->real_escape_string(trim($datos['email']));
    $rol_a_crear = $conexion->real_escape_string(trim($datos['rol'])); 
    $pass = $conexion->real_escape_string(trim($datos['pass'])); // Nota: Si usan hash, Ángel lo aplicará aquí

    if (empty($nombre) || empty($email) || empty($rol_a_crear) || empty($pass)) {
        echo json_encode(["status" => "error", "message" => "Hay campos obligatorios vacíos."]);
        exit;
    }

    $autorizado = false;

    // 🚨 REGLA DE ORO DE JERARQUÍAS (Sintonía estricta con el HTML)
    if (strcasecmp($rol_creador, 'administrador') == 0) {
        $autorizado = true; // El administrador absoluto puede crear lo que sea
    } 
    elseif (strcasecmp($rol_creador, 'consultor') == 0) {
        // 🔥 EL CONSULTOR TIENE PERMITIDO CREAR ESTOS 4 ROLES EXCLUSIVAMENTE
        if (in_array($rol_a_crear, ['Responsable Nacional', 'Tipo 1', 'Tipo 2', 'Tipo 3'])) {
            $autorizado = true;
        }
    } 
    elseif (strcasecmp($rol_creador, 'tipo 1') == 0) {
        // El Tipo 1 solo puede replicarse o crear inferiores
        if (in_array($rol_a_crear, ['Tipo 1', 'Tipo 2', 'Tipo 3'])) {
            $autorizado = true;
        }
    }

    // Si alguien intenta manipular el sistema (por ejemplo desde la consola F12)
    if (!$autorizado) {
        echo json_encode([
            "status" => "error", 
            "message" => "Tu rango de [" . strtoupper($rol_creador) . "] no tiene permisos para asignar el rol de [" . $rol_a_crear . "]."
        ]);
        exit;
    }

    // Validar que el correo electrónico no esté repetido en la tabla usuarios_clientes
    $checkEmail = $conexion->query("SELECT id FROM usuarios_clientes WHERE email = '$email' LIMIT 1");
    if ($checkEmail && $checkEmail->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Este correo electrónico ya se encuentra registrado en el sistema."]);
        exit;
    }

    // Inyección limpia en la base de datos
    $queryInsert = "INSERT INTO usuarios_clientes (nombre, email, pass, rol, fecha_registro) 
                    VALUES ('$nombre', '$email', '$pass', '$rol_a_crear', NOW())";

    if ($conexion->query($queryInsert)) {
        echo json_encode(["status" => "success", "message" => "¡Nodo registrado exitosamente!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error interno en la base de datos: " . $conexion->error]);
    }
    exit;
}

echo json_encode(["status" => "error", "message" => "Acción no reconocida por el controlador."]);
exit;
?>