<?php
/**
 * API: Listar solicitudes (Panel Admin)
 * Endpoint: GET /api/admin/solicitudes.php?estatus=Reservado
 */
session_start();
header('Access-Control-Allow-Origin: http://127.0.0.1:5500');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

    $user_data = $usuario->obtenerUsuarioActual();
    
    error_log("User data: " . print_r($user_data, true));
    
    $solicitud = new Solicitud();
    
    // Filtros opcionales
    $filtros = [];
    if (isset($_GET['estatus'])) {
        $filtros['estatus'] = $_GET['estatus'];
    }
    if (isset($_GET['carrera'])) {
        $filtros['carrera'] = $_GET['carrera'];
    }
    if (isset($_GET['limit'])) {
        $filtros['limit'] = (int)$_GET['limit'];
    }
    
    // Agregar filtro de departamento (0 = todos, cualquier otro número = solo ese depto)
    $filtros['id_departamento'] = $user_data['id_departamento'] ?? 0;
    
    error_log("Filtros enviados a obtenerTodas: " . print_r($filtros, true));
    
    $solicitudes = $solicitud->obtenerTodas($filtros);
    
    error_log("Solicitudes obtenidas: " . count($solicitudes));
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $solicitudes,
        'count' => count($solicitudes),
        'user_info' => [
            'rol_tipo' => $user_data['rol_tipo'],
            'id_departamento' => $user_data['id_departamento'] ?? 0,
            'departamento' => $user_data['departamento'] ?? 'Todos'
        ],
        'debug' => [
            'filtros_aplicados' => $filtros
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error en solicitudes.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}