<?php
header('Content-Type: application/json');
include("validacionToken.php");

$data = json_decode(file_get_contents('php://input'), true);
$itemId = mysqli_real_escape_string($conexion, trim($data['item_id'] ?? ''));

$catalogo = [
    'logo-chocolate'   => 150,
    'logo-frutilla'    => 150,
    'logo-pixel'       => 250,
    'logo-shiny'       => 500,
    'logo-confeti'     => 300,
    'logo-friv'        => 150,
    'fondo-dark'       => 100,
    'fondo-neon'       => 200,
    'fondo-warm'       => 200,
    'font-bubblegum'   => 100,
    'font-grobold'     => 120,
    'font-minecraftia' => 150,
    'font-press-start' => 200,
    'font-upheaval'    => 180,
    'font-vcr'         => 150,
    'font-vt323'       => 150
];

if (!isset($catalogo[$itemId])) {
    echo json_encode(["ok" => false, "error" => "Artículo no válido."]);
    exit;
}

$precio = $catalogo[$itemId];

$checkCompra = mysqli_query($conexion, "SELECT id_user_item FROM user_items WHERE id_user = $user_id AND item_id = '$itemId' LIMIT 1");
if ($checkCompra && mysqli_num_rows($checkCompra) > 0) {
    echo json_encode(["ok" => false, "error" => "Ya posees este artículo."]);
    exit;
}

$resUser = mysqli_query($conexion, "SELECT points FROM users WHERE id_user = $user_id LIMIT 1");
$user = mysqli_fetch_assoc($resUser);
$puntosActuales = (int)($user['points'] ?? 0);

if ($puntosActuales < $precio) {
    echo json_encode(["ok" => false, "error" => "No tienes suficientes puntos (necesitas $precio pts)."]);
    exit;
}

mysqli_begin_transaction($conexion);

try {
    $nuevosPuntos = $puntosActuales - $precio;
    mysqli_query($conexion, "UPDATE users SET points = $nuevosPuntos WHERE id_user = $user_id");
    mysqli_query($conexion, "INSERT INTO user_items (id_user, item_id) VALUES ($user_id, '$itemId')");
    mysqli_commit($conexion);

    echo json_encode([
        "ok" => true,
        "item_id" => $itemId,
        "nuevosPuntos" => $nuevosPuntos
    ]);
} catch (Exception $e) {
    mysqli_rollback($conexion);
    echo json_encode(["ok" => false, "error" => "Error al procesar la compra en la base de datos."]);
}
?>