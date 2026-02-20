<?php
/**
 * API: Login de administradores
 * Endpoint: POST /api/auth/login.php
 */

session_start();

header('Access-Control-Allow-Origin: http://127.0.0.1:5500'); //
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Usuario.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    // Check if this is a Google Auth request
    if (isset($input['google_auth']) && $input['google_auth'] === true) {
        require_once __DIR__ . '/../../classes/GoogleAuth.php';
        require_once __DIR__ . '/../../config/google-config.php';
        
        // Return the Google Auth URL
        $auth_url = GoogleAuth::getAuthUrl(GOOGLE_REDIRECT_URI_ADMIN);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'auth_url' => $auth_url
        ]);
        exit;
    }

    // Traditional login
    if (empty($input['email']) || empty($input['password'])) {
        throw new Exception("Email y contraseña son requeridos");
    }

    $usuario = new Usuario();
    $resultado = $usuario->login($input['email'], $input['password']);

    if (!$resultado['success']) {
        http_response_code(401);
        echo json_encode($resultado);
        exit;
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login exitoso',
        'usuario' => $resultado['usuario']
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
