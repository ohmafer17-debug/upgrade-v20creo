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

$usuario_ejecutor_email = isset($_POST['usuario_ejecutor_email']) ? trim($_POST['usuario_ejecutor_email']) : (isset($datos['usuario_ejecutor_email']) ? trim($datos['usuario_ejecutor_email']) : '');

$rol_ejecutor = 'Invitado'; // Por defecto
if (!empty($usuario_ejecutor_email)) {
    $stmtEjec = $conexion->prepare("SELECT rol FROM admin_ups WHERE email = ? LIMIT 1");
    if ($stmtEjec) {
        $stmtEjec->bind_param("s", $usuario_ejecutor_email);
        $stmtEjec->execute();
        $resEjec = $stmtEjec->get_result();
        if ($resEjec && $resEjec->num_rows > 0) {
            $rowEjec = $resEjec->fetch_assoc();
            $rol_ejecutor = $rowEjec['rol'];
        }
        $stmtEjec->close();
    }
}

// Acciones que modifican empresas/licencias
$acciones_empresas_mutar = ['registrar_nueva_empresa', 'editar_empresa_cliente', 'estatus_empresa_cliente'];
if (in_array($accion, $acciones_empresas_mutar)) {
    if ($rol_ejecutor !== 'Administrador' && $rol_ejecutor !== 'Usuario Estándar') {
        echo json_encode(["status" => "error", "message" => "Acción denegada: Los invitados de Solo Lectura no pueden realizar modificaciones."]);
        exit;
    }
}

// Acciones que modifican el staff de administración
$acciones_staff_mutar = ['registrar_administrador_ups', 'editar_administrador_ups', 'estatus_administrador_ups'];
if (in_array($accion, $acciones_staff_mutar)) {
    if ($rol_ejecutor !== 'Administrador') {
        echo json_encode(["status" => "error", "message" => "Acción denegada: Únicamente el Administrador Máster puede gestionar el Staff interno."]);
        exit;
    }
}

// -----------------------------------------------------------------
// SECCIÓN A: MÓDULO DE EMPRESAS CLIENTES
// -----------------------------------------------------------------

