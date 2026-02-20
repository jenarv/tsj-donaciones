<?php
/**
 * Configuración de la base de datos
 */

// Configuración de base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'tsj_donaciones');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configuración de correo electrónico
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'jennyfermiryam@gmail.com');
define('SMTP_PASS', 'lujy yndo pcnw galm');
define('SMTP_PORT', 587);
define('SMTP_FROM', 'jennyfermiryam@gmail.com');
define('SMTP_FROM_NAME', 'TSJ Zapopan - Donaciones');

// URL base del sistema
define('BASE_URL', 'http://localhost/tsj-donaciones');

// Timezone
date_default_timezone_set('America/Mexico_City');

// Configuración de sesión - ANTES de session_start()
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}