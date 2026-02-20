<?php
/**
 * API: Enviar solicitud de donación
 * Endpoint: POST /api/solicitud/enviar.php
 */

// Start output buffering to catch any stray output
ob_start();

// Prevent PHP from displaying errors as HTML
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set headers first
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode([
        'error' => 'Método no permitido',
        'received_method' => $_SERVER['REQUEST_METHOD'] // This will tell us the truth
    ]);
    exit;
}


require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Solicitud.php';
require_once __DIR__ . '/../../classes/Articulo.php';
require_once __DIR__ . '/../../classes/Email.php';

// Start session to get student info
session_start();

try {
    // Check if student is authenticated
    if (!isset($_SESSION['student_auth'])) {
        throw new Exception('Debes iniciar sesión con tu cuenta de Google para enviar una solicitud');
    }
    
    $student = $_SESSION['student_auth'];
    
    // Obtener datos del formulario
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido: ' . json_last_error_msg());
    }

    // Validar campos requeridos
    $campos_requeridos = ['email', 'carrera', 'id_paquete', 'nombreAlumno', 'numeroControl', 'correoInstitucional', 'tipo_donacion'];
    
    foreach ($campos_requeridos as $campo) {
        if (empty($input[$campo])) {
            throw new Exception("El campo '{$campo}' es requerido");
        }
    }

    // Procesar múltiples donadores (separados por comas)
    $nombres = array_map('trim', explode(',', $input['nombreAlumno']));
    $controles = array_map('trim', explode(',', $input['numeroControl']));
    $correos = array_map('trim', explode(',', $input['correoInstitucional']));

    // Validar que tengan la misma cantidad
    if (count($nombres) !== count($controles) || count($nombres) !== count($correos)) {
        throw new Exception("La cantidad de nombres, números de control y correos no coincide");
    }

    // Preparar array de donadores
    $donadores = [];
    for ($i = 0; $i < count($nombres); $i++) {
        $donadores[] = [
            'nombre' => strtoupper($nombres[$i]),
            'numero_control' => $controles[$i],
            'correo' => $correos[$i]
        ];
    }

    // Verificar que el artículo esté disponible
    $articulo = new Articulo();
    if (!$articulo->estaDisponible($input['id_paquete'])) {
        throw new Exception("El artículo seleccionado ya no está disponible");
    }

    // Obtener info del artículo
    $info_articulo = $articulo->obtenerPorId($input['id_paquete']);

    // Crear la solicitud con id_estudiante
    $solicitud = new Solicitud();
    $datos_solicitud = [
        'id_estudiante' => $student['id_estudiante'],
        'id_paquete' => $input['id_paquete'],
        'email_contacto' => $input['email'],
        'carrera' => $input['carrera'],
        'tipo_donacion' => $input['tipo_donacion'],
        'donadores' => $donadores
    ];

    $resultado = $solicitud->crear($datos_solicitud);

    if (!$resultado['success']) {
        throw new Exception($resultado['error']);
    }

    // Enviar correo de confirmación
    $email = new Email();
    $solicitud_data = [
        'id_paquete' => $input['id_paquete'],
        'nombre_articulo' => $info_articulo['nombre'],
        'carrera' => $input['carrera'],
        'tipo_donacion' => $input['tipo_donacion'],
        'email_contacto' => $input['email'],
        'fecha_expiracion' => $resultado['fecha_expiracion']
    ];

    $email->enviarConfirmacionSolicitud($solicitud_data, $donadores);

    // Clear any buffered output before sending JSON
    ob_end_clean();
    
    // Respuesta exitosa
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Solicitud enviada exitosamente',
        'data' => [
            'id_solicitud' => $resultado['id_solicitud'],
            'fecha_expiracion' => $resultado['fecha_expiracion']
        ]
    ]);

} catch (Exception $e) {
    // Clear any buffered output before sending error
    ob_end_clean();
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}