if ($accion === 'registrar_nueva_empresa') {
    $empresa_nombre     = isset($_POST['empresa_nombre']) ? trim($_POST['empresa_nombre']) : (isset($datos['empresa_nombre']) ? trim($datos['empresa_nombre']) : '');
    $empresa_cod        = isset($_POST['empresa_cod']) ? trim($_POST['empresa_cod']) : (isset($datos['empresa_cod']) ? trim($datos['empresa_cod']) : ''); 
    $email_usuario      = isset($_POST['email_usuario']) ? trim($_POST['email_usuario']) : (isset($datos['email_usuario']) ? trim($datos['email_usuario']) : '');
    $pass_usuario       = isset($_POST['pass_usuario']) ? trim($_POST['pass_usuario']) : (isset($datos['pass_usuario']) ? trim($datos['pass_usuario']) : '');
    $rol_inicial        = isset($_POST['rol_inicial']) ? trim($_POST['rol_inicial']) : (isset($datos['rol_inicial']) ? trim($datos['rol_inicial']) : '');
    $rn_vinculado       = isset($_POST['rn_vinculado']) ? trim($_POST['rn_vinculado']) : (isset($datos['rn_vinculado']) ? trim($datos['rn_vinculado']) : '');

    $email_adicional    = isset($_POST['email_adicional']) ? trim($_POST['email_adicional']) : (isset($datos['email_adicional']) ? trim($datos['email_adicional']) : '');
    $telefono_principal = isset($_POST['telefono_principal']) ? trim($_POST['telefono_principal']) : (isset($datos['telefono_principal']) ? trim($datos['telefono_principal']) : '');
    $telefono_adicional = isset($_POST['telefono_adicional']) ? trim($_POST['telefono_adicional']) : (isset($datos['telefono_adicional']) ? trim($datos['telefono_adicional']) : '');
    $direccion          = isset($_POST['direccion']) ? trim($_POST['direccion']) : (isset($datos['direccion']) ? trim($datos['direccion']) : '');
    $coordenadas        = isset($_POST['coordenadas']) ? trim($_POST['coordenadas']) : (isset($datos['coordenadas']) ? trim($datos['coordenadas']) : '');

    if (empty($empresa_nombre) || empty($email_usuario) || empty($pass_usuario)) {
        echo json_encode(["status" => "error", "message" => "Existen campos mandatorios incompletos."]);
        exit;
    }

    // Validar contraseña
    $pass_err = validarPasswordComplejidad($pass_usuario);
    if ($pass_err) {
        echo json_encode(["status" => "error", "message" => "Seguridad de Contraseña: " . $pass_err]);
        exit;
    }

    // 🚀 LÓGICA DE AUTOGENERACIÓN DE ID SECUENCIAL DE COLABORADOR SI ES VINCULADO
    if (!empty($rn_vinculado) && $rn_vinculado !== 'NINGUNO') {
        $stmtRN = $conexion->prepare("SELECT cod FROM empresas_clientes WHERE email = ? LIMIT 1");
        $stmtRN->bind_param("s", $rn_vinculado);
        $stmtRN->execute();
        $resRN = $stmtRN->get_result();
        if ($resRN && $resRN->num_rows > 0) {
            $rowRN = $resRN->fetch_assoc();
            $cod_superior = $rowRN['cod'];
            
            $base_empresa = explode('/', $cod_superior)[0];
            
            $rol_limpio = strtolower($rol_inicial);
            $abreviatura = 'RN';
            if ($rol_limpio === 'tipo 1') {
                $abreviatura = 'T1';
            } elseif ($rol_limpio === 'tipo 2') {
                $abreviatura = 'T2';
            } elseif ($rol_limpio === 'tipo 3') {
                $abreviatura = 'T3';
            } elseif ($rol_limpio === 'consultor') {
                $abreviatura = 'Consultor';
            }
            
            $prefijo_busqueda = $conexion->real_escape_string($base_empresa . "/" . $abreviatura);
            $query_ultimo = "SELECT cod FROM empresas_clientes WHERE cod LIKE '$prefijo_busqueda%' ORDER BY cod DESC LIMIT 1";
            $res_ultimo = $conexion->query($query_ultimo);
            
            $siguiente_letra = 'A';
            if ($res_ultimo && $res_ultimo->num_rows > 0) {
                $row_ultimo = $res_ultimo->fetch_assoc();
                $ultimo_cod = $row_ultimo['cod'];
                
                $offset = strlen($base_empresa) + 1 + strlen($abreviatura);
                $sufijo_letra = substr($ultimo_cod, $offset);
                if (!empty($sufijo_letra)) {
                    $siguiente_letra = ++$sufijo_letra;
                }
            }
            $empresa_cod = $base_empresa . "/" . $abreviatura . $siguiente_letra;
        } else {
            echo json_encode(["status" => "error", "message" => "El superior vinculado no se encuentra registrado."]);
            $stmtRN->close();
            exit;
        }
        $stmtRN->close();
        $empresa_nombre .= " (RN: " . $rn_vinculado . ")";
    }

    // Procesar logotipo si se sube
    $logo_nombre_fisico = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(["status" => "error", "message" => "Error PHP al subir logotipo (Código: " . $_FILES['logo']['error'] . "). Valida el peso de la imagen y los límites de php.ini."]);
            exit;
        }
        
        $fileTmpPath = $_FILES['logo']['tmp_name'];
        $fileName    = $_FILES['logo']['name'];
        $fileSize    = $_FILES['logo']['size'];
        
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed_exts = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
        
        if (in_array($ext, $allowed_exts)) {
            if ($fileSize <= 5242880) { // 5MB max
                $logo_dir = __DIR__ . "/../public/uploads/logos/";
                if (!is_dir($logo_dir)) {
                    if (!mkdir($logo_dir, 0777, true)) {
                        echo json_encode(["status" => "error", "message" => "No se pudo crear la carpeta de destino del logotipo."]);
                        exit;
                    }
                }
                $logo_nombre_fisico = md5(time() . $fileName) . "." . $ext;
                if (!move_uploaded_file($fileTmpPath, $logo_dir . $logo_nombre_fisico)) {
                    echo json_encode(["status" => "error", "message" => "Error al guardar físicamente la imagen del logotipo en el servidor."]);
                    exit;
                }
            } else {
                echo json_encode(["status" => "error", "message" => "El logotipo supera el tamaño de 5MB permitido."]);
                exit;
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Formato de logotipo no válido. Solo se admiten PNG, JPG, JPEG, GIF o WEBP."]);
            exit;
        }
    }

    // Encriptar contraseña
    $pass_encriptada = password_hash($pass_usuario, PASSWORD_BCRYPT);
    $activo_inicial = 1;

    // Insertar incluyendo el logo
    $stmt = $conexion->prepare("INSERT INTO empresas_clientes (cod, nombre, email, email_adicional, telefono_principal, telefono_adicional, direccion, coordenadas, pass, activo, rol, logo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssiss", $empresa_cod, $empresa_nombre, $email_usuario, $email_adicional, $telefono_principal, $telefono_adicional, $direccion, $coordenadas, $pass_encriptada, $activo_inicial, $rol_inicial, $logo_nombre_fisico);
    
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "La empresa corporativa ha sido registrada exitosamente con firma criptográfica."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error interno en los parámetros: " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

