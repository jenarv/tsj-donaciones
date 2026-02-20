<?php
/**
 * API: Check Student Authentication Status
 * Returns the current student's auth status and request status
 */

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';

try {
    if (!isset($_SESSION['student_auth'])) {
        echo json_encode([
            'authenticated' => false,
            'message' => 'No autenticado'
        ]);
        exit;
    }
    
    $student = $_SESSION['student_auth'];
    
    // Get current request status
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT s.*, ca.nombre as nombre_articulo
        FROM solicitudes s
        LEFT JOIN catalogo_articulos ca ON s.id_paquete = ca.id_paquete
        WHERE s.id_estudiante = :id
        ORDER BY s.fecha_solicitud DESC
        LIMIT 1
    ");
    $stmt->execute([':id' => $student['id_estudiante']]);
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $response = [
        'authenticated' => true,
        'student' => [
            'id' => $student['id_estudiante'],
            'email' => $student['email'],
            'nombre' => $student['nombre'],
            'picture' => $student['picture']
        ]
    ];
    
    if ($solicitud) {
        $can_submit = false;
        
        // Determine if student can submit a new request
        if ($solicitud['estatus'] === 'Rechazado' || $solicitud['estatus'] === 'Expirado') {
            $can_submit = true;
        }
        
        $response['solicitud'] = [
            'existe' => true,
            'estatus' => $solicitud['estatus'],
            'id_paquete' => $solicitud['id_paquete'],
            'nombre_articulo' => $solicitud['nombre_articulo'],
            'fecha_solicitud' => $solicitud['fecha_solicitud'],
            'puede_solicitar' => $can_submit
        ];
    } else {
        $response['solicitud'] = [
            'existe' => false,
            'puede_solicitar' => true
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'authenticated' => false,
        'error' => $e->getMessage()
    ]);
}
