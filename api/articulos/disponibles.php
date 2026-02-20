<?php
/**
 * API: Obtener artículos disponibles
 * Endpoint: GET /api/articulos/disponibles.php?categoria=laboratorios&carrera=ISIC
 * 
 * This version maintains backward compatibility while adding role-based filtering
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
    $db = Database::getInstance()->getConnection();
    
    // Get optional parameters
    $categoria = $_GET['categoria'] ?? null;
    $carrera = $_GET['carrera'] ?? null;
    
    // Map frontend categories to database enum
    $categoria_map = [
        'laboratorios' => 'Laboratorio',
        'medica' => 'Medica',
        'deportes' => 'Deportes'
    ];
    
    if ($categoria && isset($categoria_map[$categoria])) {
        $categoria = $categoria_map[$categoria];
    }
    
    // Map career codes to department IDs
    $carrera_to_dept = [
        'TODOS' => 0,
        'ICIV' => 1,
        'IIND' => 2,
        'ISIC' => 3,
        'IELEC' => 4,
        'IGE' => 5,
        'GAST' => 6,
        'IELEM' => 7,
        'ARQ' => 8,
        'MELEC' => 9,
        'MSIC' => 10
    ];
    
    $id_departamento = null;
    if ($carrera && isset($carrera_to_dept[$carrera])) {
        $id_departamento = $carrera_to_dept[$carrera];
    }
    
    // Build query - show Universal + Department-specific articles
    $sql = "SELECT ad.*
            FROM articulos_disponibles ad
            WHERE 1=1";
    
    $params = [];
    
    // Filter by category if provided
    if ($categoria) {
        $sql .= " AND ad.categoria = :categoria";
        $params[':categoria'] = $categoria;
    }
    
    // Filter by department access
    if ($id_departamento) {
        $sql .= " AND (
            ad.tipo_acceso = 'Universal'
            OR ad.categoria_departamento = :id_departamento
        )";
        $params[':id_departamento'] = $id_departamento;
    } else {
        // If no department selected, only show universal items
        $sql .= " AND ad.tipo_acceso = 'Universal'";
    }
    
    $sql .= " ORDER BY ad.nombre";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get department info for debugging
    $dept_info = null;
    if ($id_departamento) {
        $sql_dept = "SELECT * FROM departamentos WHERE id_departamento = :id";
        $stmt_dept = $db->prepare($sql_dept);
        $stmt_dept->execute([':id' => $id_departamento]);
        $dept_info = $stmt_dept->fetch(PDO::FETCH_ASSOC);
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $articulos,
        'count' => count($articulos),
        'debug_info' => [
            'carrera_recibida' => $carrera,
            'id_departamento' => $id_departamento,
            'departamento' => $dept_info ? $dept_info['nombre_departamento'] : 'No seleccionado',
            'categoria' => $categoria
        ]
    ]);

} catch (Exception $e) {
    error_log("Error en /api/articulos/disponibles.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
