<?php
header('Content-Type: application/json');
include_once("conexion-waffle.php");

$headers = getallheaders();
$token = trim($headers['Authorization'] ?? $headers['authorization'] ?? '');

if (empty($token)) {
    echo json_encode(["ok" => false, "error" => "Sesión no iniciada."]);
    exit;
}

$stmtSes = mysqli_prepare($conexion, "SELECT user_id FROM sesiones WHERE Token = ? LIMIT 1");
mysqli_stmt_bind_param($stmtSes, "s", $token);
mysqli_stmt_execute($stmtSes);
$sesion = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtSes));

if (!$sesion) {
    echo json_encode(["ok" => false, "error" => "Token inválido o expirado."]);
    exit;
}

$userId = (int)$sesion['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$actual    = $data['actual'] ?? '';
$nueva     = $data['nueva'] ?? '';
$confirmar = $data['confirmar'] ?? '';

if (empty($actual) || empty($nueva) || empty($confirmar)) {
    echo json_encode(["ok" => false, "error" => "Completa todos los campos de contraseña."]);
    exit;
}

if ($nueva !== $confirmar) {
    echo json_encode(["ok" => false, "error" => "La nueva contraseña y su confirmación no coinciden."]);
    exit;
}

if (strlen($nueva) < 4) {
    echo json_encode(["ok" => false, "error" => "La nueva contraseña debe tener al menos 4 caracteres."]);
    exit;
}

$stmtPass = mysqli_prepare($conexion, "SELECT password_hash FROM users WHERE id_user = ? LIMIT 1");
mysqli_stmt_bind_param($stmtPass, "i", $userId);
mysqli_stmt_execute($stmtPass);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPass));

if (!$row || strtolower($row['password_hash']) !== hash('sha256', $actual)) {
    echo json_encode(["ok" => false, "error" => "La contraseña actual es incorrecta."]);
    exit;
}

$nuevoHash = hash('sha256', $nueva);
$stmtUpd = mysqli_prepare($conexion, "UPDATE users SET password_hash = ? WHERE id_user = ?");
mysqli_stmt_bind_param($stmtUpd, "si", $nuevoHash, $userId);

if (mysqli_stmt_execute($stmtUpd)) {
    echo json_encode(["ok" => true]);
} else {
    echo json_encode(["ok" => false, "error" => "No se pudo actualizar la contraseña."]);
}
?>