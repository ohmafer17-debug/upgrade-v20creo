<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== DIAGNÓSTICO DE ERROR ===\n\n";

$file1 = "C:/Users/Diego/Downloads/Documentacion_Upgrade_Systems.docx";
$file2 = "C:/Users/Diego/Downloads/Documentacion_1_2.docx";

echo "1. Verificando si existe la extensión ZipArchive:\n";
if (class_exists('ZipArchive')) {
    echo "   - ZipArchive está HABILITADO.\n";
} else {
    echo "   - ZipArchive está DESHABILITADO.\n";
}

echo "\n2. Verificando existencia de los archivos:\n";
echo "   - Archivo 1 ($file1): " . (file_exists($file1) ? "EXISTE" : "NO EXISTE") . "\n";
echo "   - Archivo 2 ($file2): " . (file_exists($file2) ? "EXISTE" : "NO EXISTE") . "\n";

echo "\n3. Probando abrir Archivo 1 con ZipArchive:\n";
if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    $res = $zip->open($file1);
    if ($res === TRUE) {
        echo "   - Se pudo abrir el zip correctamente.\n";
        $zip->close();
    } else {
        echo "   - Error al abrir el zip. Código de error: " . $res . "\n";
    }
}
?>
