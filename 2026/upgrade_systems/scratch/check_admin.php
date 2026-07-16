<?php
header("Content-Type: text/plain; charset=UTF-8");
require_once __DIR__ . "/../config/conexion.php";

echo "=== DIAGNÓSTICO DE ADMINISTRADORES ===\n\n";

$res = $conexion->query("SELECT id, nombre, email, rol, estatus FROM admin_ups");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo "ID: " . $row['id'] . "\n";
        echo "Nombre: " . $row['nombre'] . "\n";
        echo "Email: " . $row['email'] . "\n";
        echo "Rol: " . $row['rol'] . "\n";
        echo "Estatus: " . $row['estatus'] . "\n";
        echo "---------------------------\n";
    }
} else {
    echo "Error al consultar: " . $conexion->error . "\n";
}
?>
