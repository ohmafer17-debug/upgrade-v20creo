<?php
// =================================================================
// PROCESADOR AUTOMÁTICO EN SEGUNDO PLANO: alertas_automaticas.php
// =================================================================
header("Content-Type: text/plain; charset=UTF-8");
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== INICIANDO ESCANEO AUTOMÁTICO DE ALERTAS ===\n\n";

require_once __DIR__ . "/../config/conexion.php";

$fecha_actual = date('Y-m-d');

// 1. Buscamos los documentos activos con fechas de vencimiento válidas
$sqlDocs = "SELECT id, empresa_cod, tipo_doc, nombre_personalizado, fecha_vencimiento, fecha_subida_sistema, correos_adicionales 
            FROM documentos_pc 
            WHERE estatus = 1 AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento != '0000-00-00'";

$resDocs = $conexion->query($sqlDocs);

if ($resDocs && $resDocs->num_rows > 0) {
    while ($doc = $resDocs->fetch_assoc()) {
        
        $fecha_vence = $doc['fecha_vencimiento'];
        $fecha_subida = $doc['fecha_subida_sistema'];
        
        // Calculamos los días restantes
        $dias_para_vencer = null;
        if ($fecha_vence && $fecha_vence !== '0000-00-00') {
            $date_vence = new DateTime($fecha_vence);
            $date_actual = new DateTime($fecha_actual);
            $interval = $date_actual->diff($date_vence);
            $dias_para_vencer = (int)$interval->format('%r%a');
        }

        $roles_notificados = [];
        $notificar_staff = false;
        $mensaje_estatus = "";
        
        // 🚀 EVALUAMOS LAS ALERTAS SEGÚN LA NUEVA MATRIZ DE SEMÁFOROS POR DÍAS ABSOLUTOS
        if ($dias_para_vencer !== null) {
            if ($dias_para_vencer <= 7) {
                // Semáforo Rojo: 7 días o menos, o ya vencido
                $mensaje_estatus = "CRÍTICO (ROJO)";
                $roles_notificados = ['tipo 3', 'tipo 2', 'tipo 1', 'responsable nacional', 'responsable_nacional', 'consultor'];
                $notificar_staff = true;
            } elseif ($dias_para_vencer >= 8 && $dias_para_vencer <= 15) {
                // Naranja Alerta 2 (Crítico / Naranja Fuerte)
                $mensaje_estatus = "CRÍTICO (NARANJA FUERTE)";
                $roles_notificados = ['tipo 3', 'tipo 2', 'tipo 1', 'responsable nacional', 'responsable_nacional'];
            } elseif ($dias_para_vencer >= 16 && $dias_para_vencer <= 30) {
                // Naranja Alerta 1
                $mensaje_estatus = "PRÓXIMO A VENCER (NARANJA)";
                $roles_notificados = ['tipo 3', 'tipo 2'];
            }
        }

        // 2. Si requiere alerta, localizamos de inmediato los usuarios con los roles correspondientes
        if ($mensaje_estatus !== "") {
            $cod_empresa = $doc['empresa_cod'];
            
            // 🚀 AISLAMIENTO DE INQUILINOS POR BARRA INCLINADA
            $base_empresa = explode('/', $cod_empresa)[0];
            $filtro_like = $base_empresa . "/%";
            
            $roles_sql = "'" . implode("','", $roles_notificados) . "'";
            
            $queryUsers = "SELECT nombre, email, rol, telefono_principal, telefono_adicional 
                           FROM empresas_clientes 
                           WHERE (cod = ? OR cod LIKE ?) AND LOWER(rol) IN ($roles_sql)";
            
            $stmtUser = $conexion->prepare($queryUsers);
            $stmtUser->bind_param("ss", $base_empresa, $filtro_like);
            $stmtUser->execute();
            $resUsuarios = $stmtUser->get_result();
            
            $nombre_limpio_doc = preg_replace('/\[Reg: .*?\]/', '', $doc['nombre_personalizado']);
            $dias_texto = ($dias_para_vencer !== null) ? $dias_para_vencer . ' días' : 'N/A';
            
            if ($resUsuarios && $resUsuarios->num_rows > 0) {
                while ($user = $resUsuarios->fetch_assoc()) {
                    
                    $para = $user['email'];
                    $asunto = "🚨 ALERTA AUTOMÁTICA UPGRADE SYSTEMS - " . $mensaje_estatus;
                    
                    $mensaje = "Hola " . $user['nombre'] . " (Rol: " . $user['rol'] . "),\n\n";
                    $mensaje .= "Te notificamos que un documento obligatorio requiere atención de tu parte:\n\n";
                    $mensaje .= "• Documento: " . $doc['tipo_doc'] . "\n";
                    $mensaje .= "• Detalle: " . $nombre_limpio_doc . "\n";
                    $mensaje .= "• Vence el: " . $fecha_vence . "\n";
                    $mensaje .= "• Tiempo Restante: " . $dias_texto . "\n\n";
                    $mensaje .= "Por favor, ingresa al portal corporativo para actualizarlo.\n\n";
                    $mensaje .= "Atentamente,\nUpgrade Systems";

                    $cabeceras = "From: no-reply@upgradesystems.com\r\n";
                    $cabeceras .= "X-Mailer: PHP/" . phpversion();

                    // Envío de correo
                    mail($para, $asunto, $mensaje, $cabeceras);
                    
                    // Envío de WhatsApp (Bitácora local)
                    $msg_whatsapp = "🚨 ALERTA UPGRADE SYSTEMS [{$mensaje_estatus}]: Hola {$user['nombre']}, el documento [{$doc['tipo_doc']}] está próximo a vencer el {$fecha_vence} ({$dias_texto} restantes). Por favor actualízalo.";
                    
                    if (!empty($user['telefono_principal'])) {
                        enviarNotificacionWhatsApp($user['telefono_principal'], $msg_whatsapp);
                    }
                    if (!empty($user['telefono_adicional'])) {
                        enviarNotificacionWhatsApp($user['telefono_adicional'], $msg_whatsapp);
                    }
                }
            }
            $stmtUser->close();

            // 🚀 NOTIFICAR AL DUEÑO / DIRECTOR GENERAL DE LA EMPRESA RAÍZ
            $stmtDueno = $conexion->prepare("SELECT dueño_director_nombre, dueño_director_email, telefono_principal, telefono_adicional FROM empresas_clientes WHERE cod = ? LIMIT 1");
            $stmtDueno->bind_param("s", $base_empresa);
            $stmtDueno->execute();
            $resDueno = $stmtDueno->get_result();
            if ($resDueno && $resDueno->num_rows > 0) {
                $rowDueno = $resDueno->fetch_assoc();
                $dueno_email = isset($rowDueno['dueño_director_email']) ? trim($rowDueno['dueño_director_email']) : '';
                $dueno_nombre = isset($rowDueno['dueño_director_nombre']) ? trim($rowDueno['dueño_director_nombre']) : '';
                if (!empty($dueno_email)) {
                    $para = $dueno_email;
                    $asunto = "🚨 ALERTA AUTOMÁTICA UPGRADE SYSTEMS - " . $mensaje_estatus;
                    
                    $mensaje = "Hola " . $dueno_nombre . " (Dueño / Director General),\n\n";
                    $mensaje .= "Le notificamos que un documento obligatorio de su organización requiere atención:\n\n";
                    $mensaje .= "• Documento: " . $doc['tipo_doc'] . "\n";
                    $mensaje .= "• Detalle: " . $nombre_limpio_doc . "\n";
                    $mensaje .= "• Vence el: " . $fecha_vence . "\n";
                    $mensaje .= "• Tiempo Restante: " . $dias_texto . "\n\n";
                    $mensaje .= "Por favor, instruya al equipo correspondiente ingresar al portal corporativo para actualizarlo.\n\n";
                    $mensaje .= "Atentamente,\nUpgrade Systems";

                    $cabeceras = "From: no-reply@upgradesystems.com\r\n";
                    $cabeceras .= "X-Mailer: PHP/" . phpversion();

                    mail($para, $asunto, $mensaje, $cabeceras);
                    
                    // Envío de WhatsApp al Dueño/Director si tiene números registrados
                    $msg_whatsapp_dueno = "🚨 ALERTA UPGRADE SYSTEMS [{$mensaje_estatus}]: Estimado {$dueno_nombre}, el documento [{$doc['tipo_doc']}] de su empresa está próximo a vencer el {$fecha_vence} ({$dias_texto} restantes).";
                    if (!empty($rowDueno['telefono_principal'])) {
                        enviarNotificacionWhatsApp($rowDueno['telefono_principal'], $msg_whatsapp_dueno);
                    }
                    if (!empty($rowDueno['telefono_adicional'])) {
                        enviarNotificacionWhatsApp($rowDueno['telefono_adicional'], $msg_whatsapp_dueno);
                    }
                }
            }
            $stmtDueno->close();

            // 🚀 NOTIFICAR A CORREOS ADICIONALES (Destinatarios ajenos)
            // Se les notifica a partir de la fase crítica de Naranja Fuerte (días <= 15)
            if ($dias_para_vencer !== null && $dias_para_vencer <= 15) {
                $correos_raw = isset($doc['correos_adicionales']) ? trim($doc['correos_adicionales']) : '';
                if (!empty($correos_raw)) {
                    $lista_destinatarios_ajenos = array_filter(array_map('trim', explode(',', $correos_raw)));
                    foreach ($lista_destinatarios_ajenos as $correo_ajeno) {
                        if (empty($correo_ajeno)) continue;
                        
                        $para_ajeno = $correo_ajeno;
                        $asunto_ajeno = "🚨 AVISO DE VENCIMIENTO DE EXPEDIENTE - " . $base_empresa . " (" . $mensaje_estatus . ")";
                        
                        $mensaje_ajeno = "Estimado Destinatario Externo,\n\n";
                        $mensaje_ajeno .= "Le notificamos que el documento obligatorio adjunto al expediente de la organización " . $base_empresa . " requiere renovación:\n\n";
                        $mensaje_ajeno .= "• Documento: " . $doc['tipo_doc'] . "\n";
                        $mensaje_ajeno .= "• Versión: " . $nombre_limpio_doc . "\n";
                        $mensaje_ajeno .= "• Fecha de Vencimiento: " . $fecha_vence . "\n";
                        $mensaje_ajeno .= "• Tiempo de Vigencia Consumido: " . $porcentaje_texto . "\n\n";
                        $mensaje_ajeno .= "Este es un correo automático de control de expediente. Favor de contactar a la organización para proceder a su actualización.\n\n";
                        $mensaje_ajeno .= "Atentamente,\nControl de Cumplimiento Upgrade Systems";

                        $cabeceras_ajeno = "From: no-reply@upgradesystems.com\r\n";
                        $cabeceras_ajeno .= "X-Mailer: PHP/" . phpversion();

                        mail($para_ajeno, $asunto_ajeno, $mensaje_ajeno, $cabeceras_ajeno);
                    }
                }
            }
        }
    }
}

