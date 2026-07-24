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
$sqlDocs = "SELECT id, empresa_cod, tipo_doc, nombre_personalizado, fecha_vencimiento, fecha_subida_sistema, notificar_correos 
            FROM documentos_pc 
            WHERE estatus = 1 AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento != '0000-00-00'";

$resDocs = $conexion->query($sqlDocs);

if ($resDocs && $resDocs->num_rows > 0) {
    while ($doc = $resDocs->fetch_assoc()) {
        
        $fecha_vence = $doc['fecha_vencimiento'];
        $fecha_subida = $doc['fecha_subida_sistema'];
        
        // Calculamos los días totales y los transcurridos
        $timestamp_subida = strtotime($fecha_subida);
        $timestamp_vence  = strtotime($fecha_vence);
        $timestamp_actual = strtotime($fecha_actual);
        
        $dias_totales_vida  = ($timestamp_vence - $timestamp_subida) / 86400;
        $dias_transcurridos = ($timestamp_actual - $timestamp_subida) / 86400;
        
        $porcentaje_consumido = 0;
        if ($dias_totales_vida > 0) {
            $porcentaje_consumido = ($dias_transcurridos / $dias_totales_vida) * 100;
        }

        $dias_para_vencer = ($timestamp_vence - $timestamp_actual) / 86400;

        $roles_notificados = [];
        $notificar_staff = false;
        $mensaje_estatus = "";
        
        // 🚀 EVALUAMOS LAS ALERTAS SEGÚN LA NUEVA MATRIZ DE SEMÁFOROS
        if ($dias_para_vencer <= 1) {
            // Semáforo Rojo: 1 día o menos para vencer, o ya vencido
            $mensaje_estatus = "CRÍTICO (ROJO)";
            $roles_notificados = ['tipo 3', 'tipo 2', 'tipo 1', 'responsable nacional', 'responsable_nacional', 'consultor'];
            $notificar_staff = true;
        } elseif ($porcentaje_consumido >= 95) {
            // Naranja Alerta 2
            $mensaje_estatus = "PRÓXIMO A VENCER MUY URGENTE (NARANJA 2)";
            $roles_notificados = ['tipo 3', 'tipo 2', 'tipo 1', 'responsable nacional', 'responsable_nacional', 'consultor'];
        } elseif ($porcentaje_consumido >= 85) {
            // Naranja Alerta 1
            $mensaje_estatus = "PRÓXIMO A VENCER URGENTE (NARANJA 1)";
            $roles_notificados = ['tipo 3', 'tipo 2', 'tipo 1', 'responsable nacional', 'responsable_nacional'];
        } elseif ($porcentaje_consumido >= 70) {
            // Amarillo Alerta 2
            $mensaje_estatus = "PRÓXIMO A VENCER (AMARILLO 2)";
            $roles_notificados = ['tipo 3', 'tipo 2', 'tipo 1'];
        } elseif ($porcentaje_consumido >= 50) {
            // Amarillo Alerta 1
            $mensaje_estatus = "PRÓXIMO A VENCER (AMARILLO 1)";
            $roles_notificados = ['tipo 3', 'tipo 2'];
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
            $porcentaje_texto = round($porcentaje_consumido, 2) . '%';
            
            if ($resUsuarios && $resUsuarios->num_rows > 0) {
                while ($user = $resUsuarios->fetch_assoc()) {
                    
                    $para = $user['email'];
                    $asunto = "🚨 ALERTA AUTOMÁTICA XONEXKA - " . $mensaje_estatus;
                    
                    $mensaje = "Estimado/a " . $user['nombre'] . " (Rol: " . $user['rol'] . "),\n\n";
                    $mensaje .= "Le informamos que el siguiente documento obligatorio requiere su atención:\n\n";
                    $mensaje .= "• Documento: " . $doc['tipo_doc'] . "\n";
                    $mensaje .= "• Detalle: " . $nombre_limpio_doc . "\n";
                    $mensaje .= "• Vence el: " . $fecha_vence . "\n\n";
                    $mensaje .= "Solicitamos ingresar al portal corporativo para proceder con su actualización.\n\n";
                    $mensaje .= "Atentamente,\nXonexka";

                    $cabeceras = "From: no-reply@upgradesystems.com\r\n";
                    $cabeceras .= "Content-Type: text/plain; charset=UTF-8\r\n";
                    $cabeceras .= "X-Mailer: PHP/" . phpversion();

                    // Envío de correo
                    mail($para, $asunto, $mensaje, $cabeceras);
                    
                    // Envío de WhatsApp (Bitácora local)
                    $msg_whatsapp = "🚨 ALERTA XONEXKA [{$mensaje_estatus}]: Hola {$user['nombre']}, el documento [{$doc['tipo_doc']}] está próximo a vencer el {$fecha_vence}. Por favor actualízalo.";
                    
                    if (!empty($user['telefono_principal'])) {
                        enviarNotificacionWhatsApp($user['telefono_principal'], $msg_whatsapp);
                    }
                    if (!empty($user['telefono_adicional'])) {
                        enviarNotificacionWhatsApp($user['telefono_adicional'], $msg_whatsapp);
                    }
                }
            }
            $stmtUser->close();

            // Envío de correo a destinatarios adicionales (notificar_correos) a partir de la segunda alerta (Amarillo 2 en adelante)
            $correos_adicionales_str = isset($doc['notificar_correos']) ? trim($doc['notificar_correos']) : '';
            if ($correos_adicionales_str !== '' && $mensaje_estatus !== "PRÓXIMO A VENCER (AMARILLO 1)") {
                $correos_adicionales = explode(',', $correos_adicionales_str);
                foreach ($correos_adicionales as $c_adicional) {
                    $c_adicional = trim($c_adicional);
                    if (filter_var($c_adicional, FILTER_VALIDATE_EMAIL)) {
                        $asunto_adicional = "🚨 ALERTA AUTOMÁTICA XONEXKA - " . $mensaje_estatus;
                        
                        $mensaje_adicional = "Estimado/a,\n\n";
                        $mensaje_adicional .= "Le informamos que el siguiente documento obligatorio requiere su atención:\n\n";
                        $mensaje_adicional .= "• Documento: " . $doc['tipo_doc'] . "\n";
                        $mensaje_adicional .= "• Detalle: " . $nombre_limpio_doc . "\n";
                        $mensaje_adicional .= "• Vence el: " . $fecha_vence . "\n\n";
                        $mensaje_adicional .= "Solicitamos ingresar al portal corporativo para proceder con su actualización.\n\n";
                        $mensaje_adicional .= "Atentamente,\nXonexka";

                        $cabeceras_adicional = "From: no-reply@upgradesystems.com\r\n";
                        $cabeceras_adicional .= "Content-Type: text/plain; charset=UTF-8\r\n";
                        $cabeceras_adicional .= "X-Mailer: PHP/" . phpversion();

                        mail($c_adicional, $asunto_adicional, $mensaje_adicional, $cabeceras_adicional);
                    }
                }
            }

            // Envío al contacto directo de la empresa exactamente un día antes del vencimiento
            $es_un_dia_antes = (round($dias_para_vencer) == 1);
            if ($es_un_dia_antes) {
                $queryDirecto = "SELECT nombre, email, director_email FROM empresas_clientes WHERE cod = ?";
                $stmtDirecto = $conexion->prepare($queryDirecto);
                $stmtDirecto->bind_param("s", $cod_empresa);
                $stmtDirecto->execute();
                $resDirecto = $stmtDirecto->get_result();
                if ($resDirecto && $resDirecto->num_rows > 0) {
                    $rowDirecto = $resDirecto->fetch_assoc();
                    $para_dir = $rowDirecto['email'];
                    if (!empty($rowDirecto['director_email'])) {
                        $para_dir .= ", " . $rowDirecto['director_email'];
                    }
                    $asunto_dir = "🚨 AVISO CRÍTICO: SU DOCUMENTO VENCE MAÑANA - XONEXKA";
                    
                    $mensaje_dir = "Estimado/a " . $rowDirecto['nombre'] . " (Contacto Directo),\n\n";
                    $mensaje_dir .= "Le informamos que el siguiente documento obligatorio está a solo 1 día de vencer:\n\n";
                    $mensaje_dir .= "• Documento: " . $doc['tipo_doc'] . "\n";
                    $mensaje_dir .= "• Detalle: " . $nombre_limpio_doc . "\n";
                    $mensaje_dir .= "• Vence el: " . $fecha_vence . "\n\n";
                    $mensaje_dir .= "Solicitamos ingresar al portal corporativo de forma inmediata para proceder con su actualización y evitar penalizaciones.\n\n";
                    $mensaje_dir .= "Atentamente,\nXonexka";

                    $cabeceras_dir = "From: no-reply@upgradesystems.com\r\n";
                    $cabeceras_dir .= "Content-Type: text/plain; charset=UTF-8\r\n";
                    $cabeceras_dir .= "X-Mailer: PHP/" . phpversion();

                    mail($para_dir, $asunto_dir, $mensaje_dir, $cabeceras_dir);
                }
                $stmtDirecto->close();
            }

            // 🚀 ESCALAMIENTO AL STAFF (ROJO)
            if ($notificar_staff) {
                $resAdmins = $conexion->query("SELECT nombre, email FROM admin_ups WHERE estatus = 'Activo'");
                if ($resAdmins && $resAdmins->num_rows > 0) {
                    while ($adm = $resAdmins->fetch_assoc()) {
                        $para_adm = $adm['email'];
                        $asunto_adm = "🚨 ESCALAMIENTO MÁSTER XONEXKA - " . $mensaje_estatus;
                        
                        $mensaje_adm = "Estimado/a " . $adm['nombre'] . " (Administrador Staff),\n\n";
                        $mensaje_adm .= "Le informamos sobre un escalamiento crítico de vigencia para el documento de la organización " . $base_empresa . ":\n\n";
                        $mensaje_adm .= "• Documento: " . $doc['tipo_doc'] . "\n";
                        $mensaje_adm .= "• Detalle: " . $nombre_limpio_doc . "\n";
                        $mensaje_adm .= "• Vence el: " . $fecha_vence . "\n\n";
                        $mensaje_adm .= "El sistema ha alertado al equipo local sin que se haya registrado actualización del expediente.\n\n";
                        $mensaje_adm .= "Atentamente,\nXonexka";

                        $cabeceras_adm = "From: no-reply@upgradesystems.com\r\n";
                        $cabeceras_adm .= "Content-Type: text/plain; charset=UTF-8\r\n";
                        $cabeceras_adm .= "X-Mailer: PHP/" . phpversion();

                        mail($para_adm, $asunto_adm, $mensaje_adm, $cabeceras_adm);
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