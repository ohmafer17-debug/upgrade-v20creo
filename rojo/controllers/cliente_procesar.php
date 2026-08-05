<?php
// =================================================================
// CONTROLADOR COMPLEMENTARIO: cliente_procesar.php (VERSIÓN ALERTA GLOBAL MASIVA)
// =================================================================
ini_set('display_errors', 0);
error_reporting(0);

// Cabeceras anti-cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// 🚀 CORRECCIÓN DE RUTA: Salir de controllers/ y entrar a config/ para enlazar la base de datos
require_once __DIR__ . "/../config/conexion.php";

// Migración silenciosa de columna para correos adicionales de notificación
$checkCol = $conexion->query("SHOW COLUMNS FROM documentos_pc LIKE 'notificar_correos'");
if ($checkCol && $checkCol->num_rows === 0) {
    $conexion->query("ALTER TABLE documentos_pc ADD COLUMN notificar_correos TEXT DEFAULT NULL;");
}

$inputRaw = file_get_contents("php://input");
$datos = json_decode($inputRaw, true);

$accion = isset($_POST['accion']) ? $_POST['accion'] : (isset($datos['accion']) ? $datos['accion'] : '');

// --- ACCIÓN 0: PREVISUALIZAR ID DE SUCURSAL ---
if ($accion === 'previsualizar_id_sucursal') {
    $empresa_base = isset($datos['empresa_cod']) ? $conexion->real_escape_string(trim($datos['empresa_cod'])) : '';
    $nombre_sucursal = isset($datos['nombre_sucursal']) ? trim($datos['nombre_sucursal']) : '';

    if (empty($empresa_base)) { echo json_encode(['status'=>'error','message'=>'Código base requerido.']); exit; }
    $base = explode('/', $empresa_base)[0];

    if (empty($nombre_sucursal)) {
        echo json_encode(['status'=>'success', 'siguiente_id'=> $base . '/...']);
        exit;
    }

    $clean_nombre = preg_replace('/[^a-zA-Z0-9\s]/', '', $nombre_sucursal);
    $palabras = array_filter(explode(' ', $clean_nombre), function($p) { return strlen($p) >= 2; });
    $iniciales = '';
    foreach ($palabras as $p) {
        $iniciales .= strtoupper($p[0]);
    }
    if (strlen($iniciales) < 2) {
        $limpio = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $clean_nombre));
        $iniciales = substr($limpio, 0, 3);
    }
    if (strlen($iniciales) < 2) {
        $iniciales = 'SUC';
    }
    $abrev = substr($iniciales, 0, 4);

    $prefijo_busqueda = $base . "/" . $abrev;
    $query_check = $conexion->query("SELECT id FROM empresas_clientes WHERE cod = '$prefijo_busqueda' LIMIT 1");
    if ($query_check && $query_check->num_rows > 0) {
        $intentos = 1;
        $siguiente_id = $prefijo_busqueda . "1";
        while ($intentos < 100) {
            $sufijo_intento = $abrev . $intentos;
            $codigo_intento = $base . "/" . $sufijo_intento;
            $check_int = $conexion->query("SELECT id FROM empresas_clientes WHERE cod = '$codigo_intento' LIMIT 1");
            if ($check_int && $check_int->num_rows === 0) {
                $siguiente_id = $codigo_intento;
                break;
            }
            $intentos++;
        }
    } else {
        $siguiente_id = $prefijo_busqueda;
    }

    echo json_encode(['status'=>'success', 'siguiente_id'=> $siguiente_id]);
    exit;
}

