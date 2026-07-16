<?php
// =================================================================
// SCRIPT DE MIGRACIÓN: CAMPOS DE CONTACTO EN ADMIN_UPS
// =================================================================
header("Content-Type: text/plain; charset=UTF-8");
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../config/conexion.php";

echo "=== MIGRACIÓN DE BASE DE DATOS: PERFIL DE ADMINISTRADOR ===\n\n";

// Columnas a agregar
$columnas = [
    'email_secundario' => 'VARCHAR(100) NULL AFTER email',
    'telefono_principal' => 'VARCHAR(20) NULL AFTER email_secundario',
    'telefono_secundario' => 'VARCHAR(20) NULL AFTER telefono_principal'
];

foreach ($columnas as $col => $definition) {
    $check = $conexion->query("SHOW COLUMNS FROM admin_ups LIKE '$col'");
    if ($check && $check->num_rows == 0) {
        $alter = $conexion->query("ALTER TABLE admin_ups ADD COLUMN $col $definition");
        if ($alter) {
            echo "✓ Columna '$col' añadida exitosamente a tabla 'admin_ups'.\n";
        } else {
            echo "✗ Error al añadir columna '$col': " . $conexion->error . "\n";
        }
    } else {
        echo "✓ La columna '$col' ya existe en la tabla 'admin_ups'.\n";
    }
}

echo "\nMigración completada.\n";
?>
