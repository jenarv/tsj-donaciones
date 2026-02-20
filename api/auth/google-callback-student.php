<?php
/**
 * Google OAuth Callback for Students
 * Handles the OAuth flow for student authentication
 */

session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/GoogleAuth.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../config/google-config.php';

// Check if we have the authorization code
if (!isset($_GET['code'])) {
    header('Location: /tsj-donaciones/public/index.html?error=auth_failed');
    exit;
}

try {
    // Authenticate with Google
    $result = GoogleAuth::authenticate(
        $_GET['code'], 
        GOOGLE_REDIRECT_URI_STUDENT,
        true // Require institutional domain
    );

    if (!$result['success']) {
        throw new Exception($result['error']);
    }

    $user = $result['user'];
    
    // Get or create student record
    $db = Database::getInstance()->getConnection();
    
    // Check if student exists
    $stmt = $db->prepare("SELECT * FROM estudiantes WHERE google_id = :google_id");
    $stmt->execute([':google_id' => $user['google_id']]);
    $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$estudiante) {
        // Create new student record
        $stmt = $db->prepare("
            INSERT INTO estudiantes (email, google_id, nombre_completo, picture_url, ultimo_acceso)
            VALUES (:email, :google_id, :nombre, :picture, NOW())
        ");
        $stmt->execute([
            ':email' => $user['email'],
            ':google_id' => $user['google_id'],
            ':nombre' => $user['name'],
            ':picture' => $user['picture']
        ]);
        
        $id_estudiante = $db->lastInsertId();
    } else {
        // Update existing student
        $id_estudiante = $estudiante['id_estudiante'];
        $stmt = $db->prepare("
            UPDATE estudiantes 
            SET nombre_completo = :nombre, picture_url = :picture, ultimo_acceso = NOW()
            WHERE id_estudiante = :id
        ");
        $stmt->execute([
            ':nombre' => $user['name'],
            ':picture' => $user['picture'],
            ':id' => $id_estudiante
        ]);
    }
    
    // Check student's request status
    $stmt = $db->prepare("
        SELECT estatus 
        FROM solicitudes 
        WHERE id_estudiante = :id 
        AND estatus IN ('Reservado', 'Aprobado', 'En_espera')
        ORDER BY fecha_solicitud DESC 
        LIMIT 1
    ");
    $stmt->execute([':id' => $id_estudiante]);
    $solicitud_activa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Store in session
    $_SESSION['student_auth'] = [
        'id_estudiante' => $id_estudiante,
        'email' => $user['email'],
        'nombre' => $user['name'],
        'picture' => $user['picture'],
        'google_id' => $user['google_id'],
        'solicitud_activa' => $solicitud_activa ? $solicitud_activa['estatus'] : null
    ];
    
    // Redirect back to the form
    header('Location: /tsj-donaciones/public/index.html');
    exit;
    
} catch (Exception $e) {
    error_log("Google Auth Error (Student): " . $e->getMessage());
    header('Location: /tsj-donaciones/public/index.html?error=' . urlencode($e->getMessage()));
    exit;
}
