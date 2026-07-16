<?php
// =================================================================
// CONTROLADOR DE RECUPERACIÓN DE CONTRASEÑA: recuperar_procesar.php
// =================================================================

ini_set('display_errors', 0);
error_reporting(0);

// Iniciamos sesión para guardar los códigos de validación
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . "/../config/conexion.php";

$inputRaw = file_get_contents("php://input");
$datos = json_decode($inputRaw, true);

$accion = isset($datos['accion']) ? trim($datos['accion']) : '';

if (empty($accion)) {
    echo json_encode(["status" => "error", "message" => "Acción no especificada."]);
    exit;
}

// -----------------------------------------------------------------
// ACCIÓN 1: SOLICITAR CÓDIGO DE RECUPERACIÓN
// -----------------------------------------------------------------
if ($accion === 'solicitar_codigo') {
    $email = isset($datos['email']) ? $conexion->real_escape_string(trim($datos['email'])) : '';

    if (empty($email)) {
        echo json_encode(["status" => "error", "message" => "El correo electrónico es requerido."]);
        exit;
    }

    $cuenta_encontrada = false;
    $nombre_usuario = "";
    $tipo_cuenta = "";

    // 1. Verificar si el correo pertenece a admin_ups (Administrador Staff)
    $queryAdmin = "SELECT id, nombre FROM admin_ups WHERE email = ? AND estatus = 'Activo' LIMIT 1";
    $stmtAdmin = $conexion->prepare($queryAdmin);
    $stmtAdmin->bind_param("s", $email);
    $stmtAdmin->execute();
    $resAdmin = $stmtAdmin->get_result();

    if ($resAdmin && $resAdmin->num_rows > 0) {
        $rowAdmin = $resAdmin->fetch_assoc();
        $cuenta_encontrada = true;
        $nombre_usuario = $rowAdmin['nombre'];
        $tipo_cuenta = "admin_ups";
    }
    $stmtAdmin->close();

    // 2. Si no es admin, verificar si pertenece a empresas_clientes (Licencias/Nodos de clientes)
    if (!$cuenta_encontrada) {
        $queryCliente = "SELECT id, nombre FROM empresas_clientes WHERE email = ? AND activo = 1 LIMIT 1";
        $stmtCliente = $conexion->prepare($queryCliente);
        $stmtCliente->bind_param("s", $email);
        $stmtCliente->execute();
        $resCliente = $stmtCliente->get_result();

        if ($resCliente && $resCliente->num_rows > 0) {
            $rowCliente = $resCliente->fetch_assoc();
            $cuenta_encontrada = true;
            $nombre_usuario = $rowCliente['nombre'];
            $tipo_cuenta = "empresas_clientes";
        }
        $stmtCliente->close();
    }

    if ($cuenta_encontrada) {
        // Generar un código temporal de 6 dígitos
        $codigo = strval(rand(100000, 999999));
        
        // Almacenar en la sesión
        $_SESSION['rec_email'] = $email;
        $_SESSION['rec_codigo'] = $codigo;
        $_SESSION['rec_expira'] = time() + 600; // Expira en 10 minutos
        $_SESSION['rec_verificado'] = false;
        $_SESSION['rec_tipo_cuenta'] = $tipo_cuenta;

        /*
        // =================================================================
        // BLOQUE DE PRODUCCIÓN (SMTP / ENVÍO DE CORREO REAL)
        // =================================================================
        // Cuando subas este sistema a tu hosting real, descomenta este bloque para enviar el correo real:
        
        $para = $email;
        $asunto = "Código de Recuperación - Upgrade Systems";
        $mensaje = "Hola " . $nombre_usuario . ",\n\n" .
                   "Hemos recibido una solicitud para restablecer la contraseña de tu cuenta.\n" .
                   "Tu código de validación temporal es: " . $codigo . "\n\n" .
                   "Este código expirará en 10 minutos.\n" .
                   "Si no solicitaste este cambio, puedes ignorar este correo de forma segura.\n\n" .
                   "Atentamente,\nUpgrade Systems Support Team";
        $cabeceras = "From: soporte@upgradesystems.com\r\n" .
                     "Reply-To: soporte@upgradesystems.com\r\n" .
                     "X-Mailer: PHP/" . phpversion();
        
        mail($para, $asunto, $mensaje, $cabeceras);
        // =================================================================
        */

        // Devolvemos éxito. En desarrollo local enviamos el código simulado en el JSON
        echo json_encode([
            "status" => "success", 
            "message" => "Código de validación generado con éxito.",
            "codigo_simulado" => $codigo // 🚀 Esto permite que el JS local lo capture y lo muestre en una alerta
        ]);

    } else {
        echo json_encode(["status" => "error", "message" => "El correo electrónico ingresado no pertenece a una cuenta activa."]);
    }
    exit;
}