// --- ACCIÓN 1: CREAR NODO OPERATIVO ---
if ($accion === 'crear_usuario_operativo') {
    $rol_ejecutor = isset($_POST['rol_ejecutor']) ? trim($_POST['rol_ejecutor']) : (isset($datos['rol_ejecutor']) ? trim($datos['rol_ejecutor']) : '');
    $rol_ejecutor = strtolower($rol_ejecutor);
    
    if ($rol_ejecutor !== 'administrador' && $rol_ejecutor !== 'consultor' && $rol_ejecutor !== 'responsable nacional' && $rol_ejecutor !== 'responsable_nacional' && $rol_ejecutor !== 'tipo 1') {
        echo json_encode(["status" => "error", "message" => "Denegado: Su rango operativo no posee permisos para crear nodos de estructura."]);
        exit;
    }

    $nombre             = isset($_POST['nombre']) ? trim($_POST['nombre']) : (isset($datos['nombre']) ? trim($datos['nombre']) : '');
    $nombre             = $conexion->real_escape_string($nombre);
    
    $rol_a_crear        = isset($_POST['rol']) ? trim($_POST['rol']) : (isset($datos['rol']) ? trim($datos['rol']) : '');
    $rol_a_crear        = $conexion->real_escape_string($rol_a_crear);

    $rol_a_crear_lower = strtolower($rol_a_crear);
    if ($rol_ejecutor === 'responsable nacional' || $rol_ejecutor === 'responsable_nacional' || $rol_ejecutor === 'tipo 1') {
        if ($rol_a_crear_lower !== 'tipo 1' && $rol_a_crear_lower !== 'tipo 2' && $rol_a_crear_lower !== 'tipo 3') {
            echo json_encode(["status" => "error", "message" => "Denegado: Su rango operativo solo posee privilegios para crear Tipo 1, Tipo 2 o Tipo 3."]);
            exit;
        }
    }
    
    $email              = isset($_POST['email']) ? trim($_POST['email']) : (isset($datos['email']) ? trim($datos['email']) : '');
    $email              = $conexion->real_escape_string($email);
    
    $email_adicional    = isset($_POST['email_adicional']) ? trim($_POST['email_adicional']) : (isset($datos['email_adicional']) ? trim($datos['email_adicional']) : '');
    $email_adicional    = $conexion->real_escape_string($email_adicional);
    
    $telefono_principal = isset($_POST['telefono_principal']) ? trim($_POST['telefono_principal']) : (isset($datos['telefono_principal']) ? trim($datos['telefono_principal']) : '');
    $telefono_principal = $conexion->real_escape_string($telefono_principal);
    
    $telefono_adicional = isset($_POST['telefono_adicional']) ? trim($_POST['telefono_adicional']) : (isset($datos['telefono_adicional']) ? trim($datos['telefono_adicional']) : '');
    $telefono_adicional = $conexion->real_escape_string($telefono_adicional);
    
    $pass               = isset($_POST['pass']) ? trim($_POST['pass']) : (isset($datos['pass']) ? trim($datos['pass']) : '');
    $pass               = $conexion->real_escape_string($pass);
    
    $empresa_cod        = isset($_POST['empresa_cod']) ? trim($_POST['empresa_cod']) : (isset($datos['empresa_cod']) ? trim($datos['empresa_cod']) : '');
    $empresa_cod        = $conexion->real_escape_string($empresa_cod);

    $direccion          = isset($_POST['direccion']) ? trim($_POST['direccion']) : (isset($datos['direccion']) ? trim($datos['direccion']) : '');
    $direccion          = $conexion->real_escape_string($direccion);

    $coordenadas        = isset($_POST['coordenadas']) ? trim($_POST['coordenadas']) : (isset($datos['coordenadas']) ? trim($datos['coordenadas']) : '');
    $coordenadas        = $conexion->real_escape_string($coordenadas);

    $coordenadas_gps    = isset($_POST['coordenadas_gps']) ? trim($_POST['coordenadas_gps']) : (isset($datos['coordenadas_gps']) ? trim($datos['coordenadas_gps']) : '');
    $coordenadas_gps    = $conexion->real_escape_string($coordenadas_gps);

    $encargado          = isset($_POST['encargado']) ? trim($_POST['encargado']) : (isset($datos['encargado']) ? trim($datos['encargado']) : '');
    $encargado          = $conexion->real_escape_string($encargado);

    $director_email     = isset($_POST['director_email']) ? trim($_POST['director_email']) : (isset($datos['director_email']) ? trim($datos['director_email']) : '');
    $director_email     = $conexion->real_escape_string($director_email);

    if (empty($nombre) || empty($rol_a_crear) || empty($email) || empty($pass) || empty($empresa_cod)) {
        echo json_encode(["status" => "error", "message" => "Existen campos mandatorios incompletos."]);
        exit;
    }
    if ($rol_ejecutor === 'consultor' && empty($coordenadas_gps)) {
        echo json_encode(["status" => "error", "message" => "Las coordenadas (Latitud, Longitud) son obligatorias para registrar una sucursal."]);
        exit;
    }

    // Validar contraseña
    $pass_err = validarPasswordComplejidad($pass);
    if ($pass_err) {
        echo json_encode(["status" => "error", "message" => "Seguridad de Contraseña: " . $pass_err]);
        exit;
    }

    $check = $conexion->query("SELECT id FROM empresas_clientes WHERE email = '$email' LIMIT 1");
    if($check && $check->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "El correo electrónico ya pertenece a un nodo registrado."]);
        exit;
    }

    // Procesar logotipo si se sube
    $logo_nombre_fisico = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(["status" => "error", "message" => "Error PHP al subir logotipo (Código: " . $_FILES['logo']['error'] . ")."]);
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
                    mkdir($logo_dir, 0777, true);
                }
                $logo_nombre_fisico = md5(time() . $fileName) . "." . $ext;
                if (!move_uploaded_file($fileTmpPath, $logo_dir . $logo_nombre_fisico)) {
                    echo json_encode(["status" => "error", "message" => "Error al guardar la imagen del logotipo en el servidor."]);
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

    // Obtener la raíz de la empresa (ej: CONS-01 de CONS-01/RNA o CONS-01)
    $base_empresa = explode('/', $empresa_cod)[0];

    // Función para generar la abreviatura del nombre de la empresa/sucursal
    $clean_nombre = preg_replace('/[^a-zA-Z0-9\s]/', '', $nombre);
    $palabras = array_filter(explode(' ', $clean_nombre), function($p) { return strlen($p) >= 2; });
    $iniciales = '';
    foreach ($palabras as $p) {
        $iniciales .= strtoupper($p[0]);
    }
    if (strlen($iniciales) < 2) {
        $limpio = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $clean_nombre));
        $iniciales = substr($limpio, 0, 3);
    }
    if (strlen($iniciales) < 2) {
        $iniciales = 'SUC';
    }
    $abrev = substr($iniciales, 0, 4);

    // Evitar colisiones en la base de datos
    $prefijo_busqueda = $base_empresa . "/" . $abrev;
    $query_check = $conexion->query("SELECT id FROM empresas_clientes WHERE cod = '$prefijo_busqueda' LIMIT 1");
    if ($query_check && $query_check->num_rows > 0) {
        $intentos = 1;
        $cod_unico_nodo = $prefijo_busqueda . "1"; // Por defecto
        while ($intentos < 100) {
            $sufijo_intento = $abrev . $intentos;
            $codigo_intento = $base_empresa . "/" . $sufijo_intento;
            $check_int = $conexion->query("SELECT id FROM empresas_clientes WHERE cod = '$codigo_intento' LIMIT 1");
            if ($check_int && $check_int->num_rows === 0) {
                $cod_unico_nodo = $codigo_intento;
                break;
            }
            $intentos++;
        }
    } else {
        $cod_unico_nodo = $prefijo_busqueda;
    }
    
    $pass_encriptada = password_hash($pass, PASSWORD_BCRYPT);

    $logo_val = $logo_nombre_fisico ? "'$logo_nombre_fisico'" : "NULL";
    $encargado_val = !empty($encargado) ? "'$encargado'" : "NULL";
    $director_email_val = !empty($director_email) ? "'$director_email'" : "NULL";
    $queryInsert = "INSERT INTO empresas_clientes (cod, nombre, encargado, email, email_adicional, telefono_principal, telefono_adicional, direccion, coordenadas, coordenadas_gps, logo, pass, activo, rol, director_email) 
                    VALUES ('$cod_unico_nodo', '$nombre', $encargado_val, '$email', '$email_adicional', '$telefono_principal', '$telefono_adicional', '$direccion', '$coordenadas', '$coordenadas_gps', $logo_val, '$pass_encriptada', 1, '$rol_a_crear', $director_email_val)";

    if ($conexion->query($queryInsert)) {
        // 🚀 REGLA DE NEGOCIO: Si a la empresa/sucursal se le asigna un Responsable Nacional, esta debe cambiar al rango inferior (Tipo 1)
        if (strtolower($rol_a_crear) === 'responsable nacional') {
            $empresa_cod_esc = $conexion->real_escape_string($empresa_cod);
            $conexion->query("UPDATE empresas_clientes SET rol = 'Tipo 1' WHERE cod = '$empresa_cod_esc'");
        }
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => $conexion->error]);
    }
    exit;
}

