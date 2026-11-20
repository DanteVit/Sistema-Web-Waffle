<?php
session_start();
header('Content-Type: application/json');
include_once("conexion-waffle.php");

$headers = function_exists('getallheaders') ? getallheaders() : [];
$token = trim(
    $headers['Authorization']
    ?? $headers['authorization']
    ?? $_SERVER['HTTP_AUTHORIZATION']
    ?? ''
);

if (empty($token)) {
    echo json_encode(["ok" => false, "error" => "Sesión no iniciada."]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$juego = trim($data['game'] ?? '');

if (empty($juego)) {
    echo json_encode(["ok" => false, "error" => "No se detectó ningún juego activo."]);
    exit;
}

// Verificar sesión válida en MySQL
$stmtSes = mysqli_prepare($conexion, "SELECT user_id FROM sesiones WHERE Token = ? LIMIT 1");
mysqli_stmt_bind_param($stmtSes, "s", $token);
mysqli_stmt_execute($stmtSes);
$sesion = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtSes));

if (!$sesion) {
    echo json_encode(["ok" => false, "error" => "Token inválido."]);
    exit;
}

$userId = (int)$sesion['user_id'];

// Exigir al menos 55 segundos reales entre cada punto que se obtuvo al jugar
$ahora = time();
$ultimoCobro = $_SESSION['ultimo_punto_ruffle'][$userId] ?? 0;

if (($ahora - $ultimoCobro) < 55) {
    echo json_encode([
        "ok" => false,
        "error" => "Todavía no se cumplió 1 minuto de juego.",
        "segundos_restantes" => 55 - ($ahora - $ultimoCobro)
    ]);
    exit;
}

$_SESSION['ultimo_punto_ruffle'][$userId] = $ahora;

// Sumar +1 punto en la base de datos
$stmtUpd = mysqli_prepare($conexion, "UPDATE users SET points = points + 1 WHERE id_user = ?");
mysqli_stmt_bind_param($stmtUpd, "i", $userId);
mysqli_stmt_execute($stmtUpd);

// Consultar saldo actualizado
$stmtPts = mysqli_prepare($conexion, "SELECT points FROM users WHERE id_user = ? LIMIT 1");
mysqli_stmt_bind_param($stmtPts, "i", $userId);
mysqli_stmt_execute($stmtPts);
$rowPts = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPts));

echo json_encode([
    "ok"           => true,
    "puntosGanados"=> 1,
    "nuevosPuntos" => (int)($rowPts['points'] ?? 0)
]);
?>