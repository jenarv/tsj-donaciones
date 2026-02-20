<?php
/**
 * Google OAuth Callback for Admin Users
 * Handles the OAuth flow for admin authentication
 */

session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/GoogleAuth.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../config/google-config.php';

// Check if we have the authorization code
if (!isset($_GET['code'])) {
    header('Location: /tsj-donaciones/admin/index.html?error=auth_failed');
    exit;
}

try {
    // Authenticate with Google
    $result = GoogleAuth::authenticate(
        $_GET['code'], 
        GOOGLE_REDIRECT_URI_ADMIN,
        false // Don't require domain for admin (can be configured)
    );

    if (!$result['success']) {
        throw new Exception($result['error']);
    }

    $user = $result['user'];
    
    // Check if user is an authorized admin
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("SELECT * FROM usuarios_admin WHERE email = :email AND activo = 1");
    $stmt->execute([':email' => $user['email']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        throw new Exception('No tienes permisos de administrador. Contacta al administrador del sistema.');
    }
    
    // Update admin's Google ID and picture if not set
    if (empty($admin['google_id'])) {
        $stmt = $db->prepare("
            UPDATE usuarios_admin 
            SET google_id = :google_id, picture_url = :picture, ultimo_acceso = NOW()
            WHERE id_usuario = :id
        ");
        $stmt->execute([
            ':google_id' => $user['google_id'],
            ':picture' => $user['picture'],
            ':id' => $admin['id_usuario']
        ]);
    } else {
        // Just update last access
        $stmt = $db->prepare("UPDATE usuarios_admin SET ultimo_acceso = NOW() WHERE id_usuario = :id");
        $stmt->execute([':id' => $admin['id_usuario']]);
    }
    
    // Store in session
    $_SESSION['admin_auth'] = [
        'id_usuario' => $admin['id_usuario'],
        'email' => $admin['email'],
        'nombre' => $admin['nombre_completo'],
        'rol' => $admin['rol'],
        'picture' => $user['picture']
    ];
    
    // Redirect to admin panel
    header('Location: /tsj-donaciones/admin/index.html');
    exit;
    
} catch (Exception $e) {
    error_log("Google Auth Error (Admin): " . $e->getMessage());
    header('Location: /tsj-donaciones/admin/index.html?error=' . urlencode($e->getMessage()));
    exit;
}
