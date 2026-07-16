<?php
$content = file_get_contents("c:/xampp/htdocs/upgrade_systems/public/cliente/js/index.js");
$lines = explode("\n", $content);

echo "=== BUSCANDO UPLOADDOCFORM ===\n";
foreach ($lines as $i => $line) {
    if (strpos($line, "uploadDocForm") !== false) {
        $num = $i + 1;
        echo "Línea $num: $line\n";
    }
}
?>
