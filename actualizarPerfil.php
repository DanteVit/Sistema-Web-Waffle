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

//Devolver datos actuales para rellenar el formulario de ajustes con GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmtUser = mysqli_prepare($conexion, "SELECT username, email, avatar FROM users WHERE id_user = ? LIMIT 1");
    mysqli_stmt_bind_param($stmtUser, "i", $userId);
    mysqli_stmt_execute($stmtUser);
    $u = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtUser));

    echo json_encode([
        "ok"       => true,
        "username" => $u['username'] ?? '',
        "email"    => $u['email'] ?? '',
        "avatar"   => !empty($u['avatar']) ? $u['avatar'] : 'images/guest.png'
    ]);
    exit;
}

//Actualizar nombre, correo y avatar con POST
$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');
$avatar   = trim($_POST['avatar_preset'] ?? 'images/guest.png');

if (empty($username) || empty($email)) {
    echo json_encode(["ok" => false, "error" => "El nombre de usuario y el correo son obligatorios."]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["ok" => false, "error" => "El formato del correo electrónico no es válido."]);
    exit;
}

// Verificar que username o email no estén en uso por otra cuenta
$stmtDup = mysqli_prepare($conexion, "SELECT id_user FROM users WHERE (username = ? OR email = ?) AND id_user != ? LIMIT 1");
mysqli_stmt_bind_param($stmtDup, "ssi", $username, $email, $userId);
mysqli_stmt_execute($stmtDup);
if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtDup))) {
    echo json_encode(["ok" => false, "error" => "Ese nombre de usuario o correo ya está en uso."]);
    exit;
}

// Obtener avatar anterior para limpiar archivo viejo si sube uno nuevo
$stmtOld = mysqli_prepare($conexion, "SELECT avatar FROM users WHERE id_user = ? LIMIT 1");
mysqli_stmt_bind_param($stmtOld, "i", $userId);
mysqli_stmt_execute($stmtOld);
$oldRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtOld));
$avatarAnterior = $oldRow['avatar'] ?? '';

// Si el usuario subió una imagen personalizada desde su equipo
if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
    if ($_FILES['avatar_file']['size'] > 2 * 1024 * 1024) {
        echo json_encode(["ok" => false, "error" => "La imagen no puede superar los 2 MB."]);
        exit;
    }

    $ext = strtolower(pathinfo($_FILES['avatar_file']['name'], PATHINFO_EXTENSION));
    $permitidas = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

    if (!in_array($ext, $permitidas, true)) {
        echo json_encode(["ok" => false, "error" => "Formato no permitido. Usa PNG, JPG, WEBP o GIF."]);
        exit;
    }

    if (!is_dir("images/avatars")) {
        mkdir("images/avatars", 0777, true);
    }

    $rutaDestino = "images/avatars/avatar_" . $userId . "_" . time() . "." . $ext;
    if (move_uploaded_file($_FILES['avatar_file']['tmp_name'], $rutaDestino)) {
        if (!empty($avatarAnterior) && strpos($avatarAnterior, 'images/avatars/') === 0 && file_exists($avatarAnterior)) {
            @unlink($avatarAnterior);
        }
        $avatar = $rutaDestino;
    } else {
        echo json_encode(["ok" => false, "error" => "No se pudo guardar la imagen en el servidor."]);
        exit;
    }
}

$stmtUpd = mysqli_prepare($conexion, "UPDATE users SET username = ?, email = ?, avatar = ? WHERE id_user = ?");
mysqli_stmt_bind_param($stmtUpd, "sssi", $username, $email, $avatar, $userId);

if (mysqli_stmt_execute($stmtUpd)) {
    echo json_encode([
        "ok"       => true,
        "username" => $username,
        "email"    => $email,
        "avatar"   => $avatar
    ]);
} else {
    echo json_encode(["ok" => false, "error" => "No se pudieron guardar los cambios en MySQL."]);
}
?>