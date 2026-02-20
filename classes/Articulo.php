<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Database.php';

/**
 * Modelo para manejar los artículos del catálogo con control de acceso basado en roles
 */
class Articulo {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Obtener artículos disponibles para estudiantes
     * Filtra por departamento del estudiante + categorías universales
     */
    public function obtenerDisponiblesParaEstudiante($id_estudiante, $categoria_codigo = null) {
        // Use the stored procedure that already implements the logic
        $sql = "CALL obtener_articulos_para_estudiante(:id_estudiante)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_estudiante' => $id_estudiante]);
        $articulos = $stmt->fetchAll();
        
        // Filter by category if provided
        if ($categoria_codigo) {
            $articulos = array_filter($articulos, function($art) use ($categoria_codigo) {
                return $art['codigo_categoria'] === $categoria_codigo;
            });
        }
        
        return array_values($articulos);
    }

    /**
     * Obtener artículos para admin basado en su rol y departamento
     * Super Admin: ve todo
     * Department Admin: ve solo su departamento + universales
     */
    public function obtenerParaAdmin($id_usuario, $categoria_codigo = null) {
        // Use the stored procedure that implements proper access control
        $sql = "CALL obtener_articulos_para_admin(:id_usuario)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_usuario' => $id_usuario]);
        $articulos = $stmt->fetchAll();
        
        // Filter by category if provided
        if ($categoria_codigo) {
            $articulos = array_filter($articulos, function($art) use ($categoria_codigo) {
                return $art['codigo_categoria'] === $categoria_codigo;
            });
        }
        
        return array_values($articulos);
    }

