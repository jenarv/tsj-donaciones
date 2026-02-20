<?php
/**
 * Script de utilidad para crear usuarios administrativos
 * Ejecutar desde línea de comandos: php utils/crear-usuario.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';

echo "\n==============================================\n";
echo "   CREADOR DE USUARIOS ADMINISTRATIVOS\n";
echo "==============================================\n\n";

// Obtener departamentos disponibles
$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT id_departamento, nombre_departamento, codigo_departamento FROM departamentos WHERE activo = 1 ORDER BY nombre_departamento");
$departamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Solicitar datos
echo "Nombre completo: ";
$nombre = trim(fgets(STDIN));

echo "Email: ";
$email = trim(fgets(STDIN));

echo "Contraseña: ";
$password = trim(fgets(STDIN));

echo "\nTipo de administrador:\n";
echo "1. Super Admin (acceso total)\n";
echo "2. Admin Departamental (acceso a un departamento específico)\n";
echo "Selecciona (1 o 2): ";
$tipo_admin = trim(fgets(STDIN));

$rol_tipo = null;
$id_departamento = null;

if ($tipo_admin === '1') {
    $rol_tipo = 'Super_Admin';
    $id_departamento = 0; // 0 significa todos los departamentos
    echo "\n✓ Tipo: Super Admin (acceso a todos los departamentos)\n";
} elseif ($tipo_admin === '2') {
    $rol_tipo = 'Admin_Departamento';
    
    echo "\nDepartamentos disponibles:\n";
    foreach ($departamentos as $index => $dept) {
        echo ($index + 1) . ". {$dept['nombre_departamento']} ({$dept['codigo_departamento']})\n";
    }
    
    echo "\nSelecciona el número del departamento: ";
    $dept_seleccion = trim(fgets(STDIN));
    $dept_index = (int)$dept_seleccion - 1;
    
    if (!isset($departamentos[$dept_index])) {
        die("\n❌ Error: Selección de departamento no válida\n\n");
    }
    
    $id_departamento = $departamentos[$dept_index]['id_departamento'];
    echo "\n✓ Tipo: Admin Departamental - {$departamentos[$dept_index]['nombre_departamento']}\n";
} else {
    die("\n❌ Error: Opción no válida. Debe ser 1 o 2\n\n");
}

// Confirmar
echo "\n--- Confirmar datos ---\n";
echo "Nombre: $nombre\n";
echo "Email: $email\n";
echo "Tipo: " . ($rol_tipo === 'Super_Admin' ? 'Super Admin' : 'Admin Departamental') . "\n";
if ($rol_tipo === 'Admin_Departamento') {
    echo "Departamento: {$departamentos[$dept_index]['nombre_departamento']}\n";
}
echo "\n¿Crear este usuario? (s/n): ";
$confirmar = trim(fgets(STDIN));

if (strtolower($confirmar) !== 's') {
    die("\n❌ Operación cancelada\n\n");
}

// Crear usuario
try {
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO usuarios_admin 
            (nombre_completo, email, password_hash, rol, rol_tipo, id_departamento) 
            VALUES (:nombre, :email, :pass, 'Admin', :rol_tipo, :id_depto)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':nombre' => $nombre,
        ':email' => $email,
        ':pass' => $password_hash,
        ':rol_tipo' => $rol_tipo,
        ':id_depto' => $id_departamento > 0 ? $id_departamento : null
    ]);
    
    $id_usuario = $db->lastInsertId();
    
    echo "\n✅ Usuario creado exitosamente!\n";
    echo "ID: $id_usuario\n";
    echo "Tipo: $rol_tipo\n";
    if ($rol_tipo === 'Admin_Departamento') {
        echo "Departamento: {$departamentos[$dept_index]['nombre_departamento']}\n";
    }
    echo "\n";
    
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo "\n❌ Error: El email ya está registrado\n\n";
    } else {
        echo "\n❌ Error: " . $e->getMessage() . "\n\n";
    }
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n\n";
}

