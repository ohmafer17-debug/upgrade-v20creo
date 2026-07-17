<?php
// =================================================================
// SCRIPT DE INSTALACIÓN: crear_tablas.php
// Creación de Base de Datos, Tablas y Datos de Prueba
// =================================================================
header("Content-Type: text/html; charset=UTF-8");
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<html><head><title>Instalador de Base de Datos</title>";
echo "<style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 40px; background: #f4f6f9; color: #333; }
    h1 { color: #1e3a8a; border-bottom: 2px solid #1e3a8a; padding-bottom: 10px; }
    .success { color: #15803d; font-weight: bold; background: #dcfce7; padding: 10px; border-radius: 6px; border: 1px solid #bbf7d0; margin-bottom: 10px; }
    .error { color: #b91c1c; font-weight: bold; background: #fee2e2; padding: 10px; border-radius: 6px; border: 1px solid #fca5a5; margin-bottom: 10px; }
    .info { color: #1d4ed8; font-weight: bold; background: #dbeafe; padding: 10px; border-radius: 6px; border: 1px solid #bfdbfe; margin-bottom: 10px; }
    pre { background: #e2e8f0; padding: 15px; border-radius: 6px; overflow-x: auto; }
</style></head><body>";

echo "<h1>🛠️ Creación e Inicialización de la Base de Datos</h1>";

// 1. Conexión al servidor local MySQL (sin seleccionar base de datos inicialmente)
$servidor   = "localhost";
$usuario    = "root";
$password   = ""; 
$puerto     = 3306; // Puerto de tu XAMPP

echo "<div class='info'>Conectando al servidor local MySQL en localhost:3306...</div>";
$conexion_inicial = @new mysqli($servidor, $usuario, $password, "", $puerto);

if ($conexion_inicial->connect_error) {
    echo "<div class='error'>Error de conexión al servidor MySQL: " . $conexion_inicial->connect_error . "<br>Asegúrate de que MySQL esté iniciado en tu panel de control de XAMPP.</div>";
    echo "</body></html>";
    exit;
}
echo "<div class='success'>¡Conexión al servidor MySQL exitosa!</div>";

// 2. Crear la base de datos si no existe
$base_datos = "upgrade_systems_db";
$sql_db = "CREATE DATABASE IF NOT EXISTS `$base_datos` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
if ($conexion_inicial->query($sql_db) === TRUE) {
    echo "<div class='success'>Base de datos `$base_datos` creada o ya existente.</div>";
} else {
    echo "<div class='error'>Error al crear la base de datos: " . $conexion_inicial->error . "</div>";
    $conexion_inicial->close();
    echo "</body></html>";
    exit;
}
$conexion_inicial->close();

// 3. Conexión a la base de datos seleccionada
$conexion = new mysqli($servidor, $usuario, $password, $base_datos, $puerto);
if ($conexion->connect_error) {
    echo "<div class='error'>Error al seleccionar la base de datos `$base_datos`: " . $conexion->connect_error . "</div>";
    echo "</body></html>";
    exit;
}
$conexion->set_charset("utf8");

// 4. Crear tabla de Administradores UPS (admin_ups)
echo "<h3>Creando tablas...</h3>";

$sql_admin_ups = "CREATE TABLE IF NOT EXISTS `admin_ups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) UNIQUE NOT NULL,
    `pass` VARCHAR(255) NOT NULL,
    `estatus` VARCHAR(50) DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conexion->query($sql_admin_ups) === TRUE) {
    echo "<div class='success'>Tabla `admin_ups` lista.</div>";
} else {
    echo "<div class='error'>Error al crear tabla `admin_ups`: " . $conexion->error . "</div>";
}

// 5. Crear tabla de Empresas Clientes (empresas_clientes)
$sql_empresas_clientes = "CREATE TABLE IF NOT EXISTS `empresas_clientes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cod` VARCHAR(100) UNIQUE NOT NULL,
    `nombre` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) UNIQUE NOT NULL,
    `email_adicional` VARCHAR(255) DEFAULT NULL,
    `telefono_principal` VARCHAR(50) DEFAULT NULL,
    `telefono_adicional` VARCHAR(50) DEFAULT NULL,
    `direccion` TEXT DEFAULT NULL,
    `coordenadas` VARCHAR(255) DEFAULT NULL,
    `pass` VARCHAR(255) NOT NULL,
    `activo` INT DEFAULT 1,
    `rol` VARCHAR(100) NOT NULL,
    `logo` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conexion->query($sql_empresas_clientes) === TRUE) {
    echo "<div class='success'>Tabla `empresas_clientes` lista.</div>";
} else {
    echo "<div class='error'>Error al crear tabla `empresas_clientes`: " . $conexion->error . "</div>";
}

// 6. Crear tabla de Documentos (documentos_pc)
$sql_documentos_pc = "CREATE TABLE IF NOT EXISTS `documentos_pc` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `empresa_cod` VARCHAR(100) NOT NULL,
    `tipo_doc` VARCHAR(100) NOT NULL,
    `nombre_personalizado` VARCHAR(255) NOT NULL,
    `fecha_vencimiento` DATE DEFAULT NULL,
    `fecha_subida_sistema` DATE DEFAULT NULL,
    `subido_por` VARCHAR(255) DEFAULT NULL,
    `actualizado_por` VARCHAR(255) DEFAULT NULL,
    `visto_por` VARCHAR(255) DEFAULT NULL,
    `estatus` INT DEFAULT 1,
    `nombre_archivo_fisico` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conexion->query($sql_documentos_pc) === TRUE) {
    echo "<div class='success'>Tabla `documentos_pc` lista.</div>";
} else {
    echo "<div class='error'>Error al crear tabla `documentos_pc`: " . $conexion->error . "</div>";
}

// 7. Crear tabla de Historial de Documentos (historial_documentos)
$sql_historial_documentos = "CREATE TABLE IF NOT EXISTS `historial_documentos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `documento_id` INT NOT NULL,
    `empresa_cod` VARCHAR(100) NOT NULL,
    `tipo_doc` VARCHAR(100) NOT NULL,
    `nombre_personalizado` VARCHAR(255) NOT NULL,
    `fecha_vencimiento` DATE DEFAULT NULL,
    `nombre_archivo_fisico` VARCHAR(255) NOT NULL,
    `fecha_modificacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conexion->query($sql_historial_documentos) === TRUE) {
    echo "<div class='success'>Tabla `historial_documentos` lista.</div>";
} else {
    echo "<div class='error'>Error al crear tabla `historial_documentos`: " . $conexion->error . "</div>";
}

// 8. Crear Vista de compatibilidad para dashboard (documentos)
$sql_view_documentos = "CREATE OR REPLACE VIEW `documentos` AS 
    SELECT `fecha_vencimiento`, `empresa_cod`, `estatus` AS `activo` 
    FROM `documentos_pc`;";

if ($conexion->query($sql_view_documentos) === TRUE) {
    echo "<div class='success'>Vista de compatibilidad `documentos` lista.</div>";
} else {
    echo "<div class='error'>Error al crear vista `documentos`: " . $conexion->error . "</div>";
}

// 9. Insertar administrador por defecto en admin_ups
$check_admin = $conexion->query("SELECT id FROM `admin_ups` WHERE `email` = 'ohmafer.17@gmail.com'");
if ($check_admin && $check_admin->num_rows == 0) {
    $nombre_admin = "Administrador Master";
    $email_admin = "ohmafer.17@gmail.com";
    
    // Contraseña por defecto: "admin123"
    $password_texto = "admin123";
    $password_hash = password_hash($password_texto, PASSWORD_BCRYPT);
    
    $sql_insert_admin = "INSERT INTO `admin_ups` (`nombre`, `email`, `pass`, `estatus`) VALUES ('$nombre_admin', '$email_admin', '$password_hash', 'Activo')";
    if ($conexion->query($sql_insert_admin) === TRUE) {
        echo "<div class='success'>¡Usuario Administrador creado exitosamente!</div>";
        echo "<pre>";
        echo "<b>DATOS DE ACCESO PARA LA CONSOLA MÁSTER:</b>\n";
        echo "Correo: $email_admin\n";
        echo "Contraseña: $password_texto\n";
        echo "</pre>";
    } else {
        echo "<div class='error'>Error al insertar administrador: " . $conexion->error . "</div>";
    }
} else {
    echo "<div class='info'>El administrador con correo 'ohmafer.17@gmail.com' ya existe en la base de datos.</div>";
}

echo "<br><div class='info'><strong>¡Listo! Todo ha sido configurado correctamente.</strong> Ahora puedes ir a la página de login e intentar iniciar sesión con los datos arriba indicados.</div>";

$conexion->close();
echo "</body></html>";
?>
