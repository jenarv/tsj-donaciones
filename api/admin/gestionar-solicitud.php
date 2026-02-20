<?php
/**
 * API: Gestionar solicitudes (aprobar, rechazar, marcar como entregado)
 * Endpoint: POST /api/admin/gestionar-solicitud.php
 */

session_start();
header('Access-Control-Allow-Origin: http://127.0.0.1:5500'); // ← Cambio importante
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Solicitud.php';
require_once __DIR__ . '/../../classes/Usuario.php';
require_once __DIR__ . '/../../classes/Email.php';
require_once __DIR__ . '/../../classes/Constancia.php';

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
    $input = json_decode(file_get_contents('php://input'), true);

    // Validar datos
    if (empty($input['id_solicitud']) || empty($input['accion'])) {
        throw new Exception("id_solicitud y accion son requeridos");
    }

    $accion = $input['accion']; // 'aprobar', 'rechazar', 'entregar'
    $id_solicitud = $input['id_solicitud'];
    $notas = $input['notas'] ?? null;

    $solicitud_model = new Solicitud();
    $solicitud_data = $solicitud_model->obtenerPorId($id_solicitud);

    if (!$solicitud_data) {
        throw new Exception("Solicitud no encontrada");
    }

    // Verificar que el admin tenga permisos sobre esta solicitud
    if ($user_data['id_departamento'] != 0) {  // 0 = Super Admin (ve todo)
        // Obtener departamento de la categoría del artículo
        $db = Database::getInstance()->getConnection();
        $sql = "SELECT c.id_departamento, c.tipo_acceso
            FROM catalogo_articulos a
            INNER JOIN categorias c ON a.id_categoria = c.id_categoria
            WHERE a.id_paquete = :id_paquete";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':id_paquete' => $solicitud_data['id_paquete']]);
        $categoria_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$categoria_info) {
            throw new Exception("Artículo no encontrado");
        }
        
        // Verificar que tenga acceso a esta categoría
        if ($categoria_info['tipo_acceso'] !== 'Universal' && 
            $categoria_info['id_departamento'] != $user_data['id_departamento']) {
            throw new Exception("No tienes permisos para gestionar esta solicitud");
        }
    }

    // Procesar según la acción
    $nuevo_estatus = null;
    $enviar_email = false;

    switch ($accion) {
        case 'aprobar':
            // Todos los admins pueden aprobar solicitudes de su área
            $nuevo_estatus = 'Aprobado';
            $enviar_email = 'aprobacion';
            break;

        case 'rechazar':
            // Todos los admins pueden rechazar solicitudes de su área
            $nuevo_estatus = 'Rechazado';
            $enviar_email = 'rechazo';
            break;

        case 'entregar':
            // Todos los admins pueden marcar como entregado
            $nuevo_estatus = 'Entregado';
            break;

        default:
            throw new Exception("Acción no válida");
    }

    // Cambiar estatus
    $resultado = $solicitud_model->cambiarEstatus(
        $id_solicitud,
        $nuevo_estatus,
        $user_data['id'],
        $notas
    );

    if (!$resultado['success']) {
        throw new Exception($resultado['error']);
    }

    // Enviar respuesta inmediatamente al usuario
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => "Solicitud actualizada a: {$nuevo_estatus}",
        'nuevo_estatus' => $nuevo_estatus
    ]);
    
    // Enviar email de forma no bloqueante (después de la respuesta)
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request(); // Cierra la conexión con el cliente
    }
    
    // Si es entrega, generar y enviar constancias
    if ($accion === 'entregar') {
        try {
            $constancia = new Constancia();
            
            // Generar constancias para todos los donadores
            $resultado_constancias = $constancia->generarConstanciasParaSolicitud($id_solicitud);
            
            if ($resultado_constancias['success'] && !empty($resultado_constancias['constancias'])) {
                $email = new Email();
                
                // Enviar constancia a cada donador
                foreach ($resultado_constancias['constancias'] as $const) {
                    try {
                        $email->enviarConstancia(
                            $const['nombre'],
                            $const['email'],
                            $const['pdf_path'],
                            basename($const['pdf_path'])
                        );
                        
                        // Marcar como enviada en BD
                        // Obtener ID de constancia para marcarla como enviada
                        $constancias_bd = $constancia->obtenerConstanciasPorSolicitud($id_solicitud);
                        foreach ($constancias_bd as $c_bd) {
                            if ($c_bd['correo_institucional'] === $const['email'] && !$c_bd['enviado_por_correo']) {
                                $constancia->marcarComoEnviada($c_bd['id_constancia']);
                                break;
                            }
                        }
                        
                        error_log("Constancia enviada a: {$const['email']}");
                    } catch (Exception $e) {
                        error_log("Error al enviar constancia a {$const['email']}: " . $e->getMessage());
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error al generar constancias: " . $e->getMessage());
        }
    }
    
    // Ahora sí intentar enviar el email (sin bloquear al usuario)
    if ($enviar_email) {
        try {
            $email = new Email();
            
            // Extraer correos de los donadores desde el array
            $correos = array_map(function($donador) {
                return $donador['correo_institucional'];
            }, $solicitud_data['donadores']);

            if ($enviar_email === 'aprobacion') {
                $email->enviarAprobacion($solicitud_data, $correos);
            } elseif ($enviar_email === 'rechazo') {
                $email->enviarRechazo($solicitud_data, $correos, $notas);
            }
        } catch (Exception $e) {
            // Log el error pero no afecta al usuario
            error_log("Error al enviar email: " . $e->getMessage());
        }
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}