if ($accion === 'obtener_solo_empresas_raiz') {
    $query = "SELECT cod, nombre FROM empresas_clientes WHERE LOWER(rol) = 'consultor' OR LOWER(rol) = 'cliente' ORDER BY nombre ASC";
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
        $query = "SELECT id, cod, nombre, email, email_adicional, telefono_principal, telefono_adicional, direccion, coordenadas, rol, activo, logo FROM empresas_clientes ORDER BY $ordenJerarquico, id DESC";
        $res = $conexion->query($query);
    } else {
        // Sentencia preparada para el filtrado dinámico por sucursal
        $filtro_like = $filtro . "/%";
        $stmt = $conexion->prepare("SELECT id, cod, nombre, email, email_adicional, telefono_principal, telefono_adicional, direccion, coordenadas, rol, activo, logo FROM empresas_clientes WHERE cod = ? OR cod LIKE ? ORDER BY $ordenJerarquico, id DESC");
        $stmt->bind_param("ss", $filtro, $filtro_like);
        $stmt->execute();
        $res = $stmt->get_result();
    }

    $licencias = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) { 
            $licencias[] = [
                "id" => $row['id'], 
                "empresa_cod" => $row['cod'], 
                "nombre_comercial" => $row['nombre'], 
                "usuario_responsable" => $row['email'], 
                "email_adicional" => $row['email_adicional'],
                "telefono_principal" => $row['telefono_principal'],
                "telefono_adicional" => $row['telefono_adicional'],
                "direccion" => $row['direccion'],
                "coordenadas" => $row['coordenadas'],
                "rol" => $row['rol'], 
                "activo" => $row['activo'],
                "logo" => $row['logo']
            ];
        }
    }
    if ($filtro !== 'TODAS') { $stmt->close(); }
    echo json_encode(["status" => "success", "data" => $licencias]);
    exit;
}

