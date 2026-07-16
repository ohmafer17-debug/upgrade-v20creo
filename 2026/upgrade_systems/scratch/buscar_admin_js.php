<?php
$content = file_get_contents("c:/xampp/htdocs/upgrade_systems/public/admin/js/index.js");
$lines = explode("\n", $content);

echo "=== BUSCANDO DOCUMENTOS EN ADMIN JS ===\n";
foreach ($lines as $i => $line) {
    if (strpos($line, "subir_documento") !== false || strpos($line, "uploadDoc") !== false || strpos($line, "esActualizacion") !== false) {
        $num = $i + 1;
        echo "Línea $num: $line\n";
    }
}
?>
