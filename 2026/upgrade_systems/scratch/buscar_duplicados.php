<?php
$content = file_get_contents("c:/xampp/htdocs/upgrade_systems/public/cliente/js/index.js");
$lines = explode("\n", $content);

$vars = ["btnAddCorreo", "btnCancelAct", "listaCorreosTemporales", "agregarCasillaCorreo", "eliminarFilaCorreo", "cancelarActualizacionExpediente"];

echo "=== VERIFICANDO DUPLICIDAD DE DECLARACIONES ===\n";
foreach ($vars as $var) {
    echo "Buscando '$var':\n";
    foreach ($lines as $i => $line) {
        if (strpos($line, "const $var ") !== false || strpos($line, "let $var ") !== false || strpos($line, "function $var") !== false) {
            $num = $i + 1;
            echo "  Línea $num: $line\n";
        }
    }
}
?>
