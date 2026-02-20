<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Database.php';

/**
 * Modelo para manejar las solicitudes de donación
 */
class Solicitud {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Crear una nueva solicitud con sus donadores
     */
    public function crear($datos) {
        try {
            $this->db->beginTransaction();

            // 1. Verificar que el estudiante exista
            if (!isset($datos['id_estudiante'])) {
                throw new Exception("ID de estudiante requerido");
            }

            // 2. Verificar que el estudiante no tenga una solicitud activa
            $sql_check = "SELECT COUNT(*) FROM solicitudes 
                         WHERE id_estudiante = :id_estudiante 
                         AND estatus IN ('Reservado', 'Aprobado', 'En_espera')";
            $stmt = $this->db->prepare($sql_check);
            $stmt->execute([':id_estudiante' => $datos['id_estudiante']]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Ya tienes una solicitud activa. Espera a que sea procesada antes de enviar otra.");
            }

            // 3. Verificar que el artículo esté disponible
            if (!$this->articuloDisponible($datos['id_paquete'])) {
                throw new Exception("El artículo ya no está disponible");
            }

            // 4. Insertar la solicitud
            $fecha_expiracion = date('Y-m-d H:i:s', strtotime('+5 days'));
            
            $sql = "INSERT INTO solicitudes 
                    (id_estudiante, id_paquete, email_contacto, carrera, tipo_donacion, fecha_expiracion) 
                    VALUES (:id_estudiante, :id_paquete, :email, :carrera, :tipo, :expiracion)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':id_estudiante' => $datos['id_estudiante'],
                ':id_paquete' => $datos['id_paquete'],
                ':email' => $datos['email_contacto'],
                ':carrera' => $datos['carrera'],
                ':tipo' => $datos['tipo_donacion'],
                ':expiracion' => $fecha_expiracion
            ]);

            $id_solicitud = $this->db->lastInsertId();

            // 5. Insertar los donadores
            $this->insertarDonadores($id_solicitud, $datos['donadores']);

            // 6. Registrar en historial
            $this->registrarHistorial($datos['id_paquete'], 'Reservado', $id_solicitud);

            $this->db->commit();

            return [
                'success' => true,
                'id_solicitud' => $id_solicitud,
                'fecha_expiracion' => $fecha_expiracion
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error al crear solicitud: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verificar si un artículo está disponible
     */
    private function articuloDisponible($id_paquete) {
        $sql = "SELECT COUNT(*) FROM articulos_disponibles WHERE id_paquete = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id_paquete]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Insertar múltiples donadores
     */
    private function insertarDonadores($id_solicitud, $donadores) {
        $sql = "INSERT INTO donadores_detalle 
                (id_solicitud, nombre_completo, numero_control, correo_institucional, es_representante) 
                VALUES (:id_sol, :nombre, :control, :correo, :rep)";
        
        $stmt = $this->db->prepare($sql);

        foreach ($donadores as $index => $donador) {
            $stmt->execute([
                ':id_sol' => $id_solicitud,
                ':nombre' => $donador['nombre'],
                ':control' => $donador['numero_control'],
                ':correo' => $donador['correo'],
                ':rep' => $index === 0 ? 1 : 0 // El primero es el representante
            ]);
        }
    }

    /**
     * Registrar en historial
     */
    private function registrarHistorial($id_paquete, $accion, $id_solicitud = null, $id_usuario = null) {
        $sql = "INSERT INTO historial_articulos 
                (id_paquete, accion, id_solicitud, realizado_por) 
                VALUES (:paq, :acc, :sol, :usr)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':paq' => $id_paquete,
            ':acc' => $accion,
            ':sol' => $id_solicitud,
            ':usr' => $id_usuario
        ]);
    }