// --- ACCIÓN 2: SUBIR O ACTUALIZAR DOCUMENTO ---
if ($accion === 'subir_documento') {
    $rol_ejecutor = strtolower(trim($_POST['rol_ejecutor']));
    $usuario_ejecutor = $conexion->real_escape_string(trim($_POST['usuario_ejecutor'])); 
    $es_actualizacion = isset($_POST['es_actualizacion']) ? $_POST['es_actualizacion'] : 'no';

    if ($rol_ejecutor === 'tipo 3') {
        echo json_encode(["status" => "error", "message" => "Denegado: Su rango operativo no está facultado para subir archivos."]);
        exit;
    }
    if ($rol_ejecutor === 'tipo 2' && $es_actualizacion !== 'si') {
        echo json_encode(["status" => "error", "message" => "Denegado: Su rango operativo solo permite actualizar documentos existentes."]);
        exit;
    }

    $empresa_cod = $conexion->real_escape_string(trim($_POST['empresa_cod']));
    $tipo_doc    = $conexion->real_escape_string(trim($_POST['tipo_doc']));
    $nombre_p    = $conexion->real_escape_string(trim($_POST['nombre_personalizado']));
    $motivo      = isset($_POST['motivo']) ? $conexion->real_escape_string(trim($_POST['motivo'])) : '';
    $notificar_correos = isset($_POST['notificar_correos']) ? $conexion->real_escape_string(trim($_POST['notificar_correos'])) : '';
    
    $vencimiento = isset($_POST['fecha_vencimiento']) ? trim($_POST['fecha_vencimiento']) : '';
    $vencimiento_sql = empty($vencimiento) ? "'0000-00-00'" : "'" . $conexion->real_escape_string($vencimiento) . "'";
    
    $fecha_actual_sistema = date('Y-m-d'); 
    $fecha_subida_manual = (isset($_POST['fecha_subida']) && !empty($_POST['fecha_subida'])) ? trim($_POST['fecha_subida']) : date('Y-m-d H:i:s');
    $fecha_solo_base = trim(explode(' ', $fecha_subida_manual)[0]);

    $nombre_p .= " [Reg: " . $fecha_subida_manual . "]";

    $es_actualizacion = isset($_POST['es_actualizacion']) ? $_POST['es_actualizacion'] : 'no';

    // Obtener código base de la organización
    $empresa_cod_raw = trim($_POST['empresa_cod']);
    $empresa_cods = array_map('trim', explode(',', $empresa_cod_raw));
    $first_cod = $empresa_cods[0];
    $base_empresa = explode('/', $first_cod)[0];
    $base_empresa_limpio = preg_replace('/[^a-zA-Z0-9]/', '', $base_empresa);
    if (empty($base_empresa_limpio)) $base_empresa_limpio = 'GENERAL';

    $dest_path = __DIR__ . "/../uploads_dictamenes/" . $base_empresa_limpio . "/";
    if (!is_dir($dest_path)) mkdir($dest_path, 0777, true);

    $extensiones_permitidas = ['pdf', 'jpg', 'jpeg', 'png'];
    $MAX_SIZE = 104857600; // 100MB

    // Recopilar archivos enviados (soporta archivo[] múltiple o ninguno)
    $archivos = [];
    if (!empty($_FILES['archivo']['name'][0])) {
        // Múltiples archivos via archivo[]
        $count = count($_FILES['archivo']['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['archivo']['error'][$i] === UPLOAD_ERR_OK) {
                $archivos[] = [
                    'tmp_name' => $_FILES['archivo']['tmp_name'][$i],
                    'name'     => $_FILES['archivo']['name'][$i],
                    'size'     => $_FILES['archivo']['size'][$i],
                ];
            }
        }
    } elseif (isset($_FILES['archivo']) && !is_array($_FILES['archivo']['name']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        // Compatibilidad: un solo archivo sin []
        $archivos[] = [
            'tmp_name' => $_FILES['archivo']['tmp_name'],
            'name'     => $_FILES['archivo']['name'],
            'size'     => $_FILES['archivo']['size'],
        ];
    }

    // Si hay archivos, validar y subir cada uno
    if (!empty($archivos)) {
        $archivos_subidos = []; // nombre_fisico => archivo info
        foreach ($archivos as $archivo) {
            $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $extensiones_permitidas)) {
                echo json_encode(["status" => "error", "message" => "Formato no válido en '{$archivo['name']}'. Solo PDF o Imágenes."]);
                exit;
            }
            if ($archivo['size'] > $MAX_SIZE) {
                echo json_encode(["status" => "error", "message" => "'{$archivo['name']}' supera el límite de 100MB permitido."]);
                exit;
            }
            $nuevo_nombre_fisico = md5(time() . $archivo['name'] . rand()) . "." . $ext;
            $ruta_final = $dest_path . $nuevo_nombre_fisico;
            if (!move_uploaded_file($archivo['tmp_name'], $ruta_final)) {
                echo json_encode(["status" => "error", "message" => "No se pudo mover '{$archivo['name']}' al servidor."]);
                exit;
            }
            $archivos_subidos[] = $nuevo_nombre_fisico;
        }

        // Insertar/actualizar un registro por cada archivo subido
        foreach ($archivos_subidos as $idx => $nuevo_nombre_fisico) {
            $sufijo = count($archivos_subidos) > 1 ? " [Doc " . ($idx + 1) . "]" : "";
            foreach ($empresa_cods as $current_cod) {
                if (empty($current_cod)) continue;
                $current_cod_esc = $conexion->real_escape_string($current_cod);
                if ($es_actualizacion === 'si' && $idx === 0) {
                    $checkDoc = $conexion->query("SELECT id, nombre_archivo_fisico, nombre_personalizado, fecha_vencimiento, subido_por, actualizado_por FROM documentos_pc WHERE empresa_cod = '$current_cod_esc' AND tipo_doc = '$tipo_doc' LIMIT 1");
                    if ($checkDoc && $checkDoc->num_rows > 0) {
                        $docViejo = $checkDoc->fetch_assoc();
                        $dId = $docViejo['id'];
                        $vNom = $conexion->real_escape_string($docViejo['nombre_personalizado']);
                        $vFec_val = is_null($docViejo['fecha_vencimiento']) || empty($docViejo['fecha_vencimiento']) ? "'0000-00-00'" : "'".$docViejo['fecha_vencimiento']."'";
                        $vArc = $docViejo['nombre_archivo_fisico'];
                        $vAutor = !empty($docViejo['actualizado_por']) ? $docViejo['actualizado_por'] : $docViejo['subido_por'];
                        $conexion->query("INSERT INTO historial_documentos (documento_id, empresa_cod, tipo_doc, nombre_personalizado, fecha_vencimiento, nombre_archivo_fisico, subido_por, motivo) VALUES ($dId, '$current_cod_esc', '$tipo_doc', '$vNom', $vFec_val, '$vArc', '$vAutor', '$motivo')");
                        $conexion->query("UPDATE documentos_pc SET nombre_personalizado = '$nombre_p', fecha_vencimiento = $vencimiento_sql, fecha_subida_sistema = '$fecha_solo_base', actualizado_por = '$usuario_ejecutor', nombre_archivo_fisico = '$nuevo_nombre_fisico', estatus = 1, motivo = '$motivo', notificar_correos = '$notificar_correos' WHERE id = $dId");
                        continue;
                    }
                }
                $nombre_p_final = $conexion->real_escape_string($nombre_p . $sufijo);
                $conexion->query("INSERT INTO documentos_pc (empresa_cod, tipo_doc, nombre_personalizado, fecha_vencimiento, fecha_subida_sistema, subido_por, estatus, nombre_archivo_fisico, motivo, notificar_correos) VALUES ('$current_cod_esc', '$tipo_doc', '$nombre_p_final', $vencimiento_sql, '$fecha_solo_base', '$usuario_ejecutor', 1, '$nuevo_nombre_fisico', '$motivo', '$notificar_correos')");
            }
        }
        echo json_encode(["status" => "success", "message" => "¡Archivo(s) indexado(s) con éxito!", "nueva_categoria" => $tipo_doc]);

    } else {
        // Sin archivos: solo actualizar metadata si es actualización, o crear registro sin archivo
        foreach ($empresa_cods as $current_cod) {
            if (empty($current_cod)) continue;
            $current_cod_esc = $conexion->real_escape_string($current_cod);
            if ($es_actualizacion === 'si') {
                $checkDoc = $conexion->query("SELECT id FROM documentos_pc WHERE empresa_cod = '$current_cod_esc' AND tipo_doc = '$tipo_doc' LIMIT 1");
                if ($checkDoc && $checkDoc->num_rows > 0) {
                    $dId = $checkDoc->fetch_assoc()['id'];
                    $conexion->query("UPDATE documentos_pc SET nombre_personalizado = '$nombre_p', fecha_vencimiento = $vencimiento_sql, fecha_subida_sistema = '$fecha_solo_base', actualizado_por = '$usuario_ejecutor', motivo = '$motivo', notificar_correos = '$notificar_correos' WHERE id = $dId");
                    continue;
                }
            }
            $conexion->query("INSERT INTO documentos_pc (empresa_cod, tipo_doc, nombre_personalizado, fecha_vencimiento, fecha_subida_sistema, subido_por, estatus, nombre_archivo_fisico, motivo, notificar_correos) VALUES ('$current_cod_esc', '$tipo_doc', '$nombre_p', $vencimiento_sql, '$fecha_solo_base', '$usuario_ejecutor', 1, '', '$motivo', '$notificar_correos')");
        }
        echo json_encode(["status" => "success", "message" => "¡Registro indexado sin archivo adjunto!", "nueva_categoria" => $tipo_doc]);
    }
    exit;
}

