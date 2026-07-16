<?php
$content = file_get_contents("c:/xampp/htdocs/upgrade_systems/public/admin/index.html");
$lines = explode("\n", $content);

echo "=== BUSCANDO FORMULARIO DE DOCUMENTOS EN ADMIN ===\n";
foreach ($lines as $i => $line) {
    if (strpos($line, "docFile") !== false || strpos($line, "uploadDoc") !== false || strpos($line, "esActualizacion") !== false) {
        $num = $i + 1;
        echo "Línea $num: $line\n";
    }
}
?>
