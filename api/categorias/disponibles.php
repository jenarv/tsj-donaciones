<?php
/**
 * API: Obtener categorías disponibles según el rol/departamento
 * Endpoint: GET /api/categorias/disponibles.php?tipo=admin
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
    $tipo = $_GET['tipo'] ?? 'admin';
    $db = Database::getInstance()->getConnection();
    
    // En la sección de admin, cambiar:

if ($tipo === 'admin') {
    // Verify admin session
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Get user info
    $sql_user = "SELECT rol_tipo, id_departamento FROM usuarios_admin WHERE id_usuario = :id AND activo = 1";
    $stmt_user = $db->prepare($sql_user);
    $stmt_user->execute([':id' => $user_id]);
    $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_info) {
        http_response_code(401);
        echo json_encode(['error' => 'Usuario no encontrado']);
        exit;
    }
    
    // Get accessible categories
    $sql = "SELECT c.*
            FROM categorias c
            WHERE c.activo = 1
            AND (
                :id_departamento = 0
                OR c.tipo_acceso = 'Universal'
                OR c.id_departamento = :id_departamento
            )
            ORDER BY c.nombre_categoria";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':id_departamento' => $user_info['id_departamento'] ?? 0
    ]);
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else if ($tipo === 'estudiante') {
        $id_estudiante = $_GET['id'] ?? null;
        
        if (!$id_estudiante) {
            $id_estudiante = $_SESSION['student_id'] ?? null;
        }
        
        if (!$id_estudiante) {
            http_response_code(400);
            echo json_encode(['error' => 'ID de estudiante requerido']);
            exit;
        }
        
        // Get student's department
        $sql_student = "SELECT id_departamento FROM estudiantes WHERE id_estudiante = :id AND activo = 1";
        $stmt_student = $db->prepare($sql_student);
        $stmt_student->execute([':id' => $id_estudiante]);
        $student_info = $stmt_student->fetch(PDO::FETCH_ASSOC);
        
        if (!$student_info) {
            http_response_code(404);
            echo json_encode(['error' => 'Estudiante no encontrado']);
            exit;
        }
        
        // Get accessible categories
        $sql = "SELECT c.*
                FROM categorias c
                WHERE c.activo = 1
                AND (
                    c.tipo_acceso = 'Universal'
                    OR c.id_departamento = :id_departamento
                )
                ORDER BY c.nombre_categoria";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':id_departamento' => $student_info['id_departamento']]);
        $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Tipo inválido']);
        exit;
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $categorias,
        'count' => count($categorias)
    ]);

} catch (Exception $e) {
    error_log("Error en /api/categorias/disponibles.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al cargar categorías: ' . $e->getMessage()
    ]);
}