// --- ACCIÓN 2.5: LISTAR MIS NODOS (SUCURSALES Y PROPIA EMPRESA) ---
if ($accion === 'listar_mis_nodos') {
    $empresa_cod = $conexion->real_escape_string(trim($datos['empresa_cod']));
    $rol_ejecutor = isset($datos['rol_ejecutor']) ? strtolower(trim($datos['rol_ejecutor'])) : '';

    if ($rol_ejecutor === 'tipo 1' || $rol_ejecutor === 'tipo 2' || $rol_ejecutor === 'tipo 3') {
        $res = $conexion->query("SELECT cod, nombre FROM empresas_clientes WHERE cod = '$empresa_cod' ORDER BY cod ASC");
    } else {
        $base_empresa = explode('/', $empresa_cod)[0];
        $res = $conexion->query("SELECT cod, nombre FROM empresas_clientes WHERE cod = '$base_empresa' OR (cod LIKE '$base_empresa/%' AND encargado IS NOT NULL AND encargado != '') ORDER BY cod ASC");
    }
    $nodos = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $nodos[] = $row;
        }
    }
    echo json_encode(["status" => "success", "data" => $nodos]);
    exit;
}

// --- ACCIÓN 3: LISTAR DOCUMENTOS ---
if ($accion === 'listar_documentos') {
    $rol_ejecutor = strtolower(trim($datos['rol_ejecutor']));
    if ($rol_ejecutor === 'administrador') return;

    $empresa_cod = $conexion->real_escape_string(trim($datos['empresa_cod']));
    $empresa_especifica_cod = isset($datos['empresa_especifica_cod']) ? $conexion->real_escape_string(trim($datos['empresa_especifica_cod'])) : '';

    if ($rol_ejecutor === 'tipo 1' || $rol_ejecutor === 'tipo 2' || $rol_ejecutor === 'tipo 3') {
        $res = $conexion->query("SELECT id, empresa_cod, tipo_doc, nombre_personalizado, fecha_vencimiento, fecha_subida_sistema, subido_por, actualizado_por, visto_por, estatus, nombre_archivo_fisico, notificar_correos FROM documentos_pc WHERE empresa_cod = '$empresa_cod' ORDER BY id DESC");
    } else {
        if (!empty($empresa_especifica_cod)) {
            $res = $conexion->query("SELECT id, empresa_cod, tipo_doc, nombre_personalizado, fecha_vencimiento, fecha_subida_sistema, subido_por, actualizado_por, visto_por, estatus, nombre_archivo_fisico, notificar_correos FROM documentos_pc WHERE empresa_cod = '$empresa_especifica_cod' ORDER BY id DESC");
        } else {
            $base_empresa = explode('/', $empresa_cod)[0];
            $res = $conexion->query("SELECT id, empresa_cod, tipo_doc, nombre_personalizado, fecha_vencimiento, fecha_subida_sistema, subido_por, actualizado_por, visto_por, estatus, nombre_archivo_fisico, notificar_correos FROM documentos_pc WHERE empresa_cod = '$base_empresa' OR empresa_cod LIKE '$base_empresa/%' ORDER BY id DESC");
        }
    }
    
    $documentos = [];
    $fecha_actual = date('Y-m-d');

    if($res) { 
        while($row = $res->fetch_assoc()) {
            $fecha_vencimiento = $row['fecha_vencimiento'];
            $fecha_subida = $row['fecha_subida_sistema'];
            
            $porcentaje_consumido = 0;
            $color_semaforo = 'green';
            $mensaje_estatus = 'Vigente';
            $escalar_a_admin_ups = false;
            $roles_notificados = [];

            if ($fecha_vencimiento && $fecha_vencimiento !== '0000-00-00') {
                $timestamp_subida = strtotime($fecha_subida);
                $timestamp_vence  = strtotime($fecha_vencimiento);
                $timestamp_actual = strtotime($fecha_actual);
                
                $dias_totales_vida  = ($timestamp_vence - $timestamp_subida) / 86400;
                $dias_transcurridos = ($timestamp_actual - $timestamp_subida) / 86400;
                
                if ($dias_totales_vida > 0) {
                    $porcentaje_consumido = ($dias_transcurridos / $dias_totales_vida) * 100;
                } else {
                    $porcentaje_consumido = 100;
                }

                if (intval($row['estatus']) === 0) {
                    $color_semaforo = 'gray'; 
                    $mensaje_estatus = 'Archivado / Inactivo';
                    $roles_notificados = ['tipo 3', 'tipo 2', 'tipo 1', 'responsable nacional', 'consultor', 'administrador'];
                    $escalar_a_admin_ups = true; 
                } elseif ($porcentaje_consumido >= 100 || $timestamp_actual >= $timestamp_vence) {
                    $color_semaforo = 'red'; 
                    $mensaje_estatus = 'Vencido Crítico';
                    $roles_notificados = ['tipo 3', 'tipo 2', 'tipo 1', 'responsable nacional', 'consultor', 'administrador'];
                    $escalar_a_admin_ups = true; 
                } elseif ($porcentaje_consumido >= 90) {
                    $color_semaforo = 'orange'; 
                    $mensaje_estatus = 'Próximo a vencer (Urgente)';
                    $roles_notificados = ['tipo 3', 'tipo 2'];
                } elseif ($porcentaje_consumido >= 75) {
                    $color_semaforo = 'yellow'; 
                    $mensaje_estatus = 'Próximo a vencer';
                    $roles_notificados = ['tipo 3'];
                } else {
                    $color_semaforo = 'green'; 
                    $mensaje_estatus = 'Vigente';
                }
            }

            $row['color_calculado']   = $color_semaforo;
            $row['estatus_texto']     = $mensaje_estatus;
            $row['porcentaje_vida']   = round($porcentaje_consumido, 2) . '%';
            $row['roles_alerta']       = $roles_notificados;
            $row['alerta_global_ups'] = $escalar_a_admin_ups;

            $docId = $row['id'];
            $countQuery = $conexion->query("SELECT COUNT(id) as total FROM historial_documentos WHERE documento_id = $docId");
            $countRow = $countQuery->fetch_assoc();
            $row['total_actualizaciones'] = $countRow['total'];

            $row['nombre_limpio'] = preg_replace('/\[Reg: .*?\]/', '', $row['nombre_personalizado']);

            if (in_array(strtolower($rol_ejecutor), $roles_notificados) && $color_semaforo !== 'green') {
                $para = "responsable_infraestructura@upgradesystems.com";
                $asunto = "🚨 ALERTA AUTOMÁTICA DE VENCIMIENTO - " . strtoupper($row['tipo_doc']);
                $mensaje = "Estimado Equipo,\n\n";
                $mensaje .= "Se notifica que el documento " . $row['tipo_doc'] . " (" . $row['nombre_limpio'] . ") ha entrado en la fase " . $mensaje_estatus . ".\n\n";
                $mensaje .= "Requiere revisión inmediata en el sistema.\n\n";
                $mensaje .= "Atentamente,\nXonexka";

                $cabeceras = "From: no-reply@upgradesystems.com\r\nReply-To: no-reply@upgradesystems.com\r\nContent-Type: text/plain; charset=UTF-8\r\nX-Mailer: PHP/" . phpversion();
                mail($para, $asunto, $mensaje, $cabeceras);
            }

            $documentos[] = $row;
        } 
    }
    
    echo json_encode(["status" => "success", "data" => $documentos]);
    exit;
}

