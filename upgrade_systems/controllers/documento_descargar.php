<?php
// =================================================================
// GUARDIÁN DE PRIVACIDAD: documento_descargar.php
// =================================================================
ini_set('display_errors', 0);
error_reporting(0);

// Iniciamos sesión para validar acceso
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "conexion.php";

// Capturamos el archivo físico solicitado desde la URL
$archivo = isset($_GET['archivo']) ? trim($_GET['archivo']) : '';

if (empty($archivo)) {
    die("Error: Parámetro de archivo ausente.");
}

// 🔐 VALIDACIÓN DE SEGURIDAD: Solo permitimos la descarga si viene un token seguro (empresaCod)
// Esto evita que alguien escriba la URL a mano sin haber pasado por el portal
if (!isset($_GET['token_seguro']) || empty($_GET['token_seguro'])) {
    header("HTTP/1.1 403 Forbidden");
    echo "<h1>Acceso Denegado: No tienes una sesión activa en este navegador.</h1>";
    exit;
}

// Sanitización para evitar que alguien intente salir de la carpeta (navegación de directorios)
$archivo_limpio = basename($archivo);
$ruta_completa = "./uploads_dictamenes/" . $archivo_limpio;

// Verificamos que el archivo exista
if (!file_exists($ruta_completa)) {
    header("HTTP/1.1 404 Not Found");
    die("El expediente solicitado no existe en el servidor.");
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