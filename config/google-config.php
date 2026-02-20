<?php
/**
 * Configuración de Google OAuth 2.0
 * 
 * INSTRUCCIONES:
 * 1. Ve a https://console.cloud.google.com/
 * 2. Crea un nuevo proyecto o selecciona uno existente
 * 3. Habilita "Google+ API" o "Google Identity"
 * 4. Ve a "Credenciales" y crea un "ID de cliente de OAuth 2.0"
 * 5. Configura los orígenes autorizados y URIs de redirección
 * 6. Copia el Client ID y Client Secret aquí
 */
// Configuración de sesión
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); 
ini_set('session.use_only_cookies', 1);

define('GOOGLE_CLIENT_ID', '');
define('GOOGLE_CLIENT_SECRET', '');

// URIs de redirección autorizados
define('GOOGLE_REDIRECT_URI_STUDENT', 'http://localhost/tsj-donaciones/api/auth/google-callback-student.php');
define('GOOGLE_REDIRECT_URI_ADMIN', 'http://localhost/tsj-donaciones/api/auth/google-callback-admin.php');
define('GOOGLE_REDIRECT_URI_FORM', 'http://localhost/tsj-donaciones/api/auth/google-callback-student.php'); // Backward compatibility

// Dominio permitido para el formulario (opcional - para restringir solo a emails institucionales)
define('ALLOWED_DOMAIN', 'zapopan.tecmm.edu.mx'); // Deja vacío para permitir cualquier Google account


