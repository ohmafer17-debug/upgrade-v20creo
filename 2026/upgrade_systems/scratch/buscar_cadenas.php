<?php
$content = file_get_contents("c:/xampp/htdocs/upgrade_systems/public/cliente/js/index.js");
$lines = explode("\n", $content);

echo "=== BUSCANDO VARIABLES OBSOLETAS ===\n";
foreach ($lines as $i => $line) {
    if (strpos($line, "listaCorreosTemporales") !== false || strpos($line, "renderizarLista") !== false) {
        $num = $i + 1;
        echo "Línea $num: $line\n";
    }
}
?>
