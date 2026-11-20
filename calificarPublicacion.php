<?php
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

function obtenerInfoTabla($conexion, $tabla) {
    $cols = [];
    $res = mysqli_query($conexion, "SHOW COLUMNS FROM `$tabla`");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $cols[] = $row;
        }
    }
    return $cols;
}

function existeTabla($conexion, $tabla) {
    $res = mysqli_query($conexion, "SHOW TABLES LIKE '$tabla'");
    return ($res && mysqli_num_rows($res) > 0);
}

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
        echo json_encode(["ok" => false, "error" => "Debes iniciar sesión para calificar."]);
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

    $idPub = (int)($data['id_publication'] ?? 0);
    $stars = (int)($data['stars'] ?? 0);

    if ($idPub <= 0 || $stars < 1 || $stars > 5) {
        echo json_encode(["ok" => false, "error" => "Calificación inválida (debe ser entre 1 y 5 estrellas)."]);
        exit;
    }

    if (!existeTabla($conexion, 'ratings') || !existeTabla($conexion, 'ratings_publications') || !existeTabla($conexion, 'users_ratings')) {
        echo json_encode(["ok" => false, "error" => "Faltan las tablas de calificaciones en waffle_db."]);
        exit;
    }

    // Detectar columnas reales de ratings priorizando 'stars'
    $infoRat = obtenerInfoTabla($conexion, 'ratings');
    $nombresRat = array_column($infoRat, 'Field');
    $colRatId = $nombresRat[0] ?? 'id_rating';
    foreach ($infoRat as $c) {
        if ($c['Key'] === 'PRI') {
            $colRatId = $c['Field'];
            break;
        }
    }

    $colScore = null;
    foreach (['stars', 'score', 'rating', 'estrellas', 'calificacion', 'puntuacion'] as $cand) {
        if (in_array($cand, $nombresRat, true)) {
            $colScore = $cand;
            break;
        }
    }
    if (!$colScore) {
        foreach ($infoRat as $c) {
            if ($c['Key'] !== 'PRI' && stripos($c['Type'], 'int') !== false) {
                $colScore = $c['Field'];
                break;
            }
        }
    }
    $colScore = $colScore ?: 'stars';

    // Detectar columnas reales de ratings_publications
    $colRpPub = 'id_publications';
    $colRpRat = 'id_ratings';
    foreach (obtenerInfoTabla($conexion, 'ratings_publications') as $c) {
        if (stripos($c['Field'], 'pub') !== false) $colRpPub = $c['Field'];
        if (stripos($c['Field'], 'rat') !== false || stripos($c['Field'], 'calif') !== false) $colRpRat = $c['Field'];
    }

    // Detectar columnas reales de users_ratings
    $colUrUser = 'id_user';
    $colUrRat  = 'id_ratings';
    foreach (obtenerInfoTabla($conexion, 'users_ratings') as $c) {
        if (stripos($c['Field'], 'user') !== false) $colUrUser = $c['Field'];
        if (stripos($c['Field'], 'rat') !== false || stripos($c['Field'], 'calif') !== false) $colUrRat = $c['Field'];
    }

    // Verificar si el usuario ya calificó esta publicación
    $sqlExiste = "SELECT r.`$colRatId` AS id_rating
                  FROM ratings_publications rp
                  INNER JOIN users_ratings ur ON rp.`$colRpRat` = ur.`$colUrRat`
                  INNER JOIN ratings r ON rp.`$colRpRat` = r.`$colRatId`
                  WHERE rp.`$colRpPub` = ? AND ur.`$colUrUser` = ?
                  LIMIT 1";
    $stmtExiste = mysqli_prepare($conexion, $sqlExiste);
    mysqli_stmt_bind_param($stmtExiste, "ii", $idPub, $userId);
    mysqli_stmt_execute($stmtExiste);
    $votoPrevio = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtExiste));

    if ($votoPrevio) {
        $idRating = (int)$votoPrevio['id_rating'];
        $stmtUpd = mysqli_prepare($conexion, "UPDATE ratings SET `$colScore` = ? WHERE `$colRatId` = ?");
        mysqli_stmt_bind_param($stmtUpd, "ii", $stars, $idRating);
        mysqli_stmt_execute($stmtUpd);
    } else {
        $camposIns = ["`$colScore`"];
        $placeIns  = ["?"];
        $tiposIns  = "i";
        $valsIns   = [$stars];

        if (in_array('id_user', $nombresRat, true)) {
            $camposIns[] = "`id_user`";
            $placeIns[]  = "?";
            $tiposIns   .= "i";
            $valsIns[]   = $userId;
        } elseif (in_array('user_id', $nombresRat, true)) {
            $camposIns[] = "`user_id`";
            $placeIns[]  = "?";
            $tiposIns   .= "i";
            $valsIns[]   = $userId;
        }

        if (in_array('id_publication', $nombresRat, true)) {
            $camposIns[] = "`id_publication`";
            $placeIns[]  = "?";
            $tiposIns   .= "i";
            $valsIns[]   = $idPub;
        } elseif (in_array('id_publications', $nombresRat, true)) {
            $camposIns[] = "`id_publications`";
            $placeIns[]  = "?";
            $tiposIns   .= "i";
            $valsIns[]   = $idPub;
        }

        $sqlIns = "INSERT INTO ratings (" . implode(", ", $camposIns) . ") VALUES (" . implode(", ", $placeIns) . ")";
        $stmtIns = mysqli_prepare($conexion, $sqlIns);
        mysqli_stmt_bind_param($stmtIns, $tiposIns, ...$valsIns);
        if (!mysqli_stmt_execute($stmtIns)) {
            echo json_encode(["ok" => false, "error" => "Error al insertar calificación: " . mysqli_error($conexion)]);
            exit;
        }
        $idRating = mysqli_insert_id($conexion);

        $stmtRp = mysqli_prepare($conexion, "INSERT INTO ratings_publications (`$colRpPub`, `$colRpRat`) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmtRp, "ii", $idPub, $idRating);
        mysqli_stmt_execute($stmtRp);

        $stmtUr = mysqli_prepare($conexion, "INSERT INTO users_ratings (`$colUrUser`, `$colUrRat`) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmtUr, "ii", $userId, $idRating);
        mysqli_stmt_execute($stmtUr);
    }

    // Recalcular promedio y total de votos
    $sqlAvg = "SELECT COUNT(r.`$colRatId`) AS total, COALESCE(ROUND(AVG(r.`$colScore`), 1), 0) AS prom
               FROM ratings_publications rp
               INNER JOIN ratings r ON rp.`$colRpRat` = r.`$colRatId`
               WHERE rp.`$colRpPub` = ?";
    $stmtAvg = mysqli_prepare($conexion, $sqlAvg);
    mysqli_stmt_bind_param($stmtAvg, "i", $idPub);
    mysqli_stmt_execute($stmtAvg);
    $avgRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtAvg));

    echo json_encode([
        "ok"            => true,
        "mis_estrellas" => $stars,
        "promedio"      => (float)($avgRow['prom'] ?? $stars),
        "total_votos"   => (int)($avgRow['total'] ?? 1)
    ]);
} catch (Throwable $e) {
    echo json_encode([
        "ok"    => false,
        "error" => "Error en calificarPublicacion.php: " . $e->getMessage()
    ]);
}
?>