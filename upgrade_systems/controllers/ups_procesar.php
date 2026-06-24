<?php
// =================================================================
// BACKEND GLOBAL DE UPS: ups_procesar.php 
// =================================================================

ini_set('display_errors', 0);
error_reporting(0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . "/../config/conexion.php";

$inputRaw = file_get_contents("php://input");
$datos = json_decode($inputRaw, true);

$accion = isset($_POST['accion']) ? $_POST['accion'] : (isset($datos['accion']) ? $datos['accion'] : '');

// -----------------------------------------------------------------
// SECCIÓN A: MÓDULO DE EMPRESAS CLIENTES
// -----------------------------------------------------------------

if ($accion === 'registrar_nueva_empresa') {
    $empresa_nombre = isset($_POST['empresa_nombre']) ? trim($_POST['empresa_nombre']) : trim($datos['empresa_nombre']);
    $empresa_cod    = isset($_POST['empresa_cod']) ? trim($_POST['empresa_cod']) : trim($datos['empresa_cod']); 
    $email_usuario  = isset($_POST['email_usuario']) ? trim($_POST['email_usuario']) : trim($datos['email_usuario']);
    $pass_usuario   = isset($_POST['pass_usuario']) ? trim($_POST['pass_usuario']) : trim($datos['pass_usuario']);
    $rol_inicial    = isset($_POST['rol_inicial']) ? trim($_POST['rol_inicial']) : trim($datos['rol_inicial']);
    $rn_vinculado   = isset($datos['rn_vinculado']) ? trim($datos['rn_vinculado']) : '';

    if (!empty($rn_vinculado) && $rn_vinculado !== 'NINGUNO') {
        $empresa_nombre .= " (RN: " . $rn_vinculado . ")";
    }

    //  ENCRIPCION PASO 4: Generamos el hash robusto e irreversible de la contraseña
    $pass_encriptada = password_hash($pass_usuario, PASSWORD_BCRYPT);
    $activo_inicial = 1;

    // Uso estricto de Sentencias Preparadas contra Inyección SQL
    $stmt = $conexion->prepare("INSERT INTO empresas_clientes (cod, nombre, email, pass, activo, rol) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssis", $empresa_cod, $empresa_nombre, $email_usuario, $pass_encriptada, $activo_inicial, $rol_inicial);
    
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "La empresa corporativa ha sido registrada exitosamente con firma criptográfica."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error interno en los parámetros: " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

if ($accion === 'obtener_solo_empresas_raiz') {
    $query = "SELECT cod, nombre FROM empresas_clientes WHERE email LIKE '%admin%' ORDER BY nombre ASC";
    $res = $conexion->query($query);
    $empresas = [];
    if ($res) { while ($row = $res->fetch_assoc()) { $empresas[] = $row; } }
    echo json_encode(["status" => "success", "data" => $empresas]);
    exit;
}

if ($accion === 'listar_licencias_globales') {
    $filtro = isset($datos['filtro_empresa']) ? trim($datos['filtro_empresa']) : 'TODAS';

    $ordenJerarquico = "CASE 
        WHEN LOWER(rol) = 'consultor' THEN 1 
        WHEN LOWER(rol) = 'responsable nacional' OR LOWER(rol) = 'responsable_nacional' THEN 2 
        WHEN LOWER(rol) = 'tipo 1' THEN 3 
        WHEN LOWER(rol) = 'tipo 2' THEN 4 
        WHEN LOWER(rol) = 'tipo 3' THEN 5 
        ELSE 6 
    END ASC";

    if ($filtro === 'TODAS') {
        $query = "SELECT id, cod, nombre, email, rol, activo FROM empresas_clientes ORDER BY $ordenJerarquico, id DESC";
        $res = $conexion->query($query);
    } else {
        // Sentencia preparada para el filtrado dinámico por sucursal
        $filtro_like = $filtro . "-%";
        $stmt = $conexion->prepare("SELECT id, cod, nombre, email, rol, activo FROM empresas_clientes WHERE cod = ? OR cod LIKE ? ORDER BY $ordenJerarquico, id DESC");
        $stmt->bind_param("ss", $filtro, $filtro_like);
        $stmt->execute();
        $res = $stmt->get_result();
    }

    $licencias = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) { 
            $licencias[] = [
                "id" => $row['id'], "empresa_cod" => $row['cod'], "nombre_comercial" => $row['nombre'], 
                "usuario_responsable" => $row['email'], "rol" => $row['rol'], "activo" => $row['activo']
            ];
        }
    }
    if ($filtro !== 'TODAS') { $stmt->close(); }
    echo json_encode(["status" => "success", "data" => $licencias]);
    exit;
}

