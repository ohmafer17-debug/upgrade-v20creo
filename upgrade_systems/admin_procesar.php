<?php
header("Content-Type: application/json");
require_once "conexion.php";

$datos = json_decode(file_get_contents("php://input"), true);
$accion = $datos['accion'];

if ($accion === 'listar_todo') {
    $res = $conexion->query("SELECT * FROM empresas_clientes ORDER BY nombre ASC");
    $socios = []; $empresas = [];
    while($row = $res->fetch_assoc()) {
        if(strpos($row['cod'], 'UPS') === 0) $socios[] = $row;
        else $empresas[] = $row;
    }
    echo json_encode(["status" => "success", "socios" => $socios, "empresas" => $empresas]);
}

if ($accion === 'alternar_estatus') {
    $cod = $datos['cod']; $est = $datos['nuevo_estatus'];
    $conexion->query("UPDATE empresas_clientes SET activo = $est WHERE cod = '$cod'");
    echo json_encode(["status" => "success"]);
}

if ($accion === 'crear_empresa') {
    $cod = $datos['cod']; $nom = $datos['nombre']; $em = $datos['email']; $pa = $datos['pass']; $ro = $datos['rol'];
    $conexion->query("INSERT INTO empresas_clientes (cod, nombre, email, pass, activo, rol) VALUES ('$cod', '$nom', '$em', '$pa', 1, '$ro')");
    echo json_encode(["status" => "success", "message" => "Registro creado correctamente"]);
}

// NUEVA FUNCIÓN DE EDICIÓN
if ($accion === 'editar_registro') {
    $cod = $datos['cod']; $nom = $datos['nombre']; $em = $datos['email']; $pa = $datos['pass']; $ro = $datos['rol'];
    $sql = "UPDATE empresas_clientes SET nombre='$nom', email='$em', pass='$pa', rol='$ro' WHERE cod='$cod'";
    if($conexion->query($sql)) {
        echo json_encode(["status" => "success", "message" => "Datos actualizados correctamente"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error al actualizar"]);
    }
}
?>