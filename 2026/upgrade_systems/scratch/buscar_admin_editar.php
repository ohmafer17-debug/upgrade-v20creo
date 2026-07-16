<?php
$content = file_get_contents("c:/xampp/htdocs/upgrade_systems/public/admin/js/index.js");
$lines = explode("\n", $content);

echo "=== BUSCANDO EDITAR EN ADMIN JS ===\n";
foreach ($lines as $i => $line) {
    if (strpos($line, "editar") !== false || strpos($line, "Form") !== false || strpos($line, "scroll") !== false) {
        $num = $i + 1;
        echo "Línea $num: $line\n";
    }
}
?>