if ($accion === 'editar_empresa_cliente') {
    $id                 = isset($_POST['id']) ? (int)$_POST['id'] : (isset($datos['id']) ? (int)$datos['id'] : 0);
    $nombre             = isset($_POST['nombre']) ? trim($_POST['nombre']) : (isset($datos['nombre']) ? trim($datos['nombre']) : '');
    $email              = isset($_POST['email']) ? trim($_POST['email']) : (isset($datos['email']) ? trim($datos['email']) : '');
    $email_adicional    = isset($_POST['email_adicional']) ? trim($_POST['email_adicional']) : (isset($datos['email_adicional']) ? trim($datos['email_adicional']) : '');
    $telefono_principal = isset($_POST['telefono_principal']) ? trim($_POST['telefono_principal']) : (isset($datos['telefono_principal']) ? trim($datos['telefono_principal']) : '');
    $telefono_adicional = isset($_POST['telefono_adicional']) ? trim($_POST['telefono_adicional']) : (isset($datos['telefono_adicional']) ? trim($datos['telefono_adicional']) : '');
    $direccion          = isset($_POST['direccion']) ? trim($_POST['direccion']) : (isset($datos['direccion']) ? trim($datos['direccion']) : '');
    $coordenadas        = isset($_POST['coordenadas']) ? trim($_POST['coordenadas']) : (isset($datos['coordenadas']) ? trim($datos['coordenadas']) : '');
    $rol                = isset($_POST['rol']) ? trim($_POST['rol']) : (isset($datos['rol']) ? trim($datos['rol']) : '');
    $pass               = isset($_POST['pass']) ? trim($_POST['pass']) : (isset($datos['pass']) ? trim($datos['pass']) : '');

    if (empty($nombre) || empty($email)) {
        echo json_encode(["status" => "error", "message" => "El nombre comercial y el correo electrónico principal son requeridos."]);
        exit;
    }

    $pass_hash = null;
    if (!empty($pass)) {
        $pass_err = validarPasswordComplejidad($pass);
        if ($pass_err) {
            echo json_encode(["status" => "error", "message" => "Seguridad de Contraseña: " . $pass_err]);
            exit;
        }
        $pass_hash = password_hash($pass, PASSWORD_BCRYPT);
    }

    // Procesar logotipo si se edita
    $logo_nombre_fisico = null;
    $logo_actualizado = false;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(["status" => "error", "message" => "Error PHP al subir logotipo (Código: " . $_FILES['logo']['error'] . "). Valida el peso de la imagen y los límites de php.ini."]);
            exit;
        }
        
        $fileTmpPath = $_FILES['logo']['tmp_name'];
        $fileName    = $_FILES['logo']['name'];
        $fileSize    = $_FILES['logo']['size'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed_exts = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
        
        if (in_array($ext, $allowed_exts)) {
            if ($fileSize <= 5242880) { // 5MB max
                // 1. Consultar el logo viejo para borrarlo del disco
                $stmtOld = $conexion->prepare("SELECT logo FROM empresas_clientes WHERE id = ? LIMIT 1");
                $stmtOld->bind_param("i", $id);
                $stmtOld->execute();
                $resOld = $stmtOld->get_result();
                if ($resOld && $resOld->num_rows > 0) {
                    $rowOld = $resOld->fetch_assoc();
                    $oldLogo = $rowOld['logo'];
                    if (!empty($oldLogo)) {
                        $oldPath = __DIR__ . "/../public/uploads/logos/" . $oldLogo;
                        if (file_exists($oldPath)) {
                            unlink($oldPath);
                        }
                    }
                }
                $stmtOld->close();

                // 2. Guardar el nuevo logo físico
                $logo_dir = __DIR__ . "/../public/uploads/logos/";
                if (!is_dir($logo_dir)) {
                    if (!mkdir($logo_dir, 0777, true)) {
                        echo json_encode(["status" => "error", "message" => "No se pudo crear la carpeta de destino del logotipo."]);
                        exit;
                    }
                }
                $logo_nombre_fisico = md5(time() . $fileName) . "." . $ext;
                if (move_uploaded_file($fileTmpPath, $logo_dir . $logo_nombre_fisico)) {
                    $logo_actualizado = true;
                } else {
                    echo json_encode(["status" => "error", "message" => "Error al guardar físicamente la imagen del logotipo en el servidor."]);
                    exit;
                }
            } else {
                echo json_encode(["status" => "error", "message" => "El logotipo supera el tamaño de 5MB permitido."]);
                exit;
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Formato de logotipo no válido."]);
            exit;
        }
    }

    // Construcción dinámica de la sentencia preparada
    $query = "UPDATE empresas_clientes SET 
                nombre = ?, 
                email = ?, 
                email_adicional = ?, 
                telefono_principal = ?, 
                telefono_adicional = ?, 
                direccion = ?, 
                coordenadas = ?, 
                rol = ?";
    
    if ($pass_hash !== null) {
        $query .= ", pass = ?";
    }
    if ($logo_actualizado) {
        $query .= ", logo = ?";
    }
    $query .= " WHERE id = ?";

    $stmt = $conexion->prepare($query);
    
    // Bind dinámico
    if ($pass_hash !== null && $logo_actualizado) {
        $stmt->bind_param("ssssssssssi", $nombre, $email, $email_adicional, $telefono_principal, $telefono_adicional, $direccion, $coordenadas, $rol, $pass_hash, $logo_nombre_fisico, $id);
    } elseif ($pass_hash !== null) {
        $stmt->bind_param("sssssssssi", $nombre, $email, $email_adicional, $telefono_principal, $telefono_adicional, $direccion, $coordenadas, $rol, $pass_hash, $id);
    } elseif ($logo_actualizado) {
        $stmt->bind_param("ssssssssss", $nombre, $email, $email_adicional, $telefono_principal, $telefono_adicional, $direccion, $coordenadas, $rol, $logo_nombre_fisico, $id);
    } else {
        $stmt->bind_param("ssssssssi", $nombre, $email, $email_adicional, $telefono_principal, $telefono_adicional, $direccion, $coordenadas, $rol, $id);
    }

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
    $nombre              = trim($datos['nombre']);
    $email               = trim($datos['email']);
    $email_secundario    = isset($datos['email_secundario']) ? trim($datos['email_secundario']) : '';
    $telefono_principal  = isset($datos['telefono_principal']) ? trim($datos['telefono_principal']) : '';
    $telefono_secundario = isset($datos['telefono_secundario']) ? trim($datos['telefono_secundario']) : '';
    $rol                 = isset($datos['rol']) ? trim($datos['rol']) : 'Usuario Estándar';
    $pass                = trim($datos['pass']);

    //  ENCRIPCION PASO 4: Hashing seguro para el personal de staff interno
    $pass_encriptada = password_hash($pass, PASSWORD_BCRYPT);

    $stmt = $conexion->prepare("INSERT INTO admin_ups (nombre, email, email_secundario, telefono_principal, telefono_secundario, rol, pass) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $nombre, $email, $email_secundario, $telefono_principal, $telefono_secundario, $rol, $pass_encriptada);
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "¡Administrador del Staff registrado correctamente!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "El correo electrónico ya se encuentra registrado o falló el registro."]);
    }
    $stmt->close();
    exit;
}

