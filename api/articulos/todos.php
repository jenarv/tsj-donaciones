<?php
/**
 * API: Obtener todos los artículos (Admin) - Compatible version
 * Endpoint: GET /api/articulos/todos.php
 */

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

try {
    // Verificar sesión
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado']);
        exit;
    }

    $db = Database::getInstance()->getConnection();
    $user_id = $_SESSION['user_id'];
    
    // Get user role info
    $sql_user = "SELECT rol_tipo, id_departamento FROM usuarios_admin WHERE id_usuario = :id AND activo = 1";
    $stmt_user = $db->prepare($sql_user);
    $stmt_user->execute([':id' => $user_id]);
    $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_info) {
        http_response_code(401);
        echo json_encode(['error' => 'Usuario no encontrado']);
        exit;
    }
    
    // Use stored procedure to get filtered articles
    $sql = "CALL obtener_articulos_para_admin(:id_usuario)";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id_usuario' => $user_id]);
    $articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Close the cursor so we can run more queries
    $stmt->closeCursor();
    
    // Add solicitudes_activas count for each article
    foreach ($articulos as &$art) {
        $sql_count = "SELECT COUNT(*) as count 
                      FROM solicitudes 
                      WHERE id_paquete = :id 
                      AND estatus IN ('Reservado', 'Aprobado', 'En_espera')";
        $stmt_count = $db->prepare($sql_count);
        $stmt_count->execute([':id' => $art['id_paquete']]);
        $count_result = $stmt_count->fetch(PDO::FETCH_ASSOC);
        $art['solicitudes_activas'] = $count_result['count'];
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $articulos,
        'count' => count($articulos),
        'user_info' => [
            'rol_tipo' => $user_info['rol_tipo'],
            'id_departamento' => $user_info['id_departamento']
        ]
    ]);

} catch (Exception $e) {
    error_log("Error en /api/articulos/todos.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al cargar artículos: ' . $e->getMessage()
    ]);
}