    /**
     * Obtener solicitudes pendientes de un artículo
     */
    public function obtenerPorArticulo($id_paquete) {
        $sql = "SELECT s.*, 
                GROUP_CONCAT(CONCAT(d.nombre_completo, ' (', d.numero_control, ')') SEPARATOR ', ') as donadores
                FROM solicitudes s
                LEFT JOIN donadores_detalle d ON s.id_solicitud = d.id_solicitud
                WHERE s.id_paquete = :id
                GROUP BY s.id_solicitud
                ORDER BY s.fecha_solicitud DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id_paquete]);
        return $stmt->fetchAll();
    }

    /**
     * Cambiar estatus de una solicitud
     */
    public function cambiarEstatus($id_solicitud, $nuevo_estatus, $id_usuario = null, $notas = null) {
        try {
            $this->db->beginTransaction();

            $sql = "UPDATE solicitudes 
                    SET estatus = :estatus";
            
            $params = [
                ':estatus' => $nuevo_estatus,
                ':id' => $id_solicitud
            ];

            // Campos adicionales según el estatus
            if ($nuevo_estatus === 'Aprobado') {
                $sql .= ", fecha_aprobacion = NOW(), aprobado_por = :usuario";
                $params[':usuario'] = $id_usuario;
            } elseif ($nuevo_estatus === 'Entregado') {
                $sql .= ", fecha_entrega = NOW(), recibido_por = :usuario";
                $params[':usuario'] = $id_usuario;
            }

            if ($notas) {
                $sql .= ", notas_admin = :notas";
                $params[':notas'] = $notas;
            }

            $sql .= " WHERE id_solicitud = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            // Obtener info de la solicitud para historial
            $solicitud = $this->obtenerPorId($id_solicitud);
            
            // Registrar en historial
            $accion = $nuevo_estatus === 'Entregado' ? 'Entregado' : 'Modificado';
            $this->registrarHistorial($solicitud['id_paquete'], $accion, $id_solicitud, $id_usuario);

            // Si se rechaza o expira, liberar el artículo
            if (in_array($nuevo_estatus, ['Rechazado', 'Expirado'])) {
                $this->registrarHistorial($solicitud['id_paquete'], 'Liberado', $id_solicitud, $id_usuario);
            }

            $this->db->commit();
            return ['success' => true];

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error al cambiar estatus: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function obtenerPorId($id_solicitud) {
        $sql = "SELECT 
                    s.*,
                    a.nombre AS nombre_articulo,
                    a.descripcion,
                    a.imagen_url,
                    a.id_categoria
                FROM solicitudes s
                INNER JOIN catalogo_articulos a ON s.id_paquete = a.id_paquete
                WHERE s.id_solicitud = :id";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id_solicitud]);
            $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($solicitud) {
                // Agregar información completa de donadores
                $solicitud['donadores'] = $this->obtenerDonadoresPorSolicitud($id_solicitud);
                $solicitud['num_donadores'] = count($solicitud['donadores']);
                
                // Crear donadores_info para compatibilidad
                $donadores_info = array_map(function($d) {
                    return $d['nombre_completo'] . ' (' . $d['numero_control'] . ')';
                }, $solicitud['donadores']);
                $solicitud['donadores_info'] = implode(', ', $donadores_info);
            }
            
            return $solicitud;
        } catch (PDOException $e) {
            error_log("Error en Solicitud::obtenerPorId: " . $e->getMessage());
            return null;
        }
    }
    /**
     * Obtener todas las solicitudes con filtros opcionales
     */
    public function obtenerTodas($filtros = []) {
    $sql = "SELECT 
                s.id_solicitud,
                s.id_paquete,
                s.carrera,
                s.estatus,
                s.fecha_solicitud,
                s.fecha_expiracion,
                a.nombre AS nombre_articulo,
                a.imagen_url,
                a.descripcion,
                c.id_departamento,
                c.tipo_acceso,
                d.nombre_departamento,
                dept_carrera.id_departamento as departamento_estudiante,
                dept_carrera.nombre_departamento as nombre_carrera_estudiante
            FROM solicitudes s
            INNER JOIN catalogo_articulos a ON s.id_paquete = a.id_paquete
            INNER JOIN categorias c ON a.id_categoria = c.id_categoria
            LEFT JOIN departamentos d ON c.id_departamento = d.id_departamento
            LEFT JOIN departamentos dept_carrera ON s.carrera = dept_carrera.codigo_departamento
            WHERE 1=1";
    
    $params = [];
    
    // Filtro por estatus
    if (!empty($filtros['estatus'])) {
        $sql .= " AND s.estatus = :estatus";
        $params[':estatus'] = $filtros['estatus'];
    }
    
    // Filtro por carrera específica
    if (!empty($filtros['carrera'])) {
        $sql .= " AND s.carrera = :carrera";
        $params[':carrera'] = $filtros['carrera'];
    }
    
    // Filtro por departamento del ESTUDIANTE (basado en su carrera)
    // Si id_departamento = 0 (Todos), mostrar TODO
    // Si id_departamento > 0, filtrar por solicitudes de estudiantes de ese departamento
    if (isset($filtros['id_departamento']) && $filtros['id_departamento'] > 0) {
        $sql .= " AND dept_carrera.id_departamento = :id_departamento";
        $params[':id_departamento'] = $filtros['id_departamento'];
    }
    
    $sql .= " ORDER BY s.fecha_solicitud DESC";
    
    // Límite
    if (!empty($filtros['limit'])) {
        $sql .= " LIMIT :limit";
    }
    
    // DEBUG: Log the query
    error_log("SQL Query: " . $sql);
    error_log("Params: " . print_r($params, true));
    error_log("Filtros recibidos: " . print_r($filtros, true));
    
    try {
        $stmt = $this->db->prepare($sql);
        
        // Bind parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        // Bind limit separately as integer
        if (!empty($filtros['limit'])) {
            $stmt->bindValue(':limit', (int)$filtros['limit'], PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Agregar donadores a cada solicitud
        foreach ($solicitudes as &$solicitud) {
            $solicitud['donadores'] = $this->obtenerDonadoresPorSolicitud($solicitud['id_solicitud']);
            $solicitud['num_donadores'] = count($solicitud['donadores']);
            
            // También crear donadores_info para compatibilidad con código existente
            $donadores_info = array_map(function($d) {
                return $d['nombre_completo'] . ' (' . $d['numero_control'] . ')';
            }, $solicitud['donadores']);
            $solicitud['donadores_info'] = implode(', ', $donadores_info);
        }
        
        // DEBUG: Log result count
        error_log("Resultado: " . count($solicitudes) . " solicitudes encontradas");
        
        return $solicitudes;
    } catch (PDOException $e) {
        error_log("Error en Solicitud::obtenerTodas: " . $e->getMessage());
        return [];
    }
}
    
    /**
     * Obtener todos los donadores de una solicitud específica
     */
    public function obtenerDonadoresPorSolicitud($id_solicitud) {
        $sql = "SELECT 
                    id_detalle,
                    nombre_completo,
                    numero_control,
                    correo_institucional,
                    es_representante
                FROM donadores_detalle
                WHERE id_solicitud = :id_solicitud
                ORDER BY es_representante DESC, nombre_completo ASC";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id_solicitud' => $id_solicitud]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en obtenerDonadoresPorSolicitud: " . $e->getMessage());
            return [];
        }
    }
    /**
     * Expirar solicitudes vencidas (llamar desde cron o manualmente)
     */
    public function expirarVencidas() {
        $sql = "UPDATE solicitudes 
                SET estatus = 'Expirado' 
                WHERE estatus = 'Reservado' 
                AND fecha_expiracion < NOW()";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount();
    }
}