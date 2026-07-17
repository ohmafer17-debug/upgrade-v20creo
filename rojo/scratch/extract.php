<?php
// Script para extraer texto de un archivo .docx y guardarlo en un archivo .txt
error_reporting(E_ALL);
ini_set('display_errors', 1);

function read_docx($filename) {
    $striped_content = '';
    $content = '';

    if(!$filename || !file_exists($filename)) return false;

    $zip = new ZipArchive();
    if ($zip->open($filename) === true) {
        if (($index = $zip->locateName('word/document.xml')) !== false) {
            $data = $zip->getFromIndex($index);
            $xml = new SimpleXMLElement($data);
            
            // Registrar namespaces
            $namespaces = $xml->getNamespaces(true);
            $xml->registerXPathNamespace('w', $namespaces['w']);
            
            // Buscar todos los elementos de texto
            $text_elements = $xml->xpath('//w:t');
            foreach ($text_elements as $text) {
                $striped_content .= $text . " ";
            }
        }
        $zip->close();
    }
    return $striped_content;
}

$docx_path = "C:/Users/Diego/Downloads/Documentacion_Upgrade_Systems.docx";
$output_path = __DIR__ . "/doc_text.txt";

echo "Leyendo archivo: $docx_path ...\n";
$texto = read_docx($docx_path);

if ($texto !== false) {
    file_put_contents($output_path, $texto);
    echo "¡Texto extraído con éxito y guardado en $output_path!\n";
} else {
    echo "Error al leer el archivo .docx.\n";
}
?>