echo "Escaneo de alertas completado exitosamente.\n";

// =================================================================
// 🚀 FUNCIÓN MODULAR PARA NOTIFICACIONES AUTOMÁTICAS DE WHATSAPP
// =================================================================
function enviarNotificacionWhatsApp($telefono, $mensaje) {
    if (empty($telefono)) return false;
    
    // Sanitizar número telefónico
    $telefono_limpio = preg_replace('/[^0-9]/', '', $telefono);
    if (empty($telefono_limpio)) return false;
    
    // Log local de auditoría en scratch/whatsapp_alertas_log.txt
    $log_dir = __DIR__ . "/../scratch/";
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    $log_file = $log_dir . "whatsapp_alertas_log.txt";
    
    $fecha = date('Y-m-d H:i:s');
    $registro = "[$fecha] [Para: $telefono_limpio] MENSAJE: $mensaje\n";
    file_put_contents($log_file, $registro, FILE_APPEND);
    
    // =================================================================
    // 💡 INTEGRACIÓN CON TWILIO O PROVEEDORES (Futuro):
    // Cuando contrates un proveedor de WhatsApp Business, descomenta y 
    // configura este bloque de conexión:
    // =================================================================
    /*
    $api_url = "https://api.tu-proveedor.com/send";
    $payload = [
        "to" => $telefono_limpio,
        "message" => $mensaje
    ];
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    */
    
    return true;
}
?>