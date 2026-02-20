<?php
/**
 * API: Actualizar artículo (con validación de permisos)
 * Endpoint: POST /api/admin/actualizar-articulo.php
 */
session_start();
header('Access-Control-Allow-Origin: http://127.0.0.1:5500'); 
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

    $id_paquete = trim($_POST['id_paquete']);

    // Verify permissions
    $articulo = new Articulo();
    if (!$articulo->puedeAdminGestionar($user_data['id'], $id_paquete)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'No tiene permisos para modificar este artículo'
        ]);
        exit;
    }

    // Operaciones de imagen
    $imagen_url = $_POST['imagen_url_actual'] ?? null;
    $delete_image = isset($_POST['delete_image']) && $_POST['delete_image'] === '1';

    // If user wants to delete the current image
    if ($delete_image && $imagen_url) {
        // Extraer solo el nombre del archivo de la URL completa
       
        
        // Remover el prefijo /tsj-donaciones si existe
        $relative_path = str_replace('/tsj-donaciones/', '', $imagen_url);
        // Remover el / inicial si existe
        $relative_path = ltrim($relative_path, '/');
        
        $file_path = __DIR__ . '/../../' . $relative_path;
        
        error_log("Intentando eliminar: " . $file_path);
        error_log("¿Existe el archivo?: " . (file_exists($file_path) ? 'SÍ' : 'NO'));
        
        if (file_exists($file_path)) {
            if (unlink($file_path)) {
                error_log("Archivo eliminado exitosamente");
            } else {
                error_log("Error al eliminar el archivo");
            }
        } else {
            error_log("Archivo no encontrado en: " . $file_path);
        }
        
        $imagen_url = null;
    }

    // si imagen existe, la reemplaza
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        // elimina la imagen anterior
        if ($imagen_url && !$delete_image) {
            $relative_path = str_replace('/tsj-donaciones/', '', $imagen_url);
            $relative_path = ltrim($relative_path, '/');
            $old_file_path = __DIR__ . '/../../' . $relative_path;
            
            error_log("Eliminando imagen antigua: " . $old_file_path);
            
            if (file_exists($old_file_path)) {
                unlink($old_file_path);
                error_log("Imagen antigua eliminada");
            }
        }
        
        $upload_dir = __DIR__ . '/../../uploads/articulos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
        $file_name = $id_paquete . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $file_path)) {
            $imagen_url = '/tsj-donaciones/uploads/articulos/' . $file_name;
            error_log("Nueva imagen guardada: " . $imagen_url);
        }
    }

    
    $datos = [
        'id_categoria' => intval($_POST['id_categoria']),
        'nombre' => trim($_POST['nombre']),
        'descripcion' => trim($_POST['descripcion'] ?? ''),
        'precio_estimado' => floatval($_POST['precio_estimado'] ?? 1000.00),
        'enlace_referencia' => trim($_POST['enlace_referencia'] ?? ''),
        'imagen_url' => $imagen_url
    ];

    
    if (!$usuario->puedeGestionarCategoria($datos['id_categoria'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'No tiene permisos para mover el artículo a esta categoría'
        ]);
        exit;
    }

    $resultado = $articulo->actualizar($id_paquete, $datos, $user_data['id']);

    if ($resultado['success']) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Artículo actualizado exitosamente'
        ]);
    } else {
        http_response_code(400);
        echo json_encode($resultado);
    }

} catch (Exception $e) {
    error_log("Error en /api/admin/actualizar-articulo.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
