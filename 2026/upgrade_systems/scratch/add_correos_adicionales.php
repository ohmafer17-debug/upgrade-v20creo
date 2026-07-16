<?php
require_once __DIR__ . "/../config/conexion.php";

echo "<h1>Migración: Agregando columna correos_adicionales</h1>";

$colName = 'correos_adicionales';
$sql = "ALTER TABLE documentos_pc ADD COLUMN correos_adicionales TEXT NULL AFTER motivo";

// Verificar si la columna ya existe
$check = $conexion->query("SHOW COLUMNS FROM documentos_pc LIKE '$colName'");
if ($check && $check->num_rows > 0) {
    echo "La columna <strong>$colName</strong> ya existe en la tabla documentos_pc.<br>";
} else {
    if ($conexion->query($sql)) {
        echo "Columna <strong>$colName</strong> agregada exitosamente a documentos_pc.<br>";
    } else {
        echo "Error al agregar la columna $colName: " . $conexion->error . "<br>";
    }
}

echo "Migración completada.";
?>
