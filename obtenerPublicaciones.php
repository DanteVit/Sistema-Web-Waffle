<?php
header('Content-Type: application/json; charset=utf-8');
include_once("conexion-waffle.php");

$headers = function_exists('getallheaders') ? getallheaders() : [];
$token = trim(
    $headers['Authorization']
    ?? $headers['authorization']
    ?? $_SERVER['HTTP_AUTHORIZATION']
    ?? ''
);
$userId = 0;

if (!empty($token)) {
    $stmtSes = mysqli_prepare($conexion, "SELECT user_id FROM sesiones WHERE Token = ? LIMIT 1");
    mysqli_stmt_bind_param($stmtSes, "s", $token);
    mysqli_stmt_execute($stmtSes);
    $ses = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtSes));
    if ($ses) {
        $userId = (int)$ses['user_id'];
    }
}

// Comprobar si la columna avatar existe en users
$tieneAvatar = false;
$resColAvatar = mysqli_query($conexion, "SHOW COLUMNS FROM users LIKE 'avatar'");
if ($resColAvatar && mysqli_num_rows($resColAvatar) > 0) {
    $tieneAvatar = true;
}

$campoAvatar = $tieneAvatar ? "COALESCE(NULLIF(u.avatar, ''), 'images/guest.png')" : "'images/guest.png'";
$groupAvatar = $tieneAvatar ? ", u.avatar" : "";

// Detectar columna de puntuación en ratings (priorizando 'stars')
$colScore = 'stars';
$resCols = mysqli_query($conexion, "SHOW COLUMNS FROM ratings");
if ($resCols) {
    $camposRatings = [];
    while ($c = mysqli_fetch_assoc($resCols)) {
        $camposRatings[] = $c['Field'];
    }
    if (!in_array('stars', $camposRatings, true)) {
        foreach ($camposRatings as $campo) {
            if (!in_array($campo, ['id_rating', 'created_at', 'id_user', 'id_publication', 'id_publications'], true)) {
                $colScore = $campo;
                break;
            }
        }
    }
}

// Detectar nombres exactos (singular/plural) en las tablas intermedias
$colUpPub = 'id_publications';
if ($r = mysqli_query($conexion, "SHOW COLUMNS FROM users_publications")) {
    while ($c = mysqli_fetch_assoc($r)) {
        if (stripos($c['Field'], 'pub') !== false) $colUpPub = $c['Field'];
    }
}

$colRpPub = 'id_publications';
$colRpRat = 'id_rating';
if ($r = mysqli_query($conexion, "SHOW COLUMNS FROM ratings_publications")) {
    while ($c = mysqli_fetch_assoc($r)) {
        if (stripos($c['Field'], 'pub') !== false) $colRpPub = $c['Field'];
        if (stripos($c['Field'], 'rat') !== false) $colRpRat = $c['Field'];
    }
}

$colUrRat = 'id_rating';
if ($r = mysqli_query($conexion, "SHOW COLUMNS FROM users_ratings")) {
    while ($c = mysqli_fetch_assoc($r)) {
        if (stripos($c['Field'], 'rat') !== false) $colUrRat = $c['Field'];
    }
}

$sql = "SELECT p.id_publication,
               p.title,
               p.content,
               p.created_at,
               COALESCE(u.username, 'Usuario') AS author,
               $campoAvatar AS avatar,
               COUNT(r.id_rating) AS total_votos,
               COALESCE(ROUND(AVG(r.`$colScore`), 1), 0) AS promedio
        FROM publications p
        LEFT JOIN users_publications up ON p.id_publication = up.`$colUpPub`
        LEFT JOIN users u ON up.id_user = u.id_user
        LEFT JOIN ratings_publications rp ON p.id_publication = rp.`$colRpPub`
        LEFT JOIN ratings r ON rp.`$colRpRat` = r.id_rating
        GROUP BY p.id_publication, p.title, p.content, p.created_at, u.username $groupAvatar
        ORDER BY p.id_publication DESC";

$res = mysqli_query($conexion, $sql);
$publicaciones = [];

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $idPub = (int)$row['id_publication'];
        $miCalificacion = 0;

        if ($userId > 0) {
            $sqlMiVoto = "SELECT r.`$colScore` AS voto
                          FROM ratings_publications rp
                          INNER JOIN users_ratings ur ON rp.`$colRpRat` = ur.`$colUrRat`
                          INNER JOIN ratings r ON rp.`$colRpRat` = r.id_rating
                          WHERE rp.`$colRpPub` = $idPub AND ur.id_user = $userId
                          LIMIT 1";
            $resMiVoto = mysqli_query($conexion, $sqlMiVoto);
            if ($resMiVoto && $votoRow = mysqli_fetch_assoc($resMiVoto)) {
                $miCalificacion = (int)$votoRow['voto'];
            }
        }

        $publicaciones[] = [
            "id_publication"  => $idPub,
            "title"           => $row['title'],
            "content"         => $row['content'],
            "created_at"      => $row['created_at'],
            "author"          => $row['author'],
            "avatar"          => $row['avatar'],
            "total_votos"     => (int)$row['total_votos'],
            "promedio"        => (float)$row['promedio'],
            "mi_calificacion" => $miCalificacion
        ];
    }
}

echo json_encode([
    "ok"            => true,
    "publicaciones" => $publicaciones
]);
?>