<?php
// =================================================================
// SCRIPT TEMPORAL: crear_cliente.php
// Inserta un cliente de prueba en la base de datos
// =================================================================
header("Content-Type: text/html; charset=UTF-8");
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<html><head><title>Creador de Cliente Demo</title>";
echo "<style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 40px; background: #f4f6f9; color: #333; }
    h1 { color: #2563eb; border-bottom: 2px solid #2563eb; padding-bottom: 10px; }
    .success { color: #15803d; font-weight: bold; background: #dcfce7; padding: 10px; border-radius: 6px; border: 1px solid #bbf7d0; margin-bottom: 10px; }
    .error { color: #b91c1c; font-weight: bold; background: #fee2e2; padding: 10px; border-radius: 6px; border: 1px solid #fca5a5; margin-bottom: 10px; }
    pre { background: #e2e8f0; padding: 15px; border-radius: 6px; }
</style></head><body>";

echo "<h1>🔑 Creación de Usuario Cliente Demo</h1>";

$servidor   = "localhost";
$usuario    = "root";
$password   = ""; 
$base_datos = "upgrade_systems_db"; 
$puerto     = 3306;

$conexion = new mysqli($servidor, $usuario, $password, $base_datos, $puerto);
if ($conexion->connect_error) {
    echo "<div class='error'>Error de conexión a la base de datos: " . $conexion->connect_error . "</div>";
    echo "</body></html>";
    exit;
}
$conexion->set_charset("utf8");

// Datos del cliente de prueba
$cod = "DEMO-01";
$nombre = "Empresa Demo S.A.";
$email = "cliente@demo.com";
$pass_texto = "cliente123";
$pass_hash = password_hash($pass_texto, PASSWORD_BCRYPT);
$rol = "Consultor";
$activo = 1;

// Verificar si ya existe
$check = $conexion->query("SELECT id FROM empresas_clientes WHERE email = '$email'");
if ($check && $check->num_rows > 0) {
    echo "<div class='success'>El usuario cliente ya estaba registrado previamente.</div>";
} else {
    // Insertar el cliente de prueba
    $stmt = $conexion->prepare("INSERT INTO empresas_clientes (cod, nombre, email, pass, activo, rol) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssis", $cod, $nombre, $email, $pass_hash, $activo, $rol);
    
    if ($stmt->execute()) {
        echo "<div class='success'>¡Cliente Demo registrado con éxito en la base de datos!</div>";
    } else {
        echo "<div class='error'>Error al registrar el cliente: " . $stmt->error . "</div>";
    }
    $stmt->close();
}

echo "<pre>";
echo "<b>DATOS DE ACCESO PARA EL PORTAL DE CLIENTES:</b>\n";
echo "Página de Login: http://localhost:8080/upgrade_systems/public/cliente/login.html\n";
echo "Correo: $email\n";
echo "Contraseña: $pass_texto\n";
echo "Rol asignado: $rol\n";
echo "Código de Organización: $cod\n";
echo "</pre>";

$conexion->close();
echo "</body></html>";
?>
