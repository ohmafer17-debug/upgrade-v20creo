<?php
// =================================================================
// PORTAL GLOBAL: login_procesar.php (VERSIÓN COMPATIBILIDAD INTEGRAL)
// =================================================================

ini_set('display_errors', 0);
error_reporting(0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once "conexion.php"; 

$inputRaw = file_get_contents("php://input");
$datos = json_decode($inputRaw, true);

$email_post = isset($datos['email']) ? trim($datos['email']) : '';
$pass_post  = isset($datos['pass']) ? trim($datos['pass']) : '';

// Mensaje unificado de seguridad antihackers
$error_credenciales = "El correo electrónico o la contraseña introducida son incorrectos.";

if (empty($email_post) || empty($pass_post)) {
    echo json_encode(["status" => "error", "message" => "Por favor, introduce tu correo y contraseña."]);
    exit;
}

// =================================================================
// PASO 1: VERIFICAR STAFF INTERNO DE UPS (SENTENCIAS PREPARADAS)
// =================================================================
$stmtAdmin = $conexion->prepare("SELECT id, nombre, email, pass, estatus FROM admin_ups WHERE email = ? LIMIT 1");
$stmtAdmin->bind_param("s", $email_post);
$stmtAdmin->execute();
$resAdmin = $stmtAdmin->get_result();

if ($resAdmin && $resAdmin->num_rows > 0) {
    $rowAdmin = $resAdmin->fetch_assoc();
    
    if (isset($rowAdmin['estatus']) && strcasecmp($rowAdmin['estatus'], 'Activo') != 0) {
        echo json_encode(["status" => "error", "message" => "Tu acceso como Administrador UPS se encuentra suspendido."]);
        exit;
    }

    // Doble validación (Soporta hash bcrypt nuevo y texto plano para pruebas locales)
    if (password_verify($pass_post, $rowAdmin['pass']) || $pass_post === $rowAdmin['pass']) {
        echo json_encode([
            "status" => "success",
            "message" => "¡Bienvenido a la Consola de Administración UPS!",
            "id_cliente"     => "UPS-STAFF",
            "nombre_usuario" => $rowAdmin['nombre'],
            "rol_usuario"    => "Administrador",
            "data" => [
                "cod"    => "UPS-STAFF",
                "nombre" => $rowAdmin['nombre'],
                "email"  => $rowAdmin['email'],
                "rol"    => "Administrador"
            ]
        ]);
        exit;
    } else {
        echo json_encode(["status" => "error", "message" => $error_credenciales]);
        exit;
    }
}
$stmtAdmin->close();

// =================================================================
// PASO 2: VERIFICAR EMPRESAS CLIENTES / SUCURSALES (SENTENCIAS PREPARADAS)
// =================================================================
$stmtCliente = $conexion->prepare("SELECT cod, nombre, email, pass, rol, activo FROM empresas_clientes WHERE email = ? LIMIT 1");
$stmtCliente->bind_param("s", $email_post);
$stmtCliente->execute();
$resCliente = $stmtCliente->get_result();

if ($resCliente && $resCliente->num_rows > 0) {
    $rowCliente = $resCliente->fetch_assoc();
    
    if (isset($rowCliente['activo']) && (int)$rowCliente['activo'] === 0) {
        echo json_encode(["status" => "error", "message" => "Esta licencia corporativa se encuentra suspendida por la gerencia de UPS."]);
        exit;
    }

    // Doble validación criptográfica (Soporta hash bcrypt y texto plano temporal)
    if (password_verify($pass_post, $rowCliente['pass']) || $pass_post === $rowCliente['pass']) {
        $rol_real_bd = isset($rowCliente['rol']) ? $rowCliente['rol'] : 'Consultor';
        
        echo json_encode([
            "status" => "success",
            "message" => "¡Acceso autorizado al portal corporativo!",
            "id_cliente"     => $rowCliente['cod'],
            "nombre_usuario" => $rowCliente['nombre'],
            "rol_usuario"    => $rol_real_bd,
            "data" => [
                "cod"    => $rowCliente['cod'],
                "nombre" => $rowCliente['nombre'],
                "email"  => $rowCliente['email'],
                "rol"    => $rol_real_bd
            ]
        ]);
        exit;
    } else {
        echo json_encode(["status" => "error", "message" => $error_credenciales]);
        exit;
    }
}
$stmtCliente->close();

echo json_encode(["status" => "error", "message" => $error_credenciales]);
exit;
?>