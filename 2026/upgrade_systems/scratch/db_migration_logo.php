<?php
// =================================================================
// SCRIPT DE MIGRACIÓN: AGREGAR COLUMNA LOGO A empresas_clientes
// =================================================================
header("Content-Type: text/plain; charset=UTF-8");
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== INICIANDO MIGRACIÓN DE BASE DE DATOS ===\n\n";

// Conectamos a la BD usando la ruta relativa del proyecto
require_once __DIR__ . "/../config/conexion.php";

if ($conexion->connect_error) {
    die("Error de conexión a la base de datos: " . $conexion->connect_error);
}

// Verificar si la columna 'logo' ya existe en 'empresas_clientes'
$checkCol = $conexion->query("SHOW COLUMNS FROM `empresas_clientes` LIKE 'logo'");

if ($checkCol && $checkCol->num_rows > 0) {
    echo "La columna 'logo' ya existe en la tabla 'empresas_clientes'. No se requiere migración.\n";
} else {
    // Agregar la columna
    $alterQuery = "ALTER TABLE `empresas_clientes` ADD COLUMN `logo` VARCHAR(255) DEFAULT NULL AFTER `rol`";
    if ($conexion->query($alterQuery)) {
        echo "¡Migración completada exitosamente! Se agregó la columna 'logo' a la tabla 'empresas_clientes'.\n";
    } else {
        echo "ERROR al ejecutar la migración: " . $conexion->error . "\n";
    }
}

echo "\n=== MIGRACIÓN FINALIZADA ===";
?>
