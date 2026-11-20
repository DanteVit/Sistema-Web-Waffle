<?php
header('Content-Type: application/json');
include("validacionToken.php");

$titulo = mysqli_real_escape_string($conexion, trim($_POST['title'] ?? ''));
$generoSlug = trim($_POST['genre'] ?? 'clasicos');

$mapaGeneros = [
    'accion'      => 'Acción',
    'aventura'    => 'Aventura',
    'estrategia'  => 'Estrategia',
    'plataformas' => 'Plataformas',
    'clasicos'    => 'Clásicos'
];
$nombreGenero = mysqli_real_escape_string($conexion, $mapaGeneros[$generoSlug] ?? 'Clásicos');

if (empty($titulo) || !isset($_FILES['swf_file']) || $_FILES['swf_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["ok" => false, "error" => "Completa el título y selecciona un archivo .swf válido."]);
    exit;
}

$nombreOriginal = $_FILES['swf_file']['name'];
$extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

if ($extension !== 'swf') {
    echo json_encode(["ok" => false, "error" => "Solo se permiten archivos con extensión .swf"]);
    exit;
}

$carpetaDestino = __DIR__ . '/juegos/';
if (!is_dir($carpetaDestino)) {
    mkdir($carpetaDestino, 0777, true);
}

$nombreLimpio = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($nombreOriginal, PATHINFO_FILENAME));
$archivoFinal = time() . '_' . $nombreLimpio . '.swf';
$rutaRelativa = 'juegos/' . $archivoFinal;

if (!move_uploaded_file($_FILES['swf_file']['tmp_name'], $carpetaDestino . $archivoFinal)) {
    echo json_encode(["ok" => false, "error" => "No se pudo guardar el archivo en el servidor."]);
    exit;
}

mysqli_query($conexion, "INSERT INTO games (title, file_name) VALUES ('$titulo', '$rutaRelativa')");
$idGame = mysqli_insert_id($conexion);

$resGenre = mysqli_query($conexion, "SELECT id_genre FROM genres WHERE name = '$nombreGenero' LIMIT 1");
if ($resGenre && mysqli_num_rows($resGenre) > 0) {
    $idGenre = (int)mysqli_fetch_assoc($resGenre)['id_genre'];
} else {
    mysqli_query($conexion, "INSERT INTO genres (name) VALUES ('$nombreGenero')");
    $idGenre = mysqli_insert_id($conexion);
}

mysqli_query($conexion, "INSERT INTO games_genres (id_id_games, id_genre) VALUES ($idGame, $idGenre)");

mysqli_query($conexion, "INSERT INTO library (id_user, id_games) VALUES ($user_id, $idGame)");

// Sumar +25 puntos al usuario que subió el juego
$stmtBonus = mysqli_prepare($conexion, "UPDATE users SET points = points + 25 WHERE id_user = ?");
mysqli_stmt_bind_param($stmtBonus, "i", $user_id);
mysqli_stmt_execute($stmtBonus);

// Consultar saldo actualizado
$stmtSaldo = mysqli_prepare($conexion, "SELECT points FROM users WHERE id_user = ? LIMIT 1");
mysqli_stmt_bind_param($stmtSaldo, "i", $user_id);
mysqli_stmt_execute($stmtSaldo);
$saldoRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtSaldo));
$nuevosPuntos = (int)($saldoRow['points'] ?? 0);

echo json_encode([
    "ok"           => true,
    "nuevosPuntos" => $nuevosPuntos,
    "juego"        => [
        "id_game"   => $idGame,
        "title"     => $titulo,
        "file_name" => $rutaRelativa,
        "genre"     => $nombreGenero
    ]
]);
?>