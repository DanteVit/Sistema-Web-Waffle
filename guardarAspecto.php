<?php
header('Content-Type: application/json');
include("validacionToken.php");

$data = json_decode(file_get_contents('php://input'), true);
$tipo  = $data['tipo'] ?? '';
$valor = mysqli_real_escape_string($conexion, $data['valor'] ?? '');

$columnasPermitidas = [
    'logo'   => 'bg_image',
    'fondo'  => 'bg_theme',
    'fuente' => 'font_family'
];

if (!isset($columnasPermitidas[$tipo]) || empty($valor)) {
    echo json_encode(["ok" => false, "error" => "Datos de aspecto inválidos."]);
    exit;
}

$columna = $columnasPermitidas[$tipo];

$resUser = mysqli_query($conexion, "SELECT id_configuracion FROM users WHERE id_user = $user_id LIMIT 1");
if ($resUser && mysqli_num_rows($resUser) > 0) {
    $idConfig = (int)mysqli_fetch_assoc($resUser)['id_configuracion'];
    $sql = "UPDATE configurations SET $columna = '$valor' WHERE id_config = $idConfig";

    if (mysqli_query($conexion, $sql)) {
        echo json_encode(["ok" => true]);
    } else {
        echo json_encode(["ok" => false, "error" => mysqli_error($conexion)]);
    }
} else {
    echo json_encode(["ok" => false, "error" => "Configuración de usuario no encontrada."]);
}
?>