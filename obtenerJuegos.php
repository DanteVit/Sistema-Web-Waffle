<?php
header('Content-Type: application/json');
include_once("conexion-waffle.php");

$headers = getallheaders();
$token = mysqli_real_escape_string($conexion, $headers['Authorization'] ?? $headers['authorization'] ?? "");

if (empty($token)) {
    echo json_encode(["ok" => true, "juegos" => []]);
    exit;
}

$resSesion = mysqli_query($conexion, "SELECT user_id FROM sesiones WHERE Token = '$token' LIMIT 1");
if (!$resSesion || mysqli_num_rows($resSesion) === 0) {
    echo json_encode(["ok" => true, "juegos" => []]);
    exit;
}

$userId = (int)mysqli_fetch_assoc($resSesion)['user_id'];

$sql = "SELECT g.id_game, g.title, g.file_name, 
               COALESCE(gen.name, 'Clásicos') AS genre
        FROM games g
        INNER JOIN library l ON g.id_game = l.id_games
        LEFT JOIN games_genres gg ON g.id_game = gg.id_id_games
        LEFT JOIN genres gen ON gg.id_genre = gen.id_genre
        WHERE l.id_user = $userId
        ORDER BY g.id_game DESC";

$result = mysqli_query($conexion, $sql);
$juegos = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $juegos[] = $row;
    }
}

echo json_encode(["ok" => true, "juegos" => $juegos]);
?>