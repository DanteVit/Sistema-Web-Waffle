<?php
header('Content-Type: application/json');
include_once("conexion-waffle.php");

$headers = function_exists('getallheaders') ? getallheaders() : [];
$token = trim(
    $headers['Authorization']
    ?? $headers['authorization']
    ?? $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? ''
);

if (empty($token)) {
    echo json_encode(["ok" => false, "error" => "No hay token de sesión."]);
    exit;
}

$tieneAvatar = mysqli_num_rows(mysqli_query($conexion, "SHOW COLUMNS FROM users LIKE 'avatar'")) > 0;
$tieneRol    = mysqli_num_rows(mysqli_query($conexion, "SHOW COLUMNS FROM users LIKE 'role'")) > 0;

$campoAvatar = $tieneAvatar ? "u.avatar" : "'images/guest.png' AS avatar";
$campoRol    = $tieneRol ? "u.role" : "IF(u.username = 'admin', 'admin', 'user') AS role";

$sql = "SELECT u.id_user, u.username, u.email, u.points, $campoAvatar, $campoRol,
               c.bg_theme, c.text_color, c.font_family, c.bg_image
        FROM sesiones s
        INNER JOIN users u ON s.user_id = u.id_user
        LEFT JOIN configurations c ON u.id_configuracion = c.id_config
        WHERE s.Token = ?
        LIMIT 1";

$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "s", $token);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$row) {
    echo json_encode(["ok" => false, "error" => "Sesión no válida o expirada."]);
    exit;
}

$userId = (int)$row['id_user'];

// Obtener ítems comprados
$items = ['logo-original', 'fondo-default', 'font-comic'];
try {
    $colsItems = mysqli_query($conexion, "SHOW COLUMNS FROM user_items");
    if ($colsItems) {
        $colUserItem = 'id_user';
        while ($c = mysqli_fetch_assoc($colsItems)) {
            if (in_array($c['Field'], ['id_user', 'user_id'], true)) {
                $colUserItem = $c['Field'];
                break;
            }
        }
        $resItems = mysqli_query($conexion, "SELECT * FROM user_items WHERE `$colUserItem` = $userId");
        if ($resItems) {
            while ($itemRow = mysqli_fetch_assoc($resItems)) {
                foreach ($itemRow as $col => $val) {
                    if (!in_array($col, ['id_user_item', 'id_user', 'user_id', 'created_at', 'purchased_at'], true) && $val !== null) {
                        $items[] = $val;
                        break;
                    }
                }
            }
        }
    }
} catch (Throwable $e) {}
$items = array_values(array_unique($items));

// Obtener juegos en biblioteca
$library = [];
try {
    $colsLib = mysqli_query($conexion, "SHOW COLUMNS FROM library");
    if ($colsLib) {
        $colUserLib = 'id_user';
        $colGameLib = 'id_games';
        while ($c = mysqli_fetch_assoc($colsLib)) {
            if (in_array($c['Field'], ['id_user', 'user_id'], true)) $colUserLib = $c['Field'];
            if (in_array($c['Field'], ['id_game', 'id_games', 'game_id'], true)) $colGameLib = $c['Field'];
        }
        $resLib = mysqli_query($conexion, "SELECT `$colGameLib` AS gid FROM library WHERE `$colUserLib` = $userId");
        if ($resLib) {
            while ($libRow = mysqli_fetch_assoc($resLib)) {
                $library[] = (int)$libRow['gid'];
            }
        }
    }
} catch (Throwable $e) {}

$avatarFinal = !empty($row['avatar']) ? $row['avatar'] : 'images/guest.png';
$rolFinal    = !empty($row['role']) ? $row['role'] : ($row['username'] === 'admin' ? 'admin' : 'user');

// Normalizar ruta del logo para evitar 404
$bgImageRaw = trim((string)($row['bg_image'] ?? ''));
if ($bgImageRaw === '') {
    $bgImageFinal = 'images/logo-original.png';
} elseif (strpos($bgImageRaw, '/') === false && !preg_match('/\.(png|jpg|jpeg|webp|gif|svg)$/i', $bgImageRaw)) {
    $bgImageFinal = 'images/' . $bgImageRaw . '.png';
} else {
    $bgImageFinal = $bgImageRaw;
}

$perfil = [
    "id_user"     => $userId,
    "username"    => $row['username'],
    "email"       => $row['email'],
    "role"        => $rolFinal,
    "points"      => (int)$row['points'],
    "avatar"      => $avatarFinal,
    "bg_theme"    => !empty($row['bg_theme']) ? $row['bg_theme'] : 'fondo-default',
    "text_color"  => !empty($row['text_color']) ? $row['text_color'] : '#ffcf2f',
    "font_family" => !empty($row['font_family']) ? $row['font_family'] : 'font-comic',
    "bg_image"    => $bgImageFinal
];

echo json_encode([
    "ok"             => true,
    "perfil"         => $perfil,
    "itemsComprados" => $items,
    "id_user"        => $perfil['id_user'],
    "username"       => $perfil['username'],
    "email"          => $perfil['email'],
    "role"           => $perfil['role'],
    "points"         => $perfil['points'],
    "avatar"         => $perfil['avatar'],
    "bg_theme"       => $perfil['bg_theme'],
    "text_color"     => $perfil['text_color'],
    "font_family"    => $perfil['font_family'],
    "bg_image"       => $perfil['bg_image'],
    "items"          => $items,
    "library"        => $library
]);
?>