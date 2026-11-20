<?php
include("conexion-waffle.php");
$conn = $conexión ?? $conexion;

$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $email === '' || $password === '') {
    die("Error: Todos los campos son obligatorios.");
}

$passwordHash = hash('sha256', $password);
$fondos       = "fondo-default";
$texto        = "#ffcf2f";
$fuente_letra = "font-comic";
$imagen_fondo = "images/logo-original.png";

$stmtCheck = mysqli_prepare($conn, "SELECT id_user FROM users WHERE username = ? OR email = ? LIMIT 1");
mysqli_stmt_bind_param($stmtCheck, "ss", $username, $email);
mysqli_stmt_execute($stmtCheck);
$resCheck = mysqli_stmt_get_result($stmtCheck);

if ($resCheck && mysqli_num_rows($resCheck) > 0) {
    mysqli_close($conn);
    die("Error: El nombre de usuario o el correo electrónico ya están registrados.");
}

$stmtConfig = mysqli_prepare($conn, "INSERT INTO configurations (bg_theme, text_color, font_family, bg_image) VALUES (?, ?, ?, ?)");
mysqli_stmt_bind_param($stmtConfig, "ssss", $fondos, $texto, $fuente_letra, $imagen_fondo);

if (mysqli_stmt_execute($stmtConfig)) {
    $config_user = mysqli_insert_id($conn);

    $stmtUser = mysqli_prepare($conn, "INSERT INTO users (username, email, password_hash, id_configuracion) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmtUser, "sssi", $username, $email, $passwordHash, $config_user);

    if (mysqli_stmt_execute($stmtUser)) {
        mysqli_close($conn);
        header("Location: login.html");
        exit();
    } else {
        echo "Error al registrar usuario: " . mysqli_error($conn);
    }
} else {
    echo "Error al crear configuración: " . mysqli_error($conn);
}

mysqli_close($conn);
?>