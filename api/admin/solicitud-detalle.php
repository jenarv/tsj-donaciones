<?php
/**
 * API: Obtener detalle de una solicitud
 * Endpoint: GET /api/admin/solicitud-detalle.php?id=123
 */

session_start();

header('Access-Control-Allow-Origin: http://127.0.0.1:5500'); // ← Cambio importante
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Solicitud.php';
require_once __DIR__ . '/../../classes/Usuario.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

try {
    // Verificar sesión
    $usuario = new Usuario();
    if (!$usuario->verificarSesion()) {
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado']);
        exit;
    }

    if (empty($_GET['id'])) {
        throw new Exception("ID de solicitud requerido");
    }

    $solicitud = new Solicitud();
    $detalle = $solicitud->obtenerPorId($_GET['id']);

    if (!$detalle) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Solicitud no encontrada'
        ]);
        exit;
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $detalle
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