// --- ACCIÓN 4: INTERRUPTOR DINÁMICO ---
if ($accion === 'suspender_documento') {
    $rol_ejecutor = strtolower(trim($datos['rol_ejecutor']));
    if ($rol_ejecutor === 'tipo 2' || $rol_ejecutor === 'tipo 3') {
        echo json_encode(["status" => "error", "message" => "Su rango no permite modificar archivos."]); exit;
    }
    
    $id_doc = intval($datos['id_documento']);
    $motivo = isset($datos['motivo']) ? $conexion->real_escape_string(trim($datos['motivo'])) : '';
    
    $check = $conexion->query("SELECT estatus FROM documentos_pc WHERE id = $id_doc LIMIT 1");
    if($check && $check->num_rows > 0) {
        $doc = $check->fetch_assoc();
        $nuevo_estatus = intval($doc['estatus']) === 1 ? 0 : 1;
        $msg = $nuevo_estatus === 0 ? "Expediente archivado correctamente." : "Expediente desarchivado y reactivado con éxito.";
        
        $conexion->query("UPDATE documentos_pc SET estatus = $nuevo_estatus, motivo = '$motivo' WHERE id = $id_doc");
        echo json_encode(["status" => "success", "message" => $msg]);
    } else {
        echo json_encode(["status" => "error", "message" => "Documento no encontrado."]);
    }
    exit;
}

