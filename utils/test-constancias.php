<?php
/**
 * Script de Prueba - Generación de Constancias
 * 
 * Este script permite probar la generación de constancias sin necesidad
 * de usar la interfaz web.
 * 
 * Uso: php utils/test-constancias.php [id_solicitud]
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Constancia.php';
require_once __DIR__ . '/../classes/Email.php';

echo "=================================================\n";
echo "  PRUEBA DE GENERACIÓN DE CONSTANCIAS\n";
echo "=================================================\n\n";

// Obtener ID de solicitud desde argumentos o usar uno de ejemplo
$id_solicitud = isset($argv[1]) ? (int)$argv[1] : null;

if (!$id_solicitud) {
    echo "❌ Error: Debe proporcionar un ID de solicitud\n";
    echo "Uso: php test-constancias.php [id_solicitud]\n\n";
    
    // Mostrar solicitudes disponibles
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT id_solicitud, id_paquete, carrera, estatus 
                        FROM solicitudes 
                        WHERE estatus = 'Entregado' 
                        ORDER BY id_solicitud DESC 
                        LIMIT 10");
    $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($solicitudes)) {
        echo "Solicitudes disponibles (estado: Entregado):\n";
        echo "-------------------------------------------\n";
        foreach ($solicitudes as $sol) {
            echo "ID: {$sol['id_solicitud']} | Paquete: {$sol['id_paquete']} | Carrera: {$sol['carrera']}\n";
        }
    } else {
        echo "No hay solicitudes con estado 'Entregado'\n";
    }
    
    exit(1);
}

echo "📋 Generando constancias para solicitud ID: {$id_solicitud}\n\n";

try {
    $constancia = new Constancia();
    
    echo "1️⃣  Iniciando generación...\n";
    $resultado = $constancia->generarConstanciasParaSolicitud($id_solicitud);
    
    if (!$resultado['success']) {
        echo "❌ Error: {$resultado['error']}\n";
        exit(1);
    }
    
    $total = count($resultado['constancias']);
    echo "✅ Se generaron {$total} constancia(s)\n\n";
    
    echo "2️⃣  Detalles de las constancias generadas:\n";
    echo "-------------------------------------------\n";
    
    foreach ($resultado['constancias'] as $i => $const) {
        $num = $i + 1;
        echo "\n🎓 Constancia #{$num}:\n";
        echo "   Nombre:   {$const['nombre']}\n";
        echo "   Email:    {$const['email']}\n";
        echo "   Archivo:  " . basename($const['pdf_path']) . "\n";
        echo "   Tamaño:   " . number_format(filesize($const['pdf_path']) / 1024, 2) . " KB\n";
    }
    
    echo "\n3️⃣  Verificando constancias en base de datos:\n";
    echo "-------------------------------------------\n";
    
    $constancias_bd = $constancia->obtenerConstanciasPorSolicitud($id_solicitud);
    echo "✅ {count($constancias_bd)} registro(s) en tabla 'constancias'\n";
    
    // Preguntar si desea enviar por correo
    echo "\n4️⃣  ¿Desea enviar las constancias por correo? (s/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim(strtolower($line)) === 's') {
        echo "\n📧 Enviando constancias por correo...\n";
        $email = new Email();
        $enviadas = 0;
        
        foreach ($resultado['constancias'] as $const) {
            try {
                echo "   📨 Enviando a {$const['email']}... ";
                
                $exito = $email->enviarConstancia(
                    $const['nombre'],
                    $const['email'],
                    $const['pdf_path'],
                    basename($const['pdf_path'])
                );
                
                if ($exito) {
                    echo "✅ Enviado\n";
                    $enviadas++;
                    
                    // Marcar como enviada en BD
                    foreach ($constancias_bd as $c_bd) {
                        if ($c_bd['correo_institucional'] === $const['email'] && !$c_bd['enviado_por_correo']) {
                            $constancia->marcarComoEnviada($c_bd['id_constancia']);
                            break;
                        }
                    }
                } else {
                    echo "❌ Error al enviar\n";
                }
            } catch (Exception $e) {
                echo "❌ Error: {$e->getMessage()}\n";
            }
        }
        
        echo "\n✅ {$enviadas} de {$total} constancias enviadas\n";
    } else {
        echo "\n⏭️  Envío de correos omitido\n";
    }
    
    echo "\n=================================================\n";
    echo "  ✅ PRUEBA COMPLETADA EXITOSAMENTE\n";
    echo "=================================================\n";
    
    echo "\n📁 Los archivos PDF se encuentran en:\n";
    echo "   /uploads/constancias/\n\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: {$e->getMessage()}\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
