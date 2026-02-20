<?php
require_once __DIR__ . '/../config/google-config.php';

/**
 * Helper para manejar autenticación con Google OAuth 2.0
 */
class GoogleAuth {
    
    /**
     * Obtener URL de autorización de Google
     */
    public static function getAuthUrl($redirect_uri, $state = null) {
        $params = [
            'client_id' => GOOGLE_CLIENT_ID,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => 'email profile',
            'access_type' => 'online',
            'prompt' => 'select_account'
        ];
        
        if ($state) {
            $params['state'] = $state;
        }
        
        // Si queremos restringir solo a un dominio específico
        if (ALLOWED_DOMAIN && $redirect_uri === GOOGLE_REDIRECT_URI_FORM) {
            $params['hd'] = ALLOWED_DOMAIN;
        }
        
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
    
    /**
     * Intercambiar código de autorización por token de acceso
     */
    public static function getAccessToken($code, $redirect_uri) {
        $url = 'https://oauth2.googleapis.com/token';
        
        $params = [
            'code' => $code,
            'client_id' => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri' => $redirect_uri,
            'grant_type' => 'authorization_code'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception('Error al obtener token de acceso de Google');
        }
        
        $data = json_decode($response, true);
        return $data['access_token'];
    }
    
    /**
     * Obtener información del usuario desde Google
     */
    public static function getUserInfo($access_token) {
        $url = 'https://www.googleapis.com/oauth2/v2/userinfo';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception('Error al obtener información del usuario de Google');
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Validar que el email sea del dominio permitido (para formulario)
     */
    public static function validateEmailDomain($email) {
        if (!ALLOWED_DOMAIN) {
            return true; // No hay restricción de dominio
        }
        
        $domain = substr(strrchr($email, "@"), 1);
        return $domain === ALLOWED_DOMAIN;
    }
    
    /**
     * Proceso completo de autenticación
     */
    public static function authenticate($code, $redirect_uri, $require_domain = false) {
        try {
            // Obtener token de acceso
            $access_token = self::getAccessToken($code, $redirect_uri);
            
            // Obtener información del usuario
            $user_info = self::getUserInfo($access_token);
            
            // Validar dominio si es requerido
            if ($require_domain && !self::validateEmailDomain($user_info['email'])) {
                throw new Exception('Debes usar tu correo institucional (@' . ALLOWED_DOMAIN . ')');
            }
            
            return [
                'success' => true,
                'user' => [
                    'email' => $user_info['email'],
                    'name' => $user_info['name'],
                    'picture' => $user_info['picture'] ?? null,
                    'google_id' => $user_info['id']
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}