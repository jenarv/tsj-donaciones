<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Database.php';

/**
 * Modelo para manejar usuarios administrativos con control de roles
 */
class Usuario {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Autenticar usuario
     */
    public function login($email, $password) {
        $sql = "SELECT ua.*, d.nombre_departamento, d.codigo_departamento
                FROM usuarios_admin ua
                LEFT JOIN departamentos d ON ua.id_departamento = d.id_departamento
                WHERE ua.email = :email AND ua.activo = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);
        
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($password, $usuario['password_hash'])) {
            // Iniciar sesión con información completa
            $_SESSION['user_id'] = $usuario['id_usuario'];
            $_SESSION['user_name'] = $usuario['nombre_completo'];
            $_SESSION['user_role'] = $usuario['rol'];
            $_SESSION['user_role_type'] = $usuario['rol_tipo'];
            $_SESSION['user_email'] = $usuario['email'];
            $_SESSION['user_department_id'] = $usuario['id_departamento'];
            $_SESSION['user_department_name'] = $usuario['nombre_departamento'];
            
            // Update last access
            $update_sql = "UPDATE usuarios_admin SET ultimo_acceso = NOW() WHERE id_usuario = :id";
            $update_stmt = $this->db->prepare($update_sql);
            $update_stmt->execute([':id' => $usuario['id_usuario']]);
            
            return [
                'success' => true,
                'usuario' => [
                    'id' => $usuario['id_usuario'],
                    'nombre' => $usuario['nombre_completo'],
                    'rol' => $usuario['rol'],
                    'rol_tipo' => $usuario['rol_tipo'],
                    'id_departamento' => $usuario['id_departamento'],
                    'nombre_departamento' => $usuario['nombre_departamento']
                ]
            ];
        }

        return ['success' => false, 'error' => 'Credenciales incorrectas'];
    }

    /**
     * Cerrar sesión
     */
    public function logout() {
        session_destroy();
        return ['success' => true];
    }

    /**
     * Verificar si hay sesión activa
     */
    public function verificarSesion() {
        return isset($_SESSION['user_id']);
    }

        /**
     * Obtener usuario actual
     */
    public function obtenerUsuarioActual() {
        if (!$this->verificarSesion()) {
            return null;
        }

        // Consultar la base de datos para obtener datos actualizados
        $sql = "SELECT 
                    ua.id_usuario,
                    ua.nombre_completo as nombre,
                    ua.email,
                    ua.rol,
                    ua.rol_tipo,
                    ua.id_departamento,
                    d.nombre_departamento,
                    d.codigo_departamento
                FROM usuarios_admin ua
                LEFT JOIN departamentos d ON ua.id_departamento = d.id_departamento
                WHERE ua.id_usuario = :id AND ua.activo = 1";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $_SESSION['user_id']]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user_data) {
                return null;
            }
            
            return [
                'id' => $user_data['id_usuario'],
                'nombre' => $user_data['nombre'],
                'rol' => $user_data['rol'],
                'rol_tipo' => $user_data['rol_tipo'],
                'email' => $user_data['email'],
                'id_departamento' => $user_data['id_departamento'] ?? 0,
                'departamento' => $user_data['nombre_departamento'] ?? 'Sin asignar'
            ];
            
        } catch (PDOException $e) {
            error_log("Error en Usuario::obtenerUsuarioActual: " . $e->getMessage());
            
            // Fallback a datos de sesión si falla la consulta
            return [
                'id' => $_SESSION['user_id'],
                'nombre' => $_SESSION['user_name'],
                'rol' => $_SESSION['user_role'],
                'rol_tipo' => $_SESSION['user_role_type'] ?? 'Admin_Departamento',
                'email' => $_SESSION['user_email'],
                'id_departamento' => $_SESSION['user_department_id'] ?? 0,
                'departamento' => $_SESSION['user_department_name'] ?? 'Sin asignar'
            ];
        }
    }

    /**
     * Verificar si el usuario es Super Admin
     */
    public function esSuperAdmin() {
        if (!$this->verificarSesion()) {
            return false;
        }
        return ($_SESSION['user_role_type'] ?? '') === 'Super_Admin';
    }

    /**
     * Verificar si el usuario es Admin de Departamento
     */
    public function esAdminDepartamento() {
        if (!$this->verificarSesion()) {
            return false;
        }
        return ($_SESSION['user_role_type'] ?? '') === 'Admin_Departamento';
    }

    /**
     * Obtener el ID del departamento del usuario
     */
    public function obtenerIdDepartamento() {
        if (!$this->verificarSesion()) {
            return null;
        }
        return $_SESSION['user_department_id'] ?? null;
    }

    /**
     * Crear nuevo usuario (solo Super Admin)
     */
    public function crear($datos) {
        try {
            // Verify caller is Super Admin
            if (!$this->esSuperAdmin()) {
                return ['success' => false, 'error' => 'Solo Super Admins pueden crear usuarios'];
            }

            // Verificar que el email no exista
            $check_sql = "SELECT COUNT(*) FROM usuarios_admin WHERE email = :email";
            $check_stmt = $this->db->prepare($check_sql);
            $check_stmt->execute([':email' => $datos['email']]);
            
            if ($check_stmt->fetchColumn() > 0) {
                return ['success' => false, 'error' => 'El email ya está registrado'];
            }

            // Hash de la contraseña
            $password_hash = password_hash($datos['password'], PASSWORD_DEFAULT);

            $sql = "INSERT INTO usuarios_admin 
                    (nombre_completo, email, password_hash, rol, rol_tipo, id_departamento) 
                    VALUES (:nombre, :email, :pass, :rol, :rol_tipo, :id_depto)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':nombre' => $datos['nombre_completo'],
                ':email' => $datos['email'],
                ':pass' => $password_hash,
                ':rol' => 'Admin',
                ':rol_tipo' => $datos['rol_tipo'] ?? 'Admin_Departamento',
                ':id_depto' => $datos['id_departamento'] ?? null
            ]);

            return ['success' => true, 'id' => $this->db->lastInsertId()];

        } catch (Exception $e) {
            error_log("Error al crear usuario: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Actualizar usuario (solo Super Admin)
     */
    public function actualizar($id_usuario, $datos) {
        try {
            // Verify caller is Super Admin
            if (!$this->esSuperAdmin()) {
                return ['success' => false, 'error' => 'Solo Super Admins pueden modificar usuarios'];
            }

            $sql = "UPDATE usuarios_admin 
                    SET nombre_completo = :nombre,
                        email = :email,
                        rol_tipo = :rol_tipo,
                        id_departamento = :id_depto";
            
            $params = [
                ':id' => $id_usuario,
                ':nombre' => $datos['nombre_completo'],
                ':email' => $datos['email'],
                ':rol_tipo' => $datos['rol_tipo'],
                ':id_depto' => $datos['id_departamento'] ?? null
            ];

            // Si se proporciona nueva contraseña
            if (!empty($datos['password'])) {
                $sql .= ", password_hash = :pass";
                $params[':pass'] = password_hash($datos['password'], PASSWORD_DEFAULT);
            }

            $sql .= " WHERE id_usuario = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return ['success' => true];

        } catch (Exception $e) {
            error_log("Error al actualizar usuario: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Desactivar usuario (solo Super Admin)
     */
    public function desactivar($id_usuario) {
        if (!$this->esSuperAdmin()) {
            return ['success' => false, 'error' => 'Solo Super Admins pueden desactivar usuarios'];
        }

        $sql = "UPDATE usuarios_admin SET activo = 0 WHERE id_usuario = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id_usuario]);
        return ['success' => true];
    }

    /**
     * Obtener todos los usuarios (solo Super Admin)
     */
    public function obtenerTodos() {
        if (!$this->esSuperAdmin()) {
            return [];
        }

        $sql = "SELECT ua.id_usuario, ua.nombre_completo, ua.email, ua.rol, ua.rol_tipo, 
                       ua.activo, ua.fecha_creacion, ua.id_departamento,
                       d.nombre_departamento, d.codigo_departamento
                FROM usuarios_admin ua
                LEFT JOIN departamentos d ON ua.id_departamento = d.id_departamento
                ORDER BY ua.fecha_creacion DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obtener departamentos disponibles
     */
    public function obtenerDepartamentos() {
        $sql = "SELECT * FROM departamentos WHERE activo = 1 ORDER BY nombre_departamento";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Verificar si el usuario puede gestionar una categoría
     */
    public function puedeGestionarCategoria($id_categoria) {
        if (!$this->verificarSesion()) {
            return false;
        }

        $id_usuario = $_SESSION['user_id'];
        
        $sql = "SELECT COUNT(*) as puede
                FROM categorias c
                INNER JOIN usuarios_admin ua ON ua.id_usuario = :id_usuario
                WHERE c.id_categoria = :id_categoria
                AND c.activo = 1
                AND (
                    ua.rol_tipo = 'Super_Admin'
                    OR c.tipo_acceso = 'Universal'
                    OR c.id_departamento = ua.id_departamento
                )";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_usuario' => $id_usuario,
            ':id_categoria' => $id_categoria
        ]);
        
        return $stmt->fetch()['puede'] > 0;
    }
}
