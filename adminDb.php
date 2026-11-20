<?php
header('Content-Type: application/json; charset=utf-8');
include_once("conexion-waffle.php");

//Validar token y verificar que el usuario tenga rol 'admin'
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
    echo json_encode(["ok" => false, "error" => "No autorizado. Inicia sesión."]);
    exit;
}

$tieneRol = mysqli_num_rows(mysqli_query($conexion, "SHOW COLUMNS FROM users LIKE 'role'")) > 0;
$campoRol = $tieneRol ? "u.role" : "IF(u.username = 'admin', 'admin', 'user') AS role";

$stmtAuth = mysqli_prepare($conexion, "SELECT u.id_user, u.username, $campoRol FROM sesiones s INNER JOIN users u ON s.user_id = u.id_user WHERE s.Token = ? LIMIT 1");
mysqli_stmt_bind_param($stmtAuth, "s", $token);
mysqli_stmt_execute($stmtAuth);
$usuario = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtAuth));

if (!$usuario || $usuario['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "Acceso denegado. Esta sección es exclusiva para administradores."]);
    exit;
}

// Obtener lista de tablas existentes en waffle_db
$tablasValidas = [];
$resTablas = mysqli_query($conexion, "SHOW TABLES");
while ($row = mysqli_fetch_row($resTablas)) {
    $tablasValidas[] = $row[0];
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$accion = $_GET['action'] ?? $data['action'] ?? 'listar_tablas';
$tabla  = $_GET['tabla'] ?? $data['tabla'] ?? '';

function obtenerEstructuraTabla($conexion, $tabla) {
    $columnas = [];
    $pk = null;
    $res = mysqli_query($conexion, "DESCRIBE `$tabla`");
    while ($col = mysqli_fetch_assoc($res)) {
        $columnas[] = $col;
        if ($col['Key'] === 'PRI' && $pk === null) {
            $pk = $col['Field'];
        }
    }
    return ["columnas" => $columnas, "pk" => $pk];
}
//LISTA DE ACCIONES DISPONIBLES:
try {
    // ACCIÓN A: Listar todas las tablas y su cantidad de registros
    if ($accion === 'listar_tablas') {
        $infoTablas = [];
        foreach ($tablasValidas as $t) {
            $resCount = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM `$t`");
            $total = (int)mysqli_fetch_assoc($resCount)['total'];
            $infoTablas[] = ["nombre" => $t, "total" => $total];
        }
        echo json_encode(["ok" => true, "tablas" => $infoTablas]);
        exit;
    }

    // Validar que la tabla solicitada exista en waffle_db
    if (!in_array($tabla, $tablasValidas, true)) {
        echo json_encode(["ok" => false, "error" => "Tabla no válida."]);
        exit;
    }

    $estructura = obtenerEstructuraTabla($conexion, $tabla);
    $columnas = $estructura['columnas'];
    $pk = $estructura['pk'];
    $nombresColumnas = array_column($columnas, 'Field');

    // ACCIÓN B: Obtener columnas y filas de una tabla
    if ($accion === 'obtener_tabla') {
        $orderBy = $pk ? "ORDER BY `$pk` ASC" : "";
        $resFilas = mysqli_query($conexion, "SELECT * FROM `$tabla` $orderBy");
        $filas = [];
        while ($fila = mysqli_fetch_assoc($resFilas)) {
            $filas[] = $fila;
        }
        echo json_encode([
            "ok" => true,
            "tabla" => $tabla,
            "pk" => $pk,
            "columnas" => $columnas,
            "filas" => $filas
        ]);
        exit;
    }

    // ACCIÓN C: Actualizar/Modificar una fila existente
    if ($accion === 'actualizar_fila') {
        $pkVal = $data['pk_val'] ?? null;
        $valores = $data['valores'] ?? [];

        if (!$pk || $pkVal === null || !is_array($valores)) {
            echo json_encode(["ok" => false, "error" => "Faltan datos para actualizar el registro."]);
            exit;
        }

        $sets = [];
        foreach ($valores as $col => $val) {
            if (!in_array($col, $nombresColumnas, true) || $col === $pk) continue;

            // Si editan password_hash en users con texto plano (no 64 hex), lo encripta en SHA-256
            if ($tabla === 'users' && $col === 'password_hash' && !preg_match('/^[a-f0-9]{64}$/i', $val)) {
                $val = hash('sha256', $val);
            }

            $valEsc = mysqli_real_escape_string($conexion, (string)$val);
            $sets[] = "`$col` = '$valEsc'";
        }

        if (empty($sets)) {
            echo json_encode(["ok" => false, "error" => "No hay columnas modificables."]);
            exit;
        }

        $pkEsc = mysqli_real_escape_string($conexion, (string)$pkVal);
        $sql = "UPDATE `$tabla` SET " . implode(", ", $sets) . " WHERE `$pk` = '$pkEsc'";
        mysqli_query($conexion, $sql);

        echo json_encode(["ok" => true]);
        exit;
    }

    // ACCIÓN D: Insertar un nuevo registro
    if ($accion === 'insertar_fila') {
        $valores = $data['valores'] ?? [];
        $colsInsert = [];
        $valsInsert = [];

        foreach ($columnas as $colInfo) {
            $col = $colInfo['Field'];
            if (strpos($colInfo['Extra'], 'auto_increment') !== false) continue;
            if (!isset($valores[$col]) || $valores[$col] === '') continue;

            $val = (string)$valores[$col];
            if ($tabla === 'users' && $col === 'password_hash' && !preg_match('/^[a-f0-9]{64}$/i', $val)) {
                $val = hash('sha256', $val);
            }

            $colsInsert[] = "`$col`";
            $valsInsert[] = "'" . mysqli_real_escape_string($conexion, $val) . "'";
        }

        if (empty($colsInsert)) {
            echo json_encode(["ok" => false, "error" => "Completa al menos un campo para insertar."]);
            exit;
        }

        $sql = "INSERT INTO `$tabla` (" . implode(", ", $colsInsert) . ") VALUES (" . implode(", ", $valsInsert) . ")";
        mysqli_query($conexion, $sql);

        echo json_encode(["ok" => true, "id" => mysqli_insert_id($conexion)]);
        exit;
    }

    // ACCIÓN E: Eliminar un registro
    if ($accion === 'eliminar_fila') {
        $pkVal = $data['pk_val'] ?? null;
        if (!$pk || $pkVal === null) {
            echo json_encode(["ok" => false, "error" => "Identificador de fila inválido."]);
            exit;
        }

        if ($tabla === 'users' && (int)$pkVal === (int)$usuario['id_user']) {
            echo json_encode(["ok" => false, "error" => "No podés eliminar tu propia cuenta de administrador."]);
            exit;
        }

        $pkEsc = mysqli_real_escape_string($conexion, (string)$pkVal);
        mysqli_query($conexion, "DELETE FROM `$tabla` WHERE `$pk` = '$pkEsc'");

        echo json_encode(["ok" => true]);
        exit;
    }

    echo json_encode(["ok" => false, "error" => "Acción no reconocida."]);
} catch (mysqli_sql_exception $e) {
    echo json_encode(["ok" => false, "error" => "Error MySQL: " . $e->getMessage()]);
}
?>