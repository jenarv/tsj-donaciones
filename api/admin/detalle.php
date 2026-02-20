<?php
/**
 * API endpoint para obtener detalles de un artículo específico
 * Ruta: /api/articulos/detalle.php
 */

session_start();
header('Access-Control-Allow-Origin: http://127.0.0.1:5500'); 
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Articulo.php';
require_once __DIR__ . '/../../classes/Usuario.php';


// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'No autorizado'
    ]);
    exit;
}

// Verificar método GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido'
    ]);
    exit;
}

try {
    // Obtener ID del artículo
    $id_paquete = $_GET['id'] ?? null;
    
    if (!$id_paquete) {
        throw new Exception('ID de paquete requerido');
    }

    // Obtener artículo
    $articulo = new Articulo();
    $data = $articulo->obtenerPorId($id_paquete);

    if (!$data) {
        throw new Exception('Artículo no encontrado');
    }

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}