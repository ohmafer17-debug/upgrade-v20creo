<?php
// =================================================================
// GUARDIÁN DE PRIVACIDAD: documento_descargar.php
// =================================================================
ini_set('display_errors', 0);
error_reporting(0);

// Iniciamos sesión para validar acceso
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . "/../config/conexion.php";

// Capturamos el archivo físico solicitado desde la URL
$archivo = isset($_GET['archivo']) ? trim($_GET['archivo']) : '';

if (empty($archivo)) {
    die("Error: Parámetro de archivo ausente.");
}

// 🔐 VALIDACIÓN DE SEGURIDAD REAL DE SESIÓN:
// Validamos que el navegador tenga una cookie PHPSESSID con sesión activa en el servidor PHP
// (sea Staff UPS o un Cliente Corporativo). Esto bloquea el acceso en pestañas de incógnito sin login.
$sesion_cliente = isset($_SESSION['cliente_sesion_id']) ? $_SESSION['cliente_sesion_id'] : '';
$sesion_staff   = isset($_SESSION['ups_sesion_id']) ? $_SESSION['ups_sesion_id'] : '';

if (empty($sesion_cliente) && empty($sesion_staff)) {
    header("HTTP/1.1 403 Forbidden");
    echo "<h1>Acceso Denegado: No tienes una sesión activa en este navegador.</h1>";
    exit;
}

// Sanitización para evitar que alguien intente salir de la carpeta (navegación de directorios)
$archivo_limpio = basename($archivo);

// Consultar la base de datos para obtener el empresa_cod asociado a este archivo físico
$stmt = $conexion->prepare("SELECT empresa_cod FROM documentos_pc WHERE nombre_archivo_fisico = ? LIMIT 1");
$stmt->bind_param("s", $archivo_limpio);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $empresa_cod = $row['empresa_cod'];
} else {
    // Si no está en documentos_pc, buscar en el historial_documentos para contemplar archivos reemplazados
    $stmtHist = $conexion->prepare("SELECT empresa_cod FROM historial_documentos WHERE nombre_archivo_fisico = ? LIMIT 1");
    $stmtHist->bind_param("s", $archivo_limpio);
    $stmtHist->execute();
    $resultHist = $stmtHist->get_result();
    
    if ($resultHist && $resultHist->num_rows > 0) {
        $rowHist = $resultHist->fetch_assoc();
        $empresa_cod = $rowHist['empresa_cod'];
    } else {
        header("HTTP/1.1 404 Not Found");
        die("El expediente solicitado no está registrado en el sistema.");
    }
}

// Calcular el código base
$base_empresa = explode('/', $empresa_cod)[0];
$base_empresa_limpio = preg_replace('/[^a-zA-Z0-9]/', '', $base_empresa);
if (empty($base_empresa_limpio)) {
    $base_empresa_limpio = 'GENERAL';
}

// Ruta absoluta respecto al directorio del script
$ruta_completa = __DIR__ . "/../uploads_dictamenes/" . $base_empresa_limpio . "/" . $archivo_limpio;

// Verificamos que el archivo exista físicamente
if (!file_exists($ruta_completa)) {
    header("HTTP/1.1 404 Not Found");
    die("El expediente solicitado no existe físicamente en el servidor.");
}

// Detectamos el tipo de archivo para enviarlo correctamente
$ext = strtolower(pathinfo($archivo_limpio, PATHINFO_EXTENSION));
$mime = ($ext === "pdf") ? "application/pdf" : "image/jpeg";

// Enviamos el archivo al navegador de forma protegida
header("Content-Type: $mime");
header("Content-Length: " . filesize($ruta_completa));
header("Content-Disposition: inline; filename=\"$archivo_limpio\"");
readfile($ruta_completa);
exit;
?>