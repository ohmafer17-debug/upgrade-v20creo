<?php
// =================================================================
// PROCESADOR AUTOMÁTICO EN SEGUNDO PLANO: alertas_automaticas.php
// =================================================================

require_once "../config/conexion.php";

$fecha_actual = date('Y-m-d');

// 1. Buscamos los documentos activos con fechas de vencimiento válidas
$sqlDocs = "SELECT id, empresa_cod, tipo_doc, nombre_personalizado, fecha_vencimiento, fecha_subida_sistema 
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

        $enviar_alerta = false;
        $mensaje_estatus = "";
        
        // Evaluamos las alertas según tu matriz
        if ($porcentaje_consumido >= 100 || $timestamp_actual >= $timestamp_vence) {
            $enviar_alerta = true;
            $mensaje_estatus = "VENCIDO CRÍTICO";
        } elseif ($porcentaje_consumido >= 90) {
            $enviar_alerta = true;
            $mensaje_estatus = "PRÓXIMO A VENCER (URGENTE)";
        } elseif ($porcentaje_consumido >= 75) {
            $enviar_alerta = true;
            $mensaje_estatus = "PRÓXIMO A VENCER";
        }

        // 2. Si requiere alerta, localizamos de inmediato los correos de la empresa
        if ($enviar_alerta) {
            $cod_empresa = $doc['empresa_cod'];
            $base_empresa = explode('-', $cod_empresa)[0];
            
            $sqlUsuarios = "SELECT nombre, email, rol FROM empresas_clientes 
                            WHERE cod = '$base_empresa' OR cod LIKE '$base_empresa-%'";
            
            $resUsuarios = $conexion->query($sqlUsuarios);
            
            if ($resUsuarios && $resUsuarios->num_rows > 0) {
                while ($user = $resUsuarios->fetch_assoc()) {
                    
                    $para = $user['email'];
                    $nombre_limpio_doc = preg_replace('/\[Reg: .*?\]/', '', $doc['nombre_personalizado']);
                    $porcentaje_texto = round($porcentaje_consumido, 2) . '%';

                    $asunto = "🚨 ALERTA AUTOMÁTICA UPGRADE SYSTEMS - " . $mensaje_estatus;
                    
                    $mensaje = "Hola " . $user['nombre'] . ",\n\n";
                    $mensaje .= "Te notificamos que un documento obligatorio está en estado crítico:\n\n";
                    $mensaje .= "• Documento: " . $doc['tipo_doc'] . "\n";
                    $mensaje .= "• Detalle: " . $nombre_limpio_doc . "\n";
                    $mensaje .= "• Vence el: " . $fecha_vence . "\n";
                    $mensaje .= "• Tiempo Consumido: " . $porcentaje_texto . "\n\n";
                    $mensaje .= "Por favor, ingresa al portal corporativo para actualizarlo.\n\n";
                    $mensaje .= "Atentamente,\nUpgrade Systems";

                    $cabeceras = "From: no-reply@upgradesystems.com\r\n";
                    $cabeceras .= "X-Mailer: PHP/" . phpversion();

                    mail($para, $asunto, $mensaje, $cabeceras);
                }
            }
        }
    }
}
echo "Escaneo de alertas completado.";
?>