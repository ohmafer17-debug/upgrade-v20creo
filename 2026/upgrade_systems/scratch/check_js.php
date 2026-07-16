<?php
$content = file_get_contents("c:/xampp/htdocs/upgrade_systems/public/cliente/js/index.js");

// Verificar balanceo de llaves {}
$open_braces = substr_count($content, '{');
$close_braces = substr_count($content, '}');
echo "Llaves abiertas: $open_braces, Llaves cerradas: $close_braces\n";

if ($open_braces !== $close_braces) {
    echo "¡WARNING! Las llaves no están balanceadas.\n";
} else {
    echo "Llaves balanceadas correctamente.\n";
}

// Buscar palabras clave como "undefined", "null", u otros posibles errores de sintaxis comunes
// o variables no declaradas.
?>
