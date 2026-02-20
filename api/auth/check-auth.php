<?php
/**
 * API: Verificar autenticación de usuario del formulario
 * Endpoint: GET /api/auth/check-auth.php
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

try {
    // Verificar si hay sesión activa
    if (isset($_SESSION['user_authenticated']) && $_SESSION['user_authenticated'] === true) {
        
        // Verificar que la sesión no haya expirado (2 horas)
        if (isset($_SESSION['auth_time']) && (time() - $_SESSION['auth_time']) > 7200) {
            session_destroy();
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'authenticated' => false,
                'error' => 'Sesión expirada'
            ]);
            exit;
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'user' => [
                'email' => $_SESSION['user_email'],
                'name' => $_SESSION['user_name'],
                'picture' => $_SESSION['user_picture'] ?? null
            ]
        ]);
    } else {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'authenticated' => false
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}