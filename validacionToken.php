<?php 
header('Content-Type: application/json');
include_once("conexion-waffle.php");

$headers = getallheaders();
$token = $headers['Authorization'] ?? $headers['authorization'] ?? ""; 
$token = mysqli_real_escape_string($conexion, $token);

$sql = "SELECT user_id FROM sesiones WHERE Token = '$token' LIMIT 1";
$validacion = mysqli_query($conexion, $sql);

if ($validacion && mysqli_num_rows($validacion) > 0) {
    $row = mysqli_fetch_assoc($validacion);
    $user_id = $row['user_id'];
} else {
    echo json_encode(["error" => "Token invalido o no encontrado", "ok" => false]);
    exit;
}
?>