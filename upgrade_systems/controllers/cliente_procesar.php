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

$inputRaw = file_get_contents("php://input");
$datos = json_decode($inputRaw, true);

$accion = isset($_POST['accion']) ? $_POST['accion'] : (isset($datos['accion']) ? $datos['accion'] : '');

// --- ACCIÓN 1: CREAR NODO OPERATIVO ---
if ($accion === 'crear_usuario_operativo') {
    $rol_ejecutor = strtolower(trim($datos['rol_ejecutor']));
    
    if ($rol_ejecutor !== 'administrador' && $rol_ejecutor !== 'consultor' && $rol_ejecutor !== 'responsable nacional' && $rol_ejecutor !== 'responsable_nacional' && $rol_ejecutor !== 'tipo 1') {
        echo json_encode(["status" => "error", "message" => "Denegado: Su rango operativo no posee permisos para crear nodos de estructura."]);
        exit;
    }

    $nombre             = $conexion->real_escape_string(trim($datos['nombre']));
    $rol_a_crear        = $conexion->real_escape_string(trim($datos['rol']));
    $email              = $conexion->real_escape_string(trim($datos['email']));
    $email_adicional    = $conexion->real_escape_string(trim($datos['email_adicional']));
    $telefono_principal = $conexion->real_escape_string(trim($datos['telefono_principal']));
    $telefono_adicional = $conexion->real_escape_string(trim($datos['telefono_adicional']));
    $pass               = $conexion->real_escape_string(trim($datos['pass']));
    $empresa_cod        = $conexion->real_escape_string(trim($datos['empresa_cod']));

    if (empty($nombre) || empty($rol_a_crear) || empty($email) || empty($pass) || empty($empresa_cod)) {
        echo json_encode(["status" => "error", "message" => "Existen campos mandatorios incompletos."]);
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

    // Obtener la raíz de la empresa (ej: CONS-01 de CONS-01/RNA o CONS-01)
    $base_empresa = explode('/', $empresa_cod)[0];

    // Mapear el rol a su abreviatura o palabra correspondiente
    $rol_limpio = strtolower($rol_a_crear);
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

    // Buscar el último código asignado en la BD con ese prefijo (ej: CONS-01/T1%)
    $prefijo_busqueda = $conexion->real_escape_string($base_empresa . "/" . $abreviatura);
    $query_ultimo = "SELECT cod FROM empresas_clientes WHERE cod LIKE '$prefijo_busqueda%' ORDER BY cod DESC LIMIT 1";
    $res_ultimo = $conexion->query($query_ultimo);

    $siguiente_letra = 'A';
    if ($res_ultimo && $res_ultimo->num_rows > 0) {
        $row_ultimo = $res_ultimo->fetch_assoc();
        $ultimo_cod = $row_ultimo['cod'];

        // Extraer la letra de sufijo
        $offset = strlen($base_empresa) + 1 + strlen($abreviatura); // longitud de BASE + "/" + ABREV
        $sufijo_letra = substr($ultimo_cod, $offset);
        if (!empty($sufijo_letra)) {
            $siguiente_letra = ++$sufijo_letra;
        }
    }

    $cod_unico_nodo = $base_empresa . "/" . $abreviatura . $siguiente_letra;
    $pass_encriptada = password_hash($pass, PASSWORD_BCRYPT);

    $queryInsert = "INSERT INTO empresas_clientes (cod, nombre, email, email_adicional, telefono_principal, telefono_adicional, pass, activo, rol) 
                    VALUES ('$cod_unico_nodo', '$nombre', '$email', '$email_adicional', '$telefono_principal', '$telefono_adicional', '$pass_encriptada', 1, '$rol_a_crear')";

    if ($conexion->query($queryInsert)) {
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
    
    $vencimiento = isset($_POST['fecha_vencimiento']) ? trim($_POST['fecha_vencimiento']) : '';
    $vencimiento_sql = empty($vencimiento) ? "'0000-00-00'" : "'" . $conexion->real_escape_string($vencimiento) . "'";
    
    $fecha_actual_sistema = date('Y-m-d'); 
    $fecha_subida_manual = (isset($_POST['fecha_subida']) && !empty($_POST['fecha_subida'])) ? trim($_POST['fecha_subida']) : date('Y-m-d H:i:s');
    $fecha_solo_base = trim(explode(' ', $fecha_subida_manual)[0]);

    $nombre_p .= " [Reg: " . $fecha_subida_manual . "]";

    $es_actualizacion = isset($_POST['es_actualizacion']) ? $_POST['es_actualizacion'] : 'no';

    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['archivo']['tmp_name'];
        $fileName    = $_FILES['archivo']['name'];
        $fileSize    = $_FILES['archivo']['size'];
        
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $extensiones_permitidas = ['pdf', 'jpg', 'jpeg', 'png'];

        if (!in_array($ext, $extensiones_permitidas)) {
            echo json_encode(["status" => "error", "message" => "Formato no válido. Solo se admiten PDFs o Imágenes."]);
            exit;
        }

        if ($fileSize > 52428800) {
            echo json_encode(["status" => "error", "message" => "El archivo supera el límite de 50MB permitido."]);
            exit;
        }

        // Obtener código base de la organización (ej: CONS-01/RNA -> CONS-01)
        $base_empresa = explode('/', $empresa_cod)[0];
        $base_empresa_limpio = preg_replace('/[^a-zA-Z0-9]/', '', $base_empresa);
        if (empty($base_empresa_limpio)) {
            $base_empresa_limpio = 'GENERAL';
        }

        // Guardar físicamente en uploads_dictamenes/<BASE_CODE>/ en la raíz del proyecto
        $dest_path = __DIR__ . "/../uploads_dictamenes/" . $base_empresa_limpio . "/";
        if (!is_dir($dest_path)) { 
            mkdir($dest_path, 0777, true); 
        }

        $nuevo_nombre_fisico = md5(time() . $fileName) . "." . $ext;
        $ruta_final = $dest_path . $nuevo_nombre_fisico;

        if (move_uploaded_file($fileTmpPath, $ruta_final)) {
            
            if ($es_actualizacion === 'si' || $_POST['es_actualizacion'] === 'si') {
                $checkDoc = $conexion->query("SELECT id, nombre_archivo_fisico, nombre_personalizado, fecha_vencimiento FROM documentos_pc WHERE empresa_cod = '$empresa_cod' AND tipo_doc = '$tipo_doc' LIMIT 1");
                if ($checkDoc && $checkDoc->num_rows > 0) {
                    $docViejo = $checkDoc->fetch_assoc();
                    $dId = $docViejo['id'];
                    $vNom = $conexion->real_escape_string($docViejo['nombre_personalizado']);
                    $vFec_val = is_null($docViejo['fecha_vencimiento']) || empty($docViejo['fecha_vencimiento']) ? "'0000-00-00'" : "'".$docViejo['fecha_vencimiento']."'";
                    $vArc = $docViejo['nombre_archivo_fisico'];
                    
                    $conexion->query("INSERT INTO historial_documentos (documento_id, empresa_cod, tipo_doc, nombre_personalizado, fecha_vencimiento, nombre_archivo_fisico) VALUES ($dId, '$empresa_cod', '$tipo_doc', '$vNom', $vFec_val, '$vArc')");
                    
                    $conexion->query("UPDATE documentos_pc SET nombre_personalizado = '$nombre_p', fecha_vencimiento = $vencimiento_sql, fecha_subida_sistema = '$fecha_solo_base', actualizado_por = '$usuario_ejecutor', nombre_archivo_fisico = '$nuevo_nombre_fisico', estatus = 1 WHERE id = $dId");
                    
                    echo json_encode(["status" => "success", "message" => "¡Documento modificado con éxito! Nuevo semáforo calculado.", "nueva_categoria" => $tipo_doc]);
                    exit;
                }
            }

            $queryDoc = "INSERT INTO documentos_pc (empresa_cod, tipo_doc, nombre_personalizado, fecha_vencimiento, fecha_subida_sistema, subido_por, estatus, nombre_archivo_fisico) 
                         VALUES ('$empresa_cod', '$tipo_doc', '$nombre_p', $vencimiento_sql, '$fecha_solo_base', '$usuario_ejecutor', 1, '$nuevo_nombre_fisico')";
            
            if ($conexion->query($queryDoc)) {
                echo json_encode(["status" => "success", "message" => "¡Archivo guardado e indexado con éxito!", "nueva_categoria" => $tipo_doc]);
            } else {
                echo json_encode(["status" => "error", "message" => "Error al registrar en BD: " . $conexion->error]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "No se pudo mover el archivo al servidor."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Archivo no recibido o dañado."]);
    }
    exit;
}

// --- ACCIÓN 3: LISTAR DOCUMENTOS ---
if ($accion === 'listar_documentos') {
    $rol_ejecutor = strtolower(trim($datos['rol_ejecutor']));
    if ($rol_ejecutor === 'administrador') return;

    $empresa_cod = $conexion->real_escape_string(trim($datos['empresa_cod']));
    $base_empresa = explode('/', $empresa_cod)[0];

    $res = $conexion->query("SELECT id, tipo_doc, nombre_personalizado, fecha_vencimiento, fecha_subida_sistema, subido_por, actualizado_por, visto_por, estatus, nombre_archivo_fisico FROM documentos_pc WHERE empresa_cod = '$base_empresa' OR empresa_cod LIKE '$base_empresa/%' ORDER BY id DESC");
    
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
                $mensaje = "Estimado Equipo, se notifica que el documento " . $row['tipo_doc'] . " (" . $row['nombre_limpio'] . ") ha entrado en la fase " . $mensaje_estatus . " con un " . $row['porcentaje_vida'] . " de tiempo consumido. Requiere revisión inmediata en el sistema.";
                $cabeceras = "From: no-reply@upgradesystems.com\r\nReply-To: no-reply@upgradesystems.com\r\nX-Mailer: PHP/" . phpversion();
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
    
    $check = $conexion->query("SELECT estatus FROM documentos_pc WHERE id = $id_doc LIMIT 1");
    if($check && $check->num_rows > 0) {
        $doc = $check->fetch_assoc();
        $nuevo_estatus = intval($doc['estatus']) === 1 ? 0 : 1;
        $msg = $nuevo_estatus === 0 ? "Expediente archivado correctamente." : "Expediente desarchivado y reactivado con éxito.";
        
        $conexion->query("UPDATE documentos_pc SET estatus = $nuevo_estatus WHERE id = $id_doc");
        echo json_encode(["status" => "success", "message" => $msg]);
    } else {
        echo json_encode(["status" => "error", "message" => "Documento no encontrado."]);
    }
    exit;
}

// --- ACCIÓN 5: VER HISTORIAL ---
if ($accion === 'ver_historial_documento') {
    $id_doc = intval($datos['id_documento']);
    $res = $conexion->query("SELECT nombre_personalizado, fecha_vencimiento, nombre_archivo_fisico, fecha_modificacion FROM historial_documentos WHERE documento_id = $id_doc ORDER BY id DESC");
    $historial = [];
    if($res) { while($row = $res->fetch_assoc()) { $historial[] = $row; } }
    echo json_encode(["status" => "success", "data" => $historial]);
    exit;
}

// --- ACCIÓN 6: LISTAR USUARIOS (CORREGIDA PARA EMITIR ALIAS DESDE EMPRESAS_CLIENTES) ---
if ($accion === 'listar_usuarios') {
    $empresa_cod = $conexion->real_escape_string(trim($datos['empresa_cod']));
    $base_empresa = explode('/', $empresa_cod)[0];
    
    // 🚀 Extraemos de la tabla empresas_clientes filtrando de forma segura por el código base organizacional /
    $res = $conexion->query("SELECT cod, nombre, email, email_adicional, telefono_principal, telefono_adicional, rol, rol AS role FROM empresas_clientes WHERE cod = '$base_empresa' OR cod LIKE '$base_empresa/%' ORDER BY id DESC");
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
?>