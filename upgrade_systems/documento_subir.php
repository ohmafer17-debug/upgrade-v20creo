<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once "conexion.php";

// Validar que la petición venga con archivos y datos
if (!isset($_FILES['pdf_archivo']) || !isset($_POST['nombre_doc']) || !isset($_POST['fecha_vencimiento']) || !isset($_POST['empresa_cod'])) {
    echo json_encode(["status" => "error", "message" => "Faltan campos obligatorios para subir el documento."]);
    exit;
}

$nombre_doc = $conexion->real_escape_string(trim($_POST['nombre_doc']));
$fecha_vencimiento = $conexion->real_escape_string(trim($_POST['fecha_vencimiento']));
$empresa_cod = $conexion->real_escape_string(trim($_POST['empresa_cod']));

$archivo = $_FILES['pdf_archivo'];
$nombre_original = $archivo['name'];
$ruta_temporal = $archivo['tmp_name'];
$error_archivo = $archivo['error'];
$tamano_archivo = $archivo['size']; // Tamaño en bytes

// 1. Validar errores físicos de subida
if ($error_archivo !== UPLOAD_ERR_OK) {
    echo json_encode(["status" => "error", "message" => "Error físico en el servidor temporal. Código: " . $error_archivo]);
    exit;
}

// 2. Validar tamaño máximo (50 MB = 52428800 Bytes)
$max_size = 50 * 1024 * 1024; 
if ($tamano_archivo > $max_size) {
    echo json_encode(["status" => "error", "message" => "El archivo excede el límite permitido de 50 MB."]);
    exit;
}

// 3. Validar extensiones permitidas: PDF e Imágenes
$ext = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
$extensiones_permitidas = ['pdf', 'png', 'jpg', 'jpeg'];

if (!in_array($ext, $extensiones_permitidas)) {
    echo json_encode(["status" => "error", "message" => "Formato inválido. Solo se permiten archivos PDF o imágenes (PNG, JPG, JPEG)."]);
    exit;
}

// 4. Crear un nombre único seguro
$nombre_seguro = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", $nombre_original);
$carpeta_destino = "uploads/";
$ruta_final_servidor = $carpeta_destino . $nombre_seguro;

// CORRECCIÓN AQUÍ: Se cambió 'move_uploaded_uploaded_file' por la función correcta 'move_uploaded_file'
if (move_uploaded_file($ruta_temporal, $ruta_final_servidor)) {
    $fecha_subida = date('Y-m-d'); // Año actual 2026
    
    // Insertar en la base de datos de Diego
    $query = "INSERT INTO documentos (nombre, archivo_path, fecha_subida, fecha_vencimiento, empresa_cod, activo) 
              VALUES ('$nombre_doc', '$ruta_final_servidor', '$fecha_subida', '$fecha_vencimiento', '$empresa_cod', 1)";
              
    if ($conexion->query($query)) {
        echo json_encode(["status" => "success", "message" => "¡Archivo guardado y registrado con éxito!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Archivo guardado, pero falló en BD: " . $conexion->error]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "No se pudo mover el archivo a la carpeta uploads. Verifica que la carpeta exista."]);
}
?>