if ($accion === 'listar_administradores_ups') {
    $query = "SELECT id, nombre, email, email_secundario, telefono_principal, telefono_secundario, rol, pass, estatus FROM admin_ups ORDER BY id DESC";
    $res = $conexion->query($query);
    $admins = [];
    if ($res) { while ($row = $res->fetch_assoc()) { $admins[] = $row; } }
    echo json_encode(["status" => "success", "data" => $admins]);
    exit;
}

if ($accion === 'editar_administrador_ups') {
    $id                  = (int)$datos['id'];
    $nombre              = trim($datos['nombre']);
    $email               = trim($datos['email']);
    $email_secundario    = isset($datos['email_secundario']) ? trim($datos['email_secundario']) : '';
    $telefono_principal  = isset($datos['telefono_principal']) ? trim($datos['telefono_principal']) : '';
    $telefono_secundario = isset($datos['telefono_secundario']) ? trim($datos['telefono_secundario']) : '';
    $rol                 = isset($datos['rol']) ? trim($datos['rol']) : 'Usuario Estándar';
    $pass                = trim($datos['pass']);

    // Validamos si cambiaron la contraseña para re-encriptarla o dejar la existente
    if (!empty($pass) && strlen($pass) < 20) {
        $pass_hash = password_hash($pass, PASSWORD_BCRYPT);
        $stmt = $conexion->prepare("UPDATE admin_ups SET nombre = ?, email = ?, email_secundario = ?, telefono_principal = ?, telefono_secundario = ?, rol = ?, pass = ? WHERE id = ?");
        $stmt->bind_param("sssssssi", $nombre, $email, $email_secundario, $telefono_principal, $telefono_secundario, $rol, $pass_hash, $id);
    } else {
        $stmt = $conexion->prepare("UPDATE admin_ups SET nombre = ?, email = ?, email_secundario = ?, telefono_principal = ?, telefono_secundario = ?, rol = ? WHERE id = ?");
        $stmt->bind_param("ssssssi", $nombre, $email, $email_secundario, $telefono_principal, $telefono_secundario, $rol, $id);
    }
    
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Datos del administrador actualizados con éxito."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error al actualizar: " . $stmt->error]);
    }
    $stmt->close();
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