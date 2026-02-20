<?php
/**
 * API: Agregar nuevo artículo (con validación de permisos)
 * Endpoint: POST /api/admin/agregar-articulo.php
 */

session_start();
header('Access-Control-Allow-Origin: http://127.0.0.1:5500'); // ← Cambio importante
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Articulo.php';
require_once __DIR__ . '/../../classes/Usuario.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    
    // Validate required fields
    if (empty($_POST['id_paquete']) || empty($_POST['id_categoria']) || empty($_POST['nombre'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan campos requeridos']);
        exit;
    }

    // Handle image upload if present
    $imagen_url = null;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        // Assuming you have an image upload directory
        $upload_dir = __DIR__ . '/../../uploads/articulos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
        $file_name = $_POST['id_paquete'] . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $file_path)) {
            $imagen_url = '/tsj-donaciones/uploads/articulos/' . $file_name;
        }
    }

    // Prepare article data
    $datos = [
        'id_paquete' => trim($_POST['id_paquete']),
        'id_categoria' => intval($_POST['id_categoria']),
        'nombre' => trim($_POST['nombre']),
        'descripcion' => trim($_POST['descripcion'] ?? ''),
        'precio_estimado' => floatval($_POST['precio_estimado'] ?? 1000.00),
        'enlace_referencia' => trim($_POST['enlace_referencia'] ?? ''),
        'imagen_url' => $imagen_url
    ];

    // Verify the admin can manage this category
    if (!$usuario->puedeGestionarCategoria($datos['id_categoria'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'No tiene permisos para agregar artículos en esta categoría'
        ]);
        exit;
    }

    // Create article
    $articulo = new Articulo();
    $resultado = $articulo->crear($datos, $user_data['id']);

    if ($resultado['success']) {
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Artículo agregado exitosamente',
            'data' => $datos
        ]);
    } else {
        http_response_code(400);
        echo json_encode($resultado);
    }

} catch (Exception $e) {
    error_log("Error en /api/admin/agregar-articulo.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
