<?php
header('Content-Type: application/json');
include("validacionToken.php");
$sql = "SELECT * FROM users WHERE id_user = $user_id";

$result = mysqli_query($conexion, $sql);

if ($result && mysqli_num_rows($result) > 0) {

    $data = json_decode(file_get_contents('php://input'), true);
    $idConfiguracion = mysqli_fetch_assoc($result)['id_configuracion'];
    $bgImage = $data['Logo'];
    $sql = "UPDATE configurations SET bg_image = '$bgImage' WHERE id_config = $idConfiguracion";
    mysqli_query($conexion, $sql);
    echo json_encode(["ok"=>true]);
}
?>