// -----------------------------------------------------------------
// ACCIÓN 2: VALIDAR CÓDIGO DE RECUPERACIÓN
// -----------------------------------------------------------------
if ($accion === 'validar_codigo') {
    $codigo_ingresado = isset($datos['codigo']) ? trim($datos['codigo']) : '';

    if (empty($codigo_ingresado)) {
        echo json_encode(["status" => "error", "message" => "El código de validación es obligatorio."]);
        exit;
    }

    // Verificar si existen los datos de recuperación en sesión
    if (!isset($_SESSION['rec_codigo']) || !isset($_SESSION['rec_email']) || !isset($_SESSION['rec_expira'])) {
        echo json_encode(["status" => "error", "message" => "No has iniciado una solicitud de recuperación de contraseña."]);
        exit;
    }

    // Validar expiración (10 minutos)
    if (time() > $_SESSION['rec_expira']) {
        // Limpiamos variables expiradas
        unset($_SESSION['rec_email']);
        unset($_SESSION['rec_codigo']);
        unset($_SESSION['rec_expira']);
        unset($_SESSION['rec_verificado']);
        unset($_SESSION['rec_tipo_cuenta']);
        
        echo json_encode(["status" => "error", "message" => "El código de recuperación ha expirado. Por favor, solicita uno nuevo."]);
        exit;
    }

    // Validar coincidencia
    if ($codigo_ingresado === $_SESSION['rec_codigo']) {
        $_SESSION['rec_verificado'] = true;
        echo json_encode(["status" => "success", "message" => "Código validado correctamente."]);
    } else {
        echo json_encode(["status" => "error", "message" => "El código de validación ingresado es incorrecto."]);
    }
    exit;
}

// -----------------------------------------------------------------
// ACCIÓN 3: RESTABLECER LA CONTRASEÑA
// -----------------------------------------------------------------
if ($accion === 'restablecer_contrasena') {
    $nueva_pass = isset($datos['pass']) ? trim($datos['pass']) : '';

    if (empty($nueva_pass)) {
        echo json_encode(["status" => "error", "message" => "La nueva contraseña es obligatoria."]);
        exit;
    }

    // Verificar que el código haya sido verificado previamente
    if (!isset($_SESSION['rec_verificado']) || $_SESSION['rec_verificado'] !== true || !isset($_SESSION['rec_email']) || !isset($_SESSION['rec_tipo_cuenta'])) {
        echo json_encode(["status" => "error", "message" => "Acceso no autorizado. Primero debes validar tu código de recuperación."]);
        exit;
    }

    $email = $_SESSION['rec_email'];
    $tipo_cuenta = $_SESSION['rec_tipo_cuenta'];

    // Validar complejidad de contraseña (PHP)
    $pass_err = validarPasswordComplejidad($nueva_pass);
    if ($pass_err) {
        echo json_encode(["status" => "error", "message" => "Seguridad de Contraseña: " . $pass_err]);
        exit;
    }

    // Encriptar la nueva contraseña con BCRYPT
    $pass_hash = password_hash($nueva_pass, PASSWORD_BCRYPT);

    // Determinar la tabla destino
    if ($tipo_cuenta === 'admin_ups') {
        $query = "UPDATE admin_ups SET pass = ? WHERE email = ? AND estatus = 'Activo'";
    } else {
        $query = "UPDATE empresas_clientes SET pass = ? WHERE email = ? AND activo = 1";
    }

    $stmt = $conexion->prepare($query);
    $stmt->bind_param("ss", $pass_hash, $email);

    if ($stmt->execute()) {
        // Limpiamos los datos de recuperación de la sesión para evitar reutilizaciones
        unset($_SESSION['rec_email']);
        unset($_SESSION['rec_codigo']);
        unset($_SESSION['rec_expira']);
        unset($_SESSION['rec_verificado']);
        unset($_SESSION['rec_tipo_cuenta']);

        echo json_encode(["status" => "success", "message" => "¡Contraseña restablecida con éxito! Ya puedes iniciar sesión."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error al actualizar la contraseña en el servidor: " . $stmt->error]);
    }

    $stmt->close();
    exit;
}

echo json_encode(["status" => "error", "message" => "Acción no reconocida por el servidor."]);
exit;
?>