// --- ACCIÓN 5: VER HISTORIAL ---
if ($accion === 'ver_historial_documento') {
    $id_doc = intval($datos['id_documento']);
    $res = $conexion->query("SELECT nombre_personalizado, fecha_vencimiento, nombre_archivo_fisico, fecha_modificacion, subido_por, motivo FROM historial_documentos WHERE documento_id = $id_doc ORDER BY id DESC");
    $historial = [];
    if($res) { while($row = $res->fetch_assoc()) { $historial[] = $row; } }
    echo json_encode(["status" => "success", "data" => $historial]);
    exit;
}

// --- ACCIÓN 6: LISTAR USUARIOS (CORREGIDA PARA EMITIR ALIAS DESDE EMPRESAS_CLIENTES) ---
if ($accion === 'listar_usuarios') {
    $empresa_cod = $conexion->real_escape_string(trim($datos['empresa_cod']));
    $rol_ejecutor = isset($datos['rol_ejecutor']) ? strtolower(trim($datos['rol_ejecutor'])) : '';

    if ($rol_ejecutor === 'tipo 1' || $rol_ejecutor === 'tipo 2' || $rol_ejecutor === 'tipo 3') {
        $res = $conexion->query("SELECT cod, nombre, encargado, director_email, email, email_adicional, telefono_principal, telefono_adicional, direccion, coordenadas, coordenadas_gps, rol, rol AS role, activo FROM empresas_clientes WHERE cod = '$empresa_cod' ORDER BY id DESC");
    } else {
        $base_empresa = explode('/', $empresa_cod)[0];
        $res = $conexion->query("SELECT cod, nombre, encargado, director_email, email, email_adicional, telefono_principal, telefono_adicional, direccion, coordenadas, coordenadas_gps, rol, rol AS role, activo FROM empresas_clientes WHERE cod = '$base_empresa' OR cod LIKE '$base_empresa/%' ORDER BY id DESC");
    }
    
    $usuarios = [];
    if($res) { while($row = $res->fetch_assoc()) { $usuarios[] = $row; } }
    echo json_encode(["status" => "success", "data" => $usuarios]);
    exit;
}