    /**
     * Verificar si un admin puede gestionar un artículo
     */
    public function puedeAdminGestionar($id_usuario, $id_paquete) {
        $sql = "SELECT puede_admin_gestionar_articulo(:id_usuario, :id_paquete) as puede";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_usuario' => $id_usuario,
            ':id_paquete' => $id_paquete
        ]);
        $result = $stmt->fetch();
        return (bool) $result['puede'];
    }

    /**
     * Verificar si un estudiante puede ver un artículo
     */
    public function puedeEstudianteVer($id_estudiante, $id_paquete) {
        $sql = "SELECT puede_estudiante_ver_articulo(:id_estudiante, :id_paquete) as puede";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_estudiante' => $id_estudiante,
            ':id_paquete' => $id_paquete
        ]);
        $result = $stmt->fetch();
        return (bool) $result['puede'];
    }

    /**
     * Obtener categorías disponibles para un admin
     */
    public function obtenerCategoriasParaAdmin($id_usuario) {
        $sql = "SELECT DISTINCT c.*
                FROM categorias c
                INNER JOIN usuarios_admin ua ON ua.id_usuario = :id_usuario
                WHERE c.activo = 1
                AND (
                    ua.rol_tipo = 'Super_Admin'
                    OR c.tipo_acceso = 'Universal'
                    OR c.id_departamento = ua.id_departamento
                )
                ORDER BY c.nombre_categoria";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_usuario' => $id_usuario]);
        return $stmt->fetchAll();
    }

    /**
     * Obtener categorías disponibles para un estudiante
     */
    public function obtenerCategoriasParaEstudiante($id_estudiante) {
        $sql = "SELECT DISTINCT c.*
                FROM categorias c
                INNER JOIN estudiantes e ON e.id_estudiante = :id_estudiante
                WHERE c.activo = 1
                AND (
                    c.tipo_acceso = 'Universal'
                    OR c.id_departamento = e.id_departamento
                )
                ORDER BY c.nombre_categoria";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_estudiante' => $id_estudiante]);
        return $stmt->fetchAll();
    }

    /**
     * Obtener un artículo por ID (con validación de permisos)
     */
    public function obtenerPorId($id_paquete, $id_usuario = null, $es_estudiante = false) {
        $sql = "SELECT ca.*, cat.nombre_categoria, cat.codigo_categoria, cat.tipo_acceso, cat.id_departamento
                FROM catalogo_articulos ca
                INNER JOIN categorias cat ON ca.id_categoria = cat.id_categoria
                WHERE ca.id_paquete = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id_paquete]);
        $articulo = $stmt->fetch();
        
        // Validate permissions if user is provided
        if ($articulo && $id_usuario) {
            if ($es_estudiante) {
                if (!$this->puedeEstudianteVer($id_usuario, $id_paquete)) {
                    return null; // No permission
                }
            } else {
                if (!$this->puedeAdminGestionar($id_usuario, $id_paquete)) {
                    return null; // No permission
                }
            }
        }
        
        return $articulo;
    }

    /**
     * Crear un nuevo artículo (solo para admins con permisos)
     */
    public function crear($datos, $id_usuario) {
        try {
            // Verify the admin can manage this category
            $sql_check = "SELECT COUNT(*) as puede
                          FROM categorias c
                          INNER JOIN usuarios_admin ua ON ua.id_usuario = :id_usuario
                          WHERE c.id_categoria = :id_categoria
                          AND c.activo = 1
                          AND (
                              ua.rol_tipo = 'Super_Admin'
                              OR c.tipo_acceso = 'Universal'
                              OR c.id_departamento = ua.id_departamento
                          )";
            
            $stmt_check = $this->db->prepare($sql_check);
            $stmt_check->execute([
                ':id_usuario' => $id_usuario,
                ':id_categoria' => $datos['id_categoria']
            ]);
            
            if ($stmt_check->fetch()['puede'] == 0) {
                return ['success' => false, 'error' => 'No tiene permisos para agregar artículos en esta categoría'];
            }

            // Get categoria enum value for backward compatibility
            $sql_cat = "SELECT codigo_categoria FROM categorias WHERE id_categoria = :id";
            $stmt_cat = $this->db->prepare($sql_cat);
            $stmt_cat->execute([':id' => $datos['id_categoria']]);
            $cat_info = $stmt_cat->fetch();
            
            // Determine categoria enum (for backward compatibility with old column)
            $categoria_enum = 'Otro'; // default
            if (strpos($cat_info['codigo_categoria'], 'DEP') === 0) $categoria_enum = 'Deportes';
            elseif (strpos($cat_info['codigo_categoria'], 'MED') === 0) $categoria_enum = 'Medica';
            elseif (strpos($cat_info['codigo_categoria'], 'LAB') === 0) $categoria_enum = 'Laboratorio';

            $sql = "INSERT INTO catalogo_articulos 
                    (id_paquete, id_categoria, categoria, nombre, descripcion, precio_estimado, enlace_referencia, imagen_url, agregado_por) 
                    VALUES (:id, :id_cat, :cat, :nom, :desc, :precio, :enlace, :img, :usr)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':id' => $datos['id_paquete'],
                ':id_cat' => $datos['id_categoria'],
                ':cat' => $categoria_enum,
                ':nom' => $datos['nombre'],
                ':desc' => $datos['descripcion'] ?? null,
                ':precio' => $datos['precio_estimado'] ?? 1000.00,
                ':enlace' => $datos['enlace_referencia'] ?? null,
                ':img' => $datos['imagen_url'] ?? null,
                ':usr' => $id_usuario
            ]);

            // Registrar en historial
            $this->registrarHistorial($datos['id_paquete'], 'Agregado', null, $id_usuario);

            return ['success' => true];

        } catch (Exception $e) {
            error_log("Error al crear artículo: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Actualizar un artículo (con validación de permisos)
     */
    public function actualizar($id_paquete, $datos, $id_usuario) {
        try {
            // Verify permissions first
            if (!$this->puedeAdminGestionar($id_usuario, $id_paquete)) {
                return ['success' => false, 'error' => 'No tiene permisos para modificar este artículo'];
            }

            // Get categoria enum value for backward compatibility
            $sql_cat = "SELECT codigo_categoria FROM categorias WHERE id_categoria = :id";
            $stmt_cat = $this->db->prepare($sql_cat);
            $stmt_cat->execute([':id' => $datos['id_categoria']]);
            $cat_info = $stmt_cat->fetch();
            
            $categoria_enum = 'Otro';
            if (strpos($cat_info['codigo_categoria'], 'DEP') === 0) $categoria_enum = 'Deportes';
            elseif (strpos($cat_info['codigo_categoria'], 'MED') === 0) $categoria_enum = 'Medica';
            elseif (strpos($cat_info['codigo_categoria'], 'LAB') === 0) $categoria_enum = 'Laboratorio';

            $sql = "UPDATE catalogo_articulos 
                    SET id_categoria = :id_cat,
                        categoria = :cat,
                        nombre = :nom,
                        descripcion = :desc,
                        precio_estimado = :precio,
                        enlace_referencia = :enlace,
                        imagen_url = :img
                    WHERE id_paquete = :id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':id' => $id_paquete,
                ':id_cat' => $datos['id_categoria'],
                ':cat' => $categoria_enum,
                ':nom' => $datos['nombre'],
                ':desc' => $datos['descripcion'],
                ':precio' => $datos['precio_estimado'],
                ':enlace' => $datos['enlace_referencia'],
                ':img' => $datos['imagen_url']
            ]);

            $this->registrarHistorial($id_paquete, 'Modificado', null, $id_usuario);

            return ['success' => true];

        } catch (Exception $e) {
            error_log("Error al actualizar artículo: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Eliminar un artículo (con validación de permisos)
     */
    public function eliminar($id_paquete, $id_usuario) {
        try {
            // Verify permissions
            if (!$this->puedeAdminGestionar($id_usuario, $id_paquete)) {
                return ['success' => false, 'error' => 'No tiene permisos para eliminar este artículo'];
            }

            // Check if there are active requests
            $sql_check = "SELECT COUNT(*) as count FROM solicitudes 
                          WHERE id_paquete = :id 
                          AND estatus IN ('Reservado', 'Aprobado', 'En_espera')";
            $stmt_check = $this->db->prepare($sql_check);
            $stmt_check->execute([':id' => $id_paquete]);
            
            if ($stmt_check->fetch()['count'] > 0) {
                return ['success' => false, 'error' => 'No se puede eliminar un artículo con solicitudes activas'];
            }

            $this->registrarHistorial($id_paquete, 'Eliminado', null, $id_usuario);

            $sql = "DELETE FROM catalogo_articulos WHERE id_paquete = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id_paquete]);

            return ['success' => true];

        } catch (Exception $e) {
            error_log("Error al eliminar artículo: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verificar disponibilidad de un artículo
     */
    public function estaDisponible($id_paquete) {
        $sql = "SELECT COUNT(*) FROM articulos_disponibles WHERE id_paquete = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id_paquete]);
        return $stmt->fetchColumn() > 0;
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
     * Obtener estadísticas (usa el stored procedure que ya filtra por rol)
     */
    public function obtenerEstadisticas($id_usuario) {
        $sql = "CALL obtener_estadisticas(:id_usuario)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_usuario' => $id_usuario]);
        return $stmt->fetch();
    }
}
