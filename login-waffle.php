<?php
header('Content-Type: application/json');
include_once("conexion-waffle.php");

$data = json_decode(file_get_contents('php://input'), true);

$username = mysqli_real_escape_string($conexion, trim($data['username'] ?? ''));
$email    = mysqli_real_escape_string($conexion, trim($data['email'] ?? ''));
$password = $data['password'] ?? '';

if (empty($username) || empty($email) || empty($password)) {
    echo json_encode(["ok" => false, "error" => "Completa usuario, correo y contraseña."]);
    exit;
}

$passwordHash = hash('sha256', $password);

$sql = "SELECT * FROM users 
        WHERE username = '$username' 
        AND email = '$email' 
        AND password_hash = '$passwordHash' 
        LIMIT 1";

$result = mysqli_query($conexion, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $user = mysqli_fetch_assoc($result);
    $userId = $user['id_user'];
    $token = bin2hex(random_bytes(32));

    $sqlToken = "INSERT INTO sesiones (user_id, Token) VALUES ($userId, '$token')";
    mysqli_query($conexion, $sqlToken);

    echo json_encode([
        "ok" => true,
        "token" => $token,
        "username" => $user['username'],
        "points" => $user['points'] ?? 500
    ]);
} else {
    echo json_encode(["ok" => false, "error" => "Usuario, correo o contraseña incorrectos."]);
}
?>