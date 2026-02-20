<?php
/**
 * Cron Job: Expirar solicitudes vencidas
 * Ejecutar cada hora: 0 * * * * php /path/to/cron/expirar-solicitudes.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Solicitud.php';
require_once __DIR__ . '/../classes/Email.php';

echo "[" . date('Y-m-d H:i:s') . "] Iniciando proceso de expiración...\n";

try {
    $solicitud = new Solicitud();
    
    // Obtener solicitudes que van a expirar en las próximas 24 horas
    $db = Database::getInstance()->getConnection();
    $sql = "SELECT s.*, 
            GROUP_CONCAT(d.correo_institucional SEPARATOR ',') as correos_donadores
            FROM solicitudes s
            LEFT JOIN donadores_detalle d ON s.id_solicitud = d.id_solicitud
            WHERE s.estatus = 'Reservado'
            AND s.fecha_expiracion BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
            GROUP BY s.id_solicitud";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $proximas_a_expirar = $stmt->fetchAll();
    
    // Enviar recordatorios
    if (!empty($proximas_a_expirar)) {
        echo "Enviando " . count($proximas_a_expirar) . " recordatorios...\n";
        
        $email = new Email();
        foreach ($proximas_a_expirar as $sol) {
            $correos = explode(',', $sol['correos_donadores']);
            $email->enviarRecordatorioExpiracion($sol, $correos);
            echo "  - Recordatorio enviado para solicitud #{$sol['id_solicitud']}\n";
        }
    }
    
    // Expirar solicitudes vencidas
    $expiradas = $solicitud->expirarVencidas();
    echo "Solicitudes expiradas: {$expiradas}\n";
    
    echo "[" . date('Y-m-d H:i:s') . "] Proceso completado\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Error en cron de expiración: " . $e->getMessage());
}
