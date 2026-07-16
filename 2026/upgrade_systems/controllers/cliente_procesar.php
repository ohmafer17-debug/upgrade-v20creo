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
    $motivo      = isset($_POST['motivo']) ? $conexion->real_escape_string(trim($_POST['motivo'])) : '';
    $correos_adicionales = isset($_POST['correos_adicionales']) ? $conexion->real_escape_string(trim($_POST['correos_adicionales'])) : '';
    
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
                $checkDoc = $conexion->query("SELECT id, nombre_archivo_fisico, nombre_personalizado, fecha_vencimiento, subido_por, actualizado_por FROM documentos_pc WHERE empresa_cod = '$empresa_cod' AND tipo_doc = '$tipo_doc' LIMIT 1");
                if ($checkDoc && $checkDoc->num_rows > 0) {
                    $docViejo = $checkDoc->fetch_assoc();
                    $dId = $docViejo['id'];
                    $vNom = $conexion->real_escape_string($docViejo['nombre_personalizado']);
                    $vFec_val = is_null($docViejo['fecha_vencimiento']) || empty($docViejo['fecha_vencimiento']) ? "'0000-00-00'" : "'".$docViejo['fecha_vencimiento']."'";
                    $vArc = $docViejo['nombre_archivo_fisico'];
                    
                    $vAutor = !empty($docViejo['actualizado_por']) ? $docViejo['actualizado_por'] : $docViejo['subido_por'];
                    $conexion->query("INSERT INTO historial_documentos (documento_id, empresa_cod, tipo_doc, nombre_personalizado, fecha_vencimiento, nombre_archivo_fisico, subido_por, motivo) VALUES ($dId, '$empresa_cod', '$tipo_doc', '$vNom', $vFec_val, '$vArc', '$vAutor', '$motivo')");
                    
                    $conexion->query("UPDATE documentos_pc SET nombre_personalizado = '$nombre_p', fecha_vencimiento = $vencimiento_sql, fecha_subida_sistema = '$fecha_solo_base', actualizado_por = '$usuario_ejecutor', nombre_archivo_fisico = '$nuevo_nombre_fisico', estatus = 1, motivo = '$motivo', correos_adicionales = '$correos_adicionales' WHERE id = $dId");
                    
                    // 🚀 Alerta inmediata de actualización a todos los roles de la empresa
                    notificarActualizacionTodosLosRoles($conexion, $empresa_cod, $tipo_doc, $nombre_p, $usuario_ejecutor);

                    echo json_encode(["status" => "success", "message" => "¡Documento modificado con éxito! Nuevo semáforo calculado.", "nueva_categoria" => $tipo_doc]);
                    exit;
                }
            }

            $queryDoc = "INSERT INTO documentos_pc (empresa_cod, tipo_doc, nombre_personalizado, fecha_vencimiento, fecha_subida_sistema, subido_por, estatus, nombre_archivo_fisico, motivo, correos_adicionales) 
                         VALUES ('$empresa_cod', '$tipo_doc', '$nombre_p', $vencimiento_sql, '$fecha_solo_base', '$usuario_ejecutor', 1, '$nuevo_nombre_fisico', '$motivo', '$correos_adicionales')";
            
            if ($conexion->query($queryDoc)) {
                // 🚀 Alerta inmediata de actualización a todos los roles de la empresa
                notificarActualizacionTodosLosRoles($conexion, $empresa_cod, $tipo_doc, $nombre_p, $usuario_ejecutor);

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

    $res = $conexion->query("SELECT id, tipo_doc, nombre_personalizado, fecha_vencimiento, fecha_subida_sistema, subido_por, actualizado_por, visto_por, estatus, nombre_archivo_fisico, correos_adicionales FROM documentos_pc WHERE empresa_cod = '$base_empresa' OR empresa_cod LIKE '$base_empresa/%' ORDER BY id DESC");
    
    $documentos = [];
    $fecha_actual = date('Y-m-d');

    if($res) { 
        while($row = $res->fetch_assoc()) {
            $fecha_vencimiento = $row['fecha_vencimiento'];
            $fecha_subida = $row['fecha_subida_sistema'];
            
            $dias_para_vencer = null;
            $color_semaforo = 'green';
            $mensaje_estatus = 'Vigente';
            $escalar_a_admin_ups = false;
            $roles_notificados = [];

            if ($fecha_vencimiento && $fecha_vencimiento !== '0000-00-00') {
                $date_vence = new DateTime($fecha_vencimiento);
                $date_actual = new DateTime($fecha_actual);
                $interval = $date_actual->diff($date_vence);
                $dias_para_vencer = (int)$interval->format('%r%a');
            }

            if (intval($row['estatus']) === 0) {
                $color_semaforo = 'gray'; 
                $mensaje_estatus = 'Archivado / Inactivo';
                $roles_notificados = ['tipo 3', 'tipo 2', 'tipo 1', 'responsable nacional', 'consultor', 'administrador'];
                $escalar_a_admin_ups = true; 
            } elseif ($dias_para_vencer !== null) {
                if ($dias_para_vencer <= 7) {
                    $color_semaforo = 'red'; 
                    $mensaje_estatus = 'Vencido Crítico';
                    $roles_notificados = ['tipo 3', 'tipo 2', 'tipo 1', 'responsable nacional', 'consultor', 'administrador'];
                    $escalar_a_admin_ups = true; 
                } elseif ($dias_para_vencer >= 8 && $dias_para_vencer <= 15) {
                    $color_semaforo = 'orange-strong'; 
                    $mensaje_estatus = 'Próximo a vencer (Crítico)';
                    $roles_notificados = ['tipo 3', 'tipo 2', 'tipo 1', 'responsable nacional'];
                } elseif ($dias_para_vencer >= 16 && $dias_para_vencer <= 30) {
                    $color_semaforo = 'yellow'; 
                    $mensaje_estatus = 'Próximo a vencer';
                    $roles_notificados = ['tipo 3', 'tipo 2'];
                } else {
                    $color_semaforo = 'green'; 
                    $mensaje_estatus = 'Vigente';
                }
            }

            $row['color_calculado']   = $color_semaforo;
            $row['estatus_texto']     = $mensaje_estatus;
            $row['porcentaje_vida']   = ($dias_para_vencer !== null) ? $dias_para_vencer . ' días' : 'N/A';
            $row['roles_alerta']       = $roles_notificados;
            $row['alerta_global_ups'] = $escalar_a_admin_ups;

            $docId = $row['id'];
            $countQuery = $conexion->query("SELECT COUNT(id) as total FROM historial_documentos WHERE documento_id = $docId");
            $countRow = $countQuery->fetch_assoc();
            $row['total_actualizaciones'] = $countRow['total'];

            $row['nombre_limpio'] = preg_replace('/\[Reg: .*?\]/', '', $row['nombre_personalizado']);

            if (in_array(strtolower($rol_ejecutor), $roles_notificados) && $color_semaforo !== 'green' && $color_semaforo !== 'gray') {
                $para = "responsable_infraestructura@upgradesystems.com";
                $asunto = "🚨 ALERTA AUTOMÁTICA DE VENCIMIENTO - " . strtoupper($row['tipo_doc']);
                $mensaje = "Estimado Equipo, se notifica que el documento " . $row['tipo_doc'] . " (" . $row['nombre_limpio'] . ") ha entrado en la fase " . $mensaje_estatus . " con " . $row['porcentaje_vida'] . " vigentes restantes. Requiere revisión inmediata en el sistema.";
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

// --- ACCIÓN 6: LISTAR USUARIOS (CON CORRECCIÓN DE PRIVACIDAD MULTITENANT) ---
if ($accion === 'listar_usuarios') {
    $empresa_cod = $conexion->real_escape_string(trim($datos['empresa_cod']));
    $rol_ejecutor = isset($datos['rol_ejecutor']) ? strtolower(trim($datos['rol_ejecutor'])) : '';
    
    if ($rol_ejecutor === 'consultor') {
        // El consultor ve a toda su organización matriz y sub-empresas
        $base_empresa = explode('/', $empresa_cod)[0];
        $query = "SELECT cod, nombre, email, email_adicional, telefono_principal, telefono_adicional, rol, rol AS role, activo 
                  FROM empresas_clientes 
                  WHERE cod = '$base_empresa' OR cod LIKE '$base_empresa/%' 
                  ORDER BY id DESC";
    } else {
        // Las sub-empresas o colaboradores estándar solo ven su propia sucursal y sus respectivos sub-colaboradores
        $query = "SELECT cod, nombre, email, email_adicional, telefono_principal, telefono_adicional, rol, rol AS role, activo 
                  FROM empresas_clientes 
                  WHERE cod = '$empresa_cod' OR cod LIKE '$empresa_cod/%' 
                  ORDER BY id DESC";
    }
    
    $res = $conexion->query($query);
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

// --- ACCIÓN 10: CREAR SUCURSAL / SUB-EMPRESA DESDE EL CONSULTOR ---
if ($accion === 'crear_sucursal_consultor') {
    $rol_ejecutor = strtolower(trim($_POST['rol_ejecutor']));
    $consultor_cod = trim($_POST['consultor_cod']);
    
    if ($rol_ejecutor !== 'consultor') {
        echo json_encode(["status" => "error", "message" => "Denegado: Su rango operativo no posee privilegios para registrar sucursales."]);
        exit;
    }

    $nombre             = $conexion->real_escape_string(trim($_POST['nombre']));
    $email              = $conexion->real_escape_string(trim($_POST['email']));
    $telefono_principal = $conexion->real_escape_string(trim($_POST['telefono_principal']));
    $telefono_adicional = $conexion->real_escape_string(trim($_POST['telefono_adicional']));
    $direccion          = $conexion->real_escape_string(trim($_POST['direccion']));
    $coordenadas        = $conexion->real_escape_string(trim($_POST['coordenadas']));
    $pass               = $conexion->real_escape_string(trim($_POST['pass']));
    $rn_email           = isset($_POST['rn_email']) ? $conexion->real_escape_string(trim($_POST['rn_email'])) : '';

    if (empty($nombre) || empty($email) || empty($pass) || empty($consultor_cod)) {
        echo json_encode(["status" => "error", "message" => "Existen campos mandatorios incompletos."]);
        exit;
    }

    // Validar contraseña
    $pass_err = validarPasswordComplejidad($pass);
    if ($pass_err) {
        echo json_encode(["status" => "error", "message" => "Seguridad de Contraseña: " . $pass_err]);
        exit;
    }

    // Verificar si el correo ya existe
    $check = $conexion->query("SELECT id FROM empresas_clientes WHERE email = '$email' LIMIT 1");
    if($check && $check->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "El correo electrónico ya pertenece a un nodo registrado."]);
        exit;
    }

    // Autogenerar código secuencial de la sucursal (ej: CONS-01/SUC-A)
    $base_empresa = explode('/', $consultor_cod)[0];
    $prefijo_busqueda = $conexion->real_escape_string($base_empresa . "/SUC-");
    $query_ultimo = "SELECT cod FROM empresas_clientes WHERE cod LIKE '$prefijo_busqueda%' ORDER BY cod DESC LIMIT 1";
    $res_ultimo = $conexion->query($query_ultimo);

    $siguiente_letra = 'A';
    if ($res_ultimo && $res_ultimo->num_rows > 0) {
        $row_ultimo = $res_ultimo->fetch_assoc();
        $ultimo_cod = $row_ultimo['cod'];

        // Extraer la letra de sufijo
        $offset = strlen($base_empresa) + 5; // longitud de BASE + "/SUC-"
        $sufijo_letra = substr($ultimo_cod, $offset);
        if (!empty($sufijo_letra)) {
            $siguiente_letra = ++$sufijo_letra;
        }
    }

    $cod_unico_sucursal = $base_empresa . "/SUC-" . $siguiente_letra;
    $pass_encriptada = password_hash($pass, PASSWORD_BCRYPT);

    // Asignación de Responsable Nacional si es configurado
    $dueno_nombre = "NULL";
    $dueno_email = "NULL";
    $rol_sucursal = 'Tipo 1';
    if (!empty($rn_email) && $rn_email !== 'NINGUNO') {
        $resRN = $conexion->query("SELECT nombre FROM empresas_clientes WHERE email = '$rn_email' LIMIT 1");
        if ($resRN && $resRN->num_rows > 0) {
            $rowRN = $resRN->fetch_assoc();
            $dueno_nombre = "'" . $conexion->real_escape_string($rowRN['nombre']) . "'";
            $dueno_email = "'" . $rn_email . "'";
            $nombre .= " (RN: " . $rn_email . ")";
            $rol_sucursal = 'Responsable Nacional';
        }
    }

    $queryInsert = "INSERT INTO empresas_clientes (cod, nombre, email, telefono_principal, telefono_adicional, direccion, coordenadas, pass, activo, rol, dueño_director_nombre, dueño_director_email) 
                    VALUES ('$cod_unico_sucursal', '$nombre', '$email', '$telefono_principal', '$telefono_adicional', '$direccion', '$coordenadas', '$pass_encriptada', 1, '$rol_sucursal', $dueno_nombre, $dueno_email)";

    if ($conexion->query($queryInsert)) {
        echo json_encode(["status" => "success", "message" => "¡Sucursal registrada con éxito! Código asignado: " . $cod_unico_sucursal]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error al registrar en BD: " . $conexion->error]);
    }
    exit;
}

// --- ACCIÓN 11: LISTAR SUCURSALES DEL CONSULTOR ---
if ($accion === 'listar_sucursales_consultor') {
    $consultor_cod = isset($datos['consultor_cod']) ? $conexion->real_escape_string(trim($datos['consultor_cod'])) : '';
    if (empty($consultor_cod)) {
        echo json_encode(["status" => "error", "message" => "Código de consultor requerido."]);
        exit;
    }
    
    $base_empresa = explode('/', $consultor_cod)[0];
    $prefijo_busqueda = $base_empresa . "/SUC-%";
    
    $res = $conexion->query("SELECT id, cod, nombre, email, telefono_principal, telefono_adicional, direccion, coordenadas, activo FROM empresas_clientes WHERE cod LIKE '$prefijo_busqueda' ORDER BY id DESC");
    
    $sucursales = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $sucursales[] = $row;
        }
    }
    echo json_encode(["status" => "success", "data" => $sucursales]);
    exit;
}

// --- ACCIÓN 12: CAMBIAR ESTATUS DE LA SUCURSAL ---
if ($accion === 'estatus_sucursal_consultor') {
    $id = isset($datos['id']) ? (int)$datos['id'] : 0;
    $nuevo_estatus = isset($datos['activo']) ? (int)$datos['activo'] : 1;
    
    $stmt = $conexion->prepare("UPDATE empresas_clientes SET activo = ? WHERE id = ?");
    $stmt->bind_param("ii", $nuevo_estatus, $id);
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "El estatus de la sucursal ha sido actualizado."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error al actualizar estatus: " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// --- ACCIÓN 13: LISTAR RESPONSABLES NACIONALES DEL CONSULTOR ---
if ($accion === 'listar_responsables_nacionales_consultor') {
    $consultor_cod = isset($datos['consultor_cod']) ? $conexion->real_escape_string(trim($datos['consultor_cod'])) : '';
    if (empty($consultor_cod)) {
        echo json_encode(["status" => "error", "message" => "Código de consultor requerido."]);
        exit;
    }

    $base_empresa = explode('/', $consultor_cod)[0];
    // Únicamente los de rango 'Responsable Nacional' creados por este consultor
    $query = "SELECT cod, nombre, email FROM empresas_clientes 
              WHERE (cod LIKE '$base_empresa/%') 
              AND (LOWER(rol) = 'responsable nacional' OR LOWER(rol) = 'responsable_nacional')
              ORDER BY nombre ASC";
    $res = $conexion->query($query);
    
    $responsables = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $responsables[] = $row;
        }
    }
    echo json_encode(["status" => "success", "data" => $responsables]);
    exit;
}

// =================================================================
// 🚀 FUNCIONES AUXILIARES PARA NOTIFICACIONES Y AUDITORÍA
// =================================================================
function notificarActualizacionTodosLosRoles($conexion, $empresa_cod, $tipo_doc, $nombre_documento, $usuario_ejecutor) {
    $base_empresa = explode('/', $empresa_cod)[0];
    $filtro_like = $base_empresa . "/%";
    
    // 1. Obtener todos los roles asociados a esta empresa (incluidos consultores, responsables y tipos 1,2,3)
    $stmt = $conexion->prepare("SELECT nombre, email, rol, telefono_principal, telefono_adicional FROM empresas_clientes WHERE cod = ? OR cod LIKE ?");
    $stmt->bind_param("ss", $base_empresa, $filtro_like);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $nombre_limpio_doc = preg_replace('/\[Reg: .*?\]/', '', $nombre_documento);
    
    if ($res && $res->num_rows > 0) {
        while ($user = $res->fetch_assoc()) {
            $para = $user['email'];
            if (empty($para)) continue;
            
            $asunto = "📄 NUEVO DOCUMENTO SUBIDO / ACTUALIZADO - " . $base_empresa;
            
            $mensaje = "Hola " . $user['nombre'] . " (Rol: " . $user['rol'] . "),\n\n";
            $mensaje .= "Te notificamos que un documento ha sido actualizado/subido en el expediente de tu organización por el usuario " . $usuario_ejecutor . ":\n\n";
            $mensaje .= "• Documento: " . $tipo_doc . "\n";
            $mensaje .= "• Versión: " . $nombre_limpio_doc . "\n";
            $mensaje .= "• Fecha de Registro: " . date('Y-m-d H:i:s') . "\n\n";
            $mensaje .= "Puedes ingresar al portal corporativo para revisar y descargar el archivo.\n\n";
            $mensaje .= "Atentamente,\nUpgrade Systems";

            $cabeceras = "From: no-reply@upgradesystems.com\r\n";
            $cabeceras .= "X-Mailer: PHP/" . phpversion();

            // Enviar correo
            mail($para, $asunto, $mensaje, $cabeceras);
            
            // Enviar WhatsApp (Bitácora local)
            $msg_whatsapp = "📄 ACTUALIZACIÓN DE DOCUMENTO: Hola {$user['nombre']}, se ha cargado un nuevo archivo [{$tipo_doc}] ({$nombre_limpio_doc}) por {$usuario_ejecutor}.";
            
            if (!empty($user['telefono_principal'])) {
                enviarWhatsAppAuditoria($user['telefono_principal'], $msg_whatsapp);
            }
            if (!empty($user['telefono_adicional'])) {
                enviarWhatsAppAuditoria($user['telefono_adicional'], $msg_whatsapp);
            }
        }
    }
    $stmt->close();
}

function enviarWhatsAppAuditoria($telefono, $mensaje) {
    if (empty($telefono)) return false;
    $telefono_limpio = preg_replace('/[^0-9]/', '', $telefono);
    if (empty($telefono_limpio)) return false;
    
    $log_file = __DIR__ . "/../scratch/whatsapp_alertas_log.txt";
    $fecha = date('Y-m-d H:i:s');
    $registro = "[$fecha] [Para: $telefono_limpio] MENSAJE: $mensaje\n";
    file_put_contents($log_file, $registro, FILE_APPEND);
    return true;
}
?>