// --- ACCIÓN 7 ---
if ($accion === 'marcar_como_visto') {
    $id_doc  = intval($datos['id_documento']);
    $usuario = $conexion->real_escape_string(trim($datos['usuario_ejecutor']));
    
    $conexion->query("UPDATE documentos_pc SET visto_por = '$usuario' WHERE id = $id_doc");
    echo json_encode(["status" => "success"]);
    exit;
}

// --- ACCIÓN 8: CAMBIAR CONTRASEÑA PROPIA ---
if ($accion === 'cambiar_contrasena_propia') {
    $empresa_cod = $conexion->real_escape_string(trim($datos['empresa_cod']));
    $pass_actual = trim($datos['pass_actual']);
    $pass_nueva  = trim($datos['pass_nueva']);

    if (empty($empresa_cod) || empty($pass_actual) || empty($pass_nueva)) {
        echo json_encode(["status" => "error", "message" => "Todos los campos son obligatorios."]);
        exit;
    }

    $stmt = $conexion->prepare("SELECT pass FROM empresas_clientes WHERE cod = ? LIMIT 1");
    $stmt->bind_param("s", $empresa_cod);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        
        if (password_verify($pass_actual, $row['pass']) || $pass_actual === $row['pass']) {
            $pass_err = validarPasswordComplejidad($pass_nueva);
            if ($pass_err) {
                echo json_encode(["status" => "error", "message" => "Seguridad de Nueva Contraseña: " . $pass_err]);
                exit;
            }

            $pass_hash = password_hash($pass_nueva, PASSWORD_BCRYPT);
            
            $stmtUpdate = $conexion->prepare("UPDATE empresas_clientes SET pass = ? WHERE cod = ?");
            $stmtUpdate->bind_param("ss", $pass_hash, $empresa_cod);
            if ($stmtUpdate->execute()) {
                echo json_encode(["status" => "success", "message" => "¡Contraseña actualizada con éxito!"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Error al actualizar en BD: " . $stmtUpdate->error]);
            }
            $stmtUpdate->close();
        } else {
            echo json_encode(["status" => "error", "message" => "La contraseña actual ingresada es incorrecta."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Cuenta no encontrada."]);
    }
    $stmt->close();
    exit;
}

// --- ACCIÓN 9: OBTENER LOGO DE LA EMPRESA ---
if ($accion === 'obtener_logo_empresa') {
    $empresa_cod = $conexion->real_escape_string(trim($datos['empresa_cod']));
    $base_empresa = explode('/', $empresa_cod)[0];
    
    $stmt = $conexion->prepare("SELECT logo FROM empresas_clientes WHERE cod = ? LIMIT 1");
    $stmt->bind_param("s", $base_empresa);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo json_encode(["status" => "success", "logo" => $row['logo']]);
    } else {
        echo json_encode(["status" => "error", "message" => "Organización no encontrada."]);
    }
    $stmt->close();
    exit;
}

// --- ACCIÓN 10: SUSPENDER NODO O COLABORADOR CLIENTE ---
if ($accion === 'suspender_nodo_cliente') {
    $rol_ejecutor = isset($datos['rol_ejecutor']) ? strtolower(trim($datos['rol_ejecutor'])) : (isset($_POST['rol_ejecutor']) ? strtolower(trim($_POST['rol_ejecutor'])) : '');
    if ($rol_ejecutor !== 'consultor') {
        echo json_encode(["status" => "error", "message" => "No tienes permisos para realizar esta acción."]);
        exit;
    }

    $cod_nodo = isset($datos['cod_nodo']) ? $conexion->real_escape_string(trim($datos['cod_nodo'])) : (isset($_POST['cod_nodo']) ? $conexion->real_escape_string(trim($_POST['cod_nodo'])) : '');
    if (empty($cod_nodo)) {
        echo json_encode(["status" => "error", "message" => "Código de nodo requerido."]);
        exit;
    }

    $check = $conexion->query("SELECT activo FROM empresas_clientes WHERE cod = '$cod_nodo' LIMIT 1");
    if ($check && $check->num_rows > 0) {
        $row = $check->fetch_assoc();
        $nuevo_estatus = intval($row['activo']) === 1 ? 0 : 1;
        $conexion->query("UPDATE empresas_clientes SET activo = $nuevo_estatus WHERE cod = '$cod_nodo'");
        echo json_encode(["status" => "success", "message" => "Estatus del usuario/nodo actualizado con éxito."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Usuario/nodo no encontrado."]);
    }
    exit;
}

// --- ACCIÓN 11: EDITAR NODO O COLABORADOR CLIENTE ---
if ($accion === 'editar_nodo_cliente') {
    $rol_ejecutor = isset($_POST['rol_ejecutor']) ? strtolower(trim($_POST['rol_ejecutor'])) : (isset($datos['rol_ejecutor']) ? strtolower(trim($datos['rol_ejecutor'])) : '');
    if ($rol_ejecutor !== 'consultor') {
        echo json_encode(["status" => "error", "message" => "No tienes permisos para realizar esta acción."]);
        exit;
    }

    $cod_nodo = isset($_POST['cod_nodo']) ? $conexion->real_escape_string(trim($_POST['cod_nodo'])) : (isset($datos['cod_nodo']) ? $conexion->real_escape_string(trim($datos['cod_nodo'])) : '');
    if (empty($cod_nodo)) {
        echo json_encode(["status" => "error", "message" => "Código de nodo requerido para edición."]);
        exit;
    }

    $nombre             = isset($_POST['nombre']) ? $conexion->real_escape_string(trim($_POST['nombre'])) : '';
    $encargado          = isset($_POST['encargado']) ? $conexion->real_escape_string(trim($_POST['encargado'])) : '';
    $email              = isset($_POST['email']) ? $conexion->real_escape_string(trim($_POST['email'])) : '';
    $email_adicional    = isset($_POST['email_adicional']) ? $conexion->real_escape_string(trim($_POST['email_adicional'])) : '';
    $telefono_principal = isset($_POST['telefono_principal']) ? $conexion->real_escape_string(trim($_POST['telefono_principal'])) : '';
    $telefono_adicional = isset($_POST['telefono_adicional']) ? $conexion->real_escape_string(trim($_POST['telefono_adicional'])) : '';
    $direccion          = isset($_POST['direccion']) ? $conexion->real_escape_string(trim($_POST['direccion'])) : '';
    $coordenadas        = isset($_POST['coordenadas']) ? $conexion->real_escape_string(trim($_POST['coordenadas'])) : '';
    $coordenadas_gps    = isset($_POST['coordenadas_gps']) ? $conexion->real_escape_string(trim($_POST['coordenadas_gps'])) : '';
    $director_email     = isset($_POST['director_email']) ? $conexion->real_escape_string(trim($_POST['director_email'])) : '';
    $pass               = isset($_POST['pass']) ? trim($_POST['pass']) : '';
    $rol_nuevo          = isset($_POST['rol']) ? $conexion->real_escape_string(trim($_POST['rol'])) : '';

    if (empty($nombre) || empty($email)) {
        echo json_encode(["status" => "error", "message" => "Nombre y Correo Electrónico son obligatorios."]);
        exit;
    }
    if (!empty($encargado) && empty($coordenadas_gps)) {
        echo json_encode(["status" => "error", "message" => "Las coordenadas (Latitud, Longitud) son obligatorias para la sucursal."]);
        exit;
    }

    $pass_sql = "";
    if (!empty($pass)) {
        $check_err = "";
        if (strlen($pass) < 10) {
            $check_err = "La contraseña debe tener al menos 10 caracteres.";
        } elseif (!preg_match('/[A-Z]/', $pass) || !preg_match('/[a-z]/', $pass) || !preg_match('/[0-9]/', $pass) || !preg_match('/[^a-zA-Z0-9]/', $pass)) {
            $check_err = "La contraseña debe contener mayúsculas, minúsculas, números y caracteres especiales.";
        }
        if (!empty($check_err)) {
            echo json_encode(["status" => "error", "message" => "Seguridad de Contraseña: " . $check_err]);
            exit;
        }
        $pass_hash = password_hash($pass, PASSWORD_BCRYPT);
        $pass_sql = ", pass = '$pass_hash'";
    }

    $logo_sql = "";
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['logo']['tmp_name'];
        $fileName    = $_FILES['logo']['name'];
        $fileSize    = $_FILES['logo']['size'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed_exts = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
        
        if (in_array($ext, $allowed_exts)) {
            if ($fileSize <= 5242880) {
                $logo_dir = __DIR__ . "/../public/uploads/logos/";
                if (!is_dir($logo_dir)) mkdir($logo_dir, 0777, true);
                $logo_nombre_fisico = md5(time() . $fileName) . "." . $ext;
                if (move_uploaded_file($fileTmpPath, $logo_dir . $logo_nombre_fisico)) {
                    $logo_sql = ", logo = '$logo_nombre_fisico'";
                }
            }
        }
    }

    $encargado_val = !empty($encargado) ? "'$encargado'" : "NULL";
    $director_email_val = !empty($director_email) ? "'$director_email'" : "NULL";
    $rol_sql = !empty($rol_nuevo) ? ", rol = '$rol_nuevo'" : "";

    $query = "UPDATE empresas_clientes SET 
                nombre = '$nombre',
                encargado = $encargado_val,
                email = '$email',
                email_adicional = '$email_adicional',
                telefono_principal = '$telefono_principal',
                telefono_adicional = '$telefono_adicional',
                direccion = '$direccion',
                coordenadas = '$coordenadas',
                coordenadas_gps = '$coordenadas_gps',
                director_email = $director_email_val
                $pass_sql
                $logo_sql
                $rol_sql
              WHERE cod = '$cod_nodo'";

    if ($conexion->query($query)) {
        echo json_encode(["status" => "success", "message" => "Los datos de la cuenta se actualizaron exitosamente."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error al actualizar la base de datos: " . $conexion->error]);
    }
    exit;
}
?>