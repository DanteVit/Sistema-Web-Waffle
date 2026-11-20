<?php
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

try {
    include_once("conexion-waffle.php");

    if (!isset($conexion) || !$conexion) {
        echo json_encode(["ok" => false, "error" => "No hay conexión con la base de datos."]);
        exit;
    }

    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $token = trim(
        $headers['Authorization']
        ?? $headers['authorization']
        ?? $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? ''
    );

    if (empty($token)) {
        echo json_encode(["ok" => false, "error" => "Debes iniciar sesión para publicar."]);
        exit;
    }

    $stmtSes = mysqli_prepare($conexion, "SELECT user_id FROM sesiones WHERE Token = ? LIMIT 1");
    mysqli_stmt_bind_param($stmtSes, "s", $token);
    mysqli_stmt_execute($stmtSes);
    $sesion = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtSes));

    if (!$sesion) {
        echo json_encode(["ok" => false, "error" => "Sesión expirada. Volvé a iniciar sesión."]);
        exit;
    }

    $userId = (int)$sesion['user_id'];
    $data = json_decode(file_get_contents('php://input'), true);

    $title   = trim($data['title'] ?? '');
    $content = trim($data['content'] ?? '');

    if (empty($title) || empty($content)) {
        echo json_encode(["ok" => false, "error" => "Completá el título y el contenido de la publicación."]);
        exit;
    }

    // Detectar columnas de publications
    $colsPub = [];
    $resCols = mysqli_query($conexion, "SHOW COLUMNS FROM publications");
    while ($c = mysqli_fetch_assoc($resCols)) {
        $colsPub[$c['Field']] = $c;
    }

    $colTitle   = isset($colsPub['title']) ? 'title' : 'titulo';
    $colContent = isset($colsPub['content']) ? 'content' : 'contenido';

    $campos = ["`$colTitle`", "`$colContent`"];
    $placeholders = ["?", "?"];
    $tipos = "ss";
    $valores = [$title, $content];

    if (isset($colsPub['id_user'])) {
        $campos[] = "`id_user`";
        $placeholders[] = "?";
        $tipos .= "i";
        $valores[] = $userId;
    } elseif (isset($colsPub['user_id'])) {
        $campos[] = "`user_id`";
        $placeholders[] = "?";
        $tipos .= "i";
        $valores[] = $userId;
    }

    $sqlPub = "INSERT INTO publications (" . implode(", ", $campos) . ") VALUES (" . implode(", ", $placeholders) . ")";
    $stmtPub = mysqli_prepare($conexion, $sqlPub);
    mysqli_stmt_bind_param($stmtPub, $tipos, ...$valores);
    mysqli_stmt_execute($stmtPub);

    $idPub = mysqli_insert_id($conexion);

    // Insertar relación en users_publications detectando el nombre exacto de las columnas
    $resTablaRel = mysqli_query($conexion, "SHOW TABLES LIKE 'users_publications'");
    if ($resTablaRel && mysqli_num_rows($resTablaRel) > 0) {
        $colRelUser = 'id_user';
        $colRelPub  = 'id_publications';
        $resColsRel = mysqli_query($conexion, "SHOW COLUMNS FROM users_publications");
        while ($cr = mysqli_fetch_assoc($resColsRel)) {
            if (stripos($cr['Field'], 'user') !== false) {
                $colRelUser = $cr['Field'];
            } elseif (stripos($cr['Field'], 'pub') !== false) {
                $colRelPub = $cr['Field'];
            }
        }
        $stmtRel = mysqli_prepare($conexion, "INSERT INTO users_publications (`$colRelUser`, `$colRelPub`) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmtRel, "ii", $userId, $idPub);
        mysqli_stmt_execute($stmtRel);
    }

    echo json_encode([
        "ok"             => true,
        "id_publication" => $idPub
    ]);
} catch (Throwable $e) {
    echo json_encode([
        "ok"    => false,
        "error" => "Error en crearPublicacion.php: " . $e->getMessage()
    ]);
}
?>