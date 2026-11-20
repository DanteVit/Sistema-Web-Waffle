<?php
header('Content-Type: application/json; charset=utf-8');
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
    http_response_code(401);
    echo json_encode(["ok" => false, "error" => "No autorizado. Iniciá sesión."]);
    exit;
}

// Verificar usuario y rol
$tieneRol = false;
$resColRol = mysqli_query($conexion, "SHOW COLUMNS FROM users LIKE 'role'");
if ($resColRol && mysqli_num_rows($resColRol) > 0) {
    $tieneRol = true;
}

$campoRol = $tieneRol ? "u.role" : "IF(u.username = 'admin', 'admin', 'user') AS role";
$stmtAuth = mysqli_prepare($conexion, "SELECT u.id_user, u.username, $campoRol FROM sesiones s INNER JOIN users u ON s.user_id = u.id_user WHERE s.Token = ? LIMIT 1");
mysqli_stmt_bind_param($stmtAuth, "s", $token);
mysqli_stmt_execute($stmtAuth);
$usuario = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtAuth));

$rol = $usuario['role'] ?? 'user';
if (!$usuario || !in_array($rol, ['admin', 'moderator'], true)) {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "Acceso denegado. Solo administradores y moderadores pueden eliminar publicaciones."]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$idPub = (int)($data['id_publication'] ?? 0);

if ($idPub <= 0) {
    echo json_encode(["ok" => false, "error" => "ID de publicación inválido."]);
    exit;
}

// Detectar columna de publicación en ratings_publications
$colRpPub = 'id_publications';
$colRpRat = 'id_rating';
if ($r = mysqli_query($conexion, "SHOW COLUMNS FROM ratings_publications")) {
    while ($c = mysqli_fetch_assoc($r)) {
        if (stripos($c['Field'], 'pub') !== false) $colRpPub = $c['Field'];
        if (stripos($c['Field'], 'rat') !== false) $colRpRat = $c['Field'];
    }
}

// Eliminar calificaciones asociadas para no dejar registros sueltos (aunque ON DELETE CASCADE debería encargarse de esto)
mysqli_query($conexion, "DELETE r FROM ratings r INNER JOIN ratings_publications rp ON r.id_rating = rp.`$colRpRat` WHERE rp.`$colRpPub` = $idPub");

// Eliminar la publicación (por ON DELETE CASCADE limpia users_publications y ratings_publications)
$stmtDel = mysqli_prepare($conexion, "DELETE FROM publications WHERE id_publication = ?");
mysqli_stmt_bind_param($stmtDel, "i", $idPub);

if (mysqli_stmt_execute($stmtDel) && mysqli_stmt_affected_rows($stmtDel) > 0) {
    echo json_encode(["ok" => true]);
} else {
    echo json_encode(["ok" => false, "error" => "No se encontró la publicación o ya fue eliminada."]);
}
?>