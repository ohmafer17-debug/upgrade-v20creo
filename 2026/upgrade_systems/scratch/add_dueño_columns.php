<?php
require_once __DIR__ . "/../config/conexion.php";

echo "<h1>Migración: Agregando columnas de Dueño/Director</h1>";

// 1. Añadir columnas a la tabla empresas_clientes si no existen
$columnas = [
    'dueño_director_nombre' => "ALTER TABLE empresas_clientes ADD COLUMN dueño_director_nombre VARCHAR(255) NULL AFTER telefono_adicional",
    'dueño_director_email' => "ALTER TABLE empresas_clientes ADD COLUMN dueño_director_email VARCHAR(255) NULL AFTER dueño_director_nombre"
];

foreach ($columnas as $colName => $sql) {
    // Verificar si la columna ya existe
    $check = $conexion->query("SHOW COLUMNS FROM empresas_clientes LIKE '$colName'");
    if ($check && $check->num_rows > 0) {
        echo "La columna <strong>$colName</strong> ya existe en la tabla.<br>";
    } else {
        if ($conexion->query($sql)) {
            echo "Columna <strong>$colName</strong> agregada exitosamente.<br>";
        } else {
            echo "Error al agregar la columna $colName: " . $conexion->error . "<br>";
        }
    }
}

echo "Migración completada.";
?>
