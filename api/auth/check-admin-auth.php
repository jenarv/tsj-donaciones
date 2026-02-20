<?php
/**
 * API: Check Admin Authentication Status
 * Endpoint: GET /api/auth/check-admin-auth.php
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
    // Check if admin is authenticated via Google OAuth
    if (isset($_SESSION['admin_auth'])) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'usuario' => $_SESSION['admin_auth']
        ]);
        exit;
    }
    
    // Check if admin is authenticated via traditional login
    if (isset($_SESSION['usuario'])) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'usuario' => $_SESSION['usuario']
        ]);
        exit;
    }
    
    // Not authenticated
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'authenticated' => false,
        'message' => 'No autenticado'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