if ($accion === 'editar_empresa_cliente') {
    $id     = (int)$datos['id'];
    $nombre = trim($datos['nombre']);
    $email  = trim($datos['email']);

    $stmt = $conexion->prepare("UPDATE empresas_clientes SET nombre = ?, email = ? WHERE id = ?");
    $stmt->bind_param("ssi", $nombre, $email, $id);
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Licencia de la empresa modificada con éxito."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error al actualizar: " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

if ($accion === 'estatus_empresa_cliente') {
    $id     = (int)$datos['id'];
    $activo = (int)$datos['activo']; 

    $stmt = $conexion->prepare("UPDATE empresas_clientes SET activo = ? WHERE id = ?");
    $stmt->bind_param("ii", $activo, $id);
    if ($stmt->execute()) {
        $msg = ($activo === 1) ? "La licencia ha sido ACTIVADA." : "La licencia ha sido SUSPENDIDA.";
        echo json_encode(["status" => "success", "message" => $msg]);
    } $stmt->close();
    exit;
}

// -----------------------------------------------------------------
// SECCIÓN B: MÓDULO INTERNO PARA EL STAFF DE ADMINISTRADORES UPS
// -----------------------------------------------------------------

if ($accion === 'registrar_administrador_ups') {
    $nombre = trim($datos['nombre']);
    $email  = trim($datos['email']);
    $pass   = trim($datos['pass']);

    //  ENCRIPCION PASO 4: Hashing seguro para el personal de staff interno
    $pass_encriptada = password_hash($pass, PASSWORD_BCRYPT);

    $stmt = $conexion->prepare("INSERT INTO admin_ups (nombre, email, pass) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $nombre, $email, $pass_encriptada);
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "¡Administrador del Staff registrado correctamente!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "El correo electrónico ya se encuentra registrado."]);
    }
    $stmt->close();
    exit;
}

if ($accion === 'listar_administradores_ups') {
    $query = "SELECT id, nombre, email, pass, estatus FROM admin_ups ORDER BY id DESC";
    $res = $conexion->query($query);
    $admins = [];
    if ($res) { while ($row = $res->fetch_assoc()) { $admins[] = $row; } }
    echo json_encode(["status" => "success", "data" => $admins]);
    exit;
}

if ($accion === 'editar_administrador_ups') {
    $id     = (int)$datos['id'];
    $nombre = trim($datos['nombre']);
    $email  = trim($datos['email']);
    $pass   = trim($datos['pass']);

    // Validamos si cambiaron la contraseña para re-encriptarla o dejar la existente
    if (strlen($pass) < 20) {
        $pass = password_hash($pass, PASSWORD_BCRYPT);
    }

    $stmt = $conexion->prepare("UPDATE admin_ups SET nombre = ?, email = ?, pass = ? WHERE id = ?");
    $stmt->bind_param("sssi", $nombre, $email, $pass, $id);
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Datos del administrador actualizados con éxito."]);
    } $stmt->close();
    exit;
}

if ($accion === 'estatus_administrador_ups') {
    $id      = (int)$datos['id'];
    $estatus = trim($datos['estatus']);

    $stmt = $conexion->prepare("UPDATE admin_ups SET estatus = ? WHERE id = ?");
    $stmt->bind_param("si", $estatus, $id);
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Estatus cambiado a $estatus de forma correcta."]);
    } $stmt->close();
    exit;
}

echo json_encode(["status" => "error", "message" => "Acción denegada en la consola máster."]);
exit;
?>