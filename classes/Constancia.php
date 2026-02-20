<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;

/**
 * Clase para generar constancias de donación en PDF
 */
class Constancia {
    private $db;
    private $templatePath;
    private $outputDir;
    private $libreOfficePath;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->templatePath = __DIR__ . '/../templates/constancia_template.docx';
        $this->outputDir = __DIR__ . '/../uploads/constancias/';
        
        // Ruta de LibreOffice (ajustar según tu instalación)
        $this->libreOfficePath = 'C:\Program Files\LibreOffice\program\soffice.exe';
        
        // Crear directorio si no existe
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
        
        // Configurar PHPWord para HTML
        Settings::setOutputEscapingEnabled(true);
    }
    
    /**
     * Generar constancias para todos los donadores de una solicitud
     */
    public function generarConstanciasParaSolicitud($id_solicitud) {
        try {
            error_log("=== INICIANDO generación de constancias para solicitud {$id_solicitud} ===");
            
            // Obtener información de la solicitud
            $sql = "SELECT s.id_solicitud, s.id_estudiante, s.id_paquete, s.carrera, s.tipo_donacion,
                           s.estatus, s.fecha_solicitud, s.fecha_entrega,
                           ca.nombre as nombre_articulo, 
                           ca.precio_estimado,
                           e.nombre_completo as nombre_representante,
                           d.nombre_departamento as nombre_carrera
                    FROM solicitudes s
                    INNER JOIN catalogo_articulos ca ON s.id_paquete = ca.id_paquete
                    INNER JOIN estudiantes e ON s.id_estudiante = e.id_estudiante
                    LEFT JOIN departamentos d ON s.carrera = d.codigo_departamento
                    WHERE s.id_solicitud = :id_solicitud";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id_solicitud' => $id_solicitud]);
            $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$solicitud) {
                throw new Exception("Solicitud no encontrada");
            }
            
            error_log("Solicitud encontrada: " . print_r($solicitud, true));
            
            // Valores por defecto
            if (empty($solicitud['nombre_articulo'])) {
                $solicitud['nombre_articulo'] = 'Artículo ' . $solicitud['id_paquete'];
            }
            if (empty($solicitud['tipo_donacion'])) {
                $solicitud['tipo_donacion'] = 'No especificado';
            }
            if (empty($solicitud['nombre_carrera'])) {
                $solicitud['nombre_carrera'] = $solicitud['carrera'];
            }
            
            // Obtener donadores
            $sql_donadores = "SELECT * FROM donadores_detalle 
                             WHERE id_solicitud = :id_solicitud";
            $stmt_donadores = $this->db->prepare($sql_donadores);
            $stmt_donadores->execute([':id_solicitud' => $id_solicitud]);
            $donadores = $stmt_donadores->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($donadores)) {
                throw new Exception("No se encontraron donadores para esta solicitud");
            }
            
            error_log("Donadores encontrados: " . count($donadores));
            
            $constancias_generadas = [];
            
            // Generar constancia para cada donador
            foreach ($donadores as $donador) {
                error_log("Generando constancia para: " . $donador['nombre_completo']);
                
                $pdf_data = $this->generarConstanciaIndividual($solicitud, $donador);
                
                if ($pdf_data) {
                    error_log("PDF generado exitosamente: " . $pdf_data['filename']);
                    
                    $id_constancia = $this->guardarConstanciaEnBD(
                        $id_solicitud,
                        $solicitud['id_estudiante'],  
                        $donador,
                        $pdf_data['filename'],
                        $pdf_data['content']
                    );
                    
                    if ($id_constancia) {
                        error_log("✅ Constancia guardada en BD con ID: {$id_constancia}");
                        
                        $constancias_generadas[] = [
                            'id_constancia' => $id_constancia,
                            'nombre' => $donador['nombre_completo'],
                            'email' => $donador['correo_institucional'],
                            'pdf_path' => $pdf_data['filepath']
                        ];
                    } else {
                        error_log("❌ ERROR: No se pudo guardar constancia en BD para " . $donador['nombre_completo']);
                    }
                } else {
                    error_log("❌ ERROR: No se pudo generar PDF para " . $donador['nombre_completo']);
                }
            }
            
            error_log("Total constancias generadas: " . count($constancias_generadas));
            
            return [
                'success' => true,
                'constancias' => $constancias_generadas
            ];
            
        } catch (Exception $e) {
            error_log("❌ ERROR CRÍTICO en generarConstanciasParaSolicitud: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generar constancia individual (DOCX → PDF usando LibreOffice)
     */
    private function generarConstanciaIndividual($solicitud, $donador) {
        try {
            $timestamp = time();
            
            // 1. Cargar y procesar template DOCX
            if (!file_exists($this->templatePath)) {
                error_log("❌ ERROR: Template no encontrado en: {$this->templatePath}");
                throw new Exception("Template no encontrado: {$this->templatePath}");
            }
            
            $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($this->templatePath);
            
            // 2. Reemplazar variables
            $variables = $this->prepararVariables($solicitud, $donador);
            error_log("Variables preparadas: " . print_r($variables, true));
            
            foreach ($variables as $key => $value) {
                $templateProcessor->setValue($key, $value);
            }
            
            // 3. Guardar DOCX temporal
            $tempDocxPath = $this->outputDir . "temp_{$timestamp}.docx";
            $templateProcessor->saveAs($tempDocxPath);
            error_log("DOCX temporal guardado en: {$tempDocxPath}");
            
            // Verificar que se creó el archivo
            if (!file_exists($tempDocxPath)) {
                throw new Exception("No se pudo crear el archivo DOCX temporal");
            }
            
            // 4. Convertir DOCX a PDF usando LibreOffice
            $filename = "Constancia_" . $this->sanitizarNombreArchivo($donador['nombre_completo']) . "_{$timestamp}.pdf";
            $pdfPath = $this->outputDir . $filename;
            
            $conversionExitosa = $this->convertirDocxAPdfConLibreOffice($tempDocxPath, $this->outputDir);
            
            if (!$conversionExitosa) {
                throw new Exception("Error al convertir DOCX a PDF con LibreOffice");
            }
            
            // LibreOffice crea el PDF con el mismo nombre que el DOCX pero con extensión .pdf
            $pdfGeneradoPorLibreOffice = str_replace('.docx', '.pdf', $tempDocxPath);
            
            // Verificar que el PDF se generó
            if (!file_exists($pdfGeneradoPorLibreOffice)) {
                error_log("❌ ERROR: PDF no encontrado en: {$pdfGeneradoPorLibreOffice}");
                throw new Exception("LibreOffice no generó el PDF esperado");
            }
            
            // Renombrar el PDF al nombre final deseado
            if (!rename($pdfGeneradoPorLibreOffice, $pdfPath)) {
                throw new Exception("No se pudo renombrar el PDF generado");
            }
            
            error_log("✅ PDF generado y renombrado a: {$pdfPath}");
            
            // 5. Leer contenido del PDF
            $pdfContent = file_get_contents($pdfPath);
            
            if ($pdfContent === false) {
                throw new Exception("No se pudo leer el contenido del PDF");
            }
            
            error_log("PDF leído exitosamente, tamaño: " . strlen($pdfContent) . " bytes");
            
            // 6. Limpiar archivo DOCX temporal
            if (file_exists($tempDocxPath)) {
                unlink($tempDocxPath);
                error_log("Archivo DOCX temporal eliminado");
            }
            
            // 7. Verificar que el PDF existe
            if (!file_exists($pdfPath)) {
                throw new Exception("El archivo PDF no existe después de crearlo");
            }
            
            return [
                'filename' => $filename,
                'filepath' => $pdfPath,
                'content' => $pdfContent
            ];
            
        } catch (Exception $e) {
            error_log("❌ ERROR en generarConstanciaIndividual: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            // Limpiar archivos temporales en caso de error
            if (isset($tempDocxPath) && file_exists($tempDocxPath)) {
                unlink($tempDocxPath);
            }
            
            return null;
        }
    }
    
    /**
     * Convertir DOCX a PDF usando LibreOffice
     */
    private function convertirDocxAPdfConLibreOffice($docxPath, $outputDir) {
        try {
            // Verificar que LibreOffice existe
            if (!file_exists($this->libreOfficePath)) {
                error_log("❌ ERROR: LibreOffice no encontrado en: {$this->libreOfficePath}");
                return false;
            }
            
            // Verificar que el archivo DOCX existe
            if (!file_exists($docxPath)) {
                error_log("❌ ERROR: Archivo DOCX no encontrado: {$docxPath}");
                return false;
            }
            
            // Verificar que el directorio de salida existe
            if (!is_dir($outputDir)) {
                error_log("❌ ERROR: Directorio de salida no existe: {$outputDir}");
                return false;
            }
            
            error_log("Iniciando conversión con LibreOffice...");
            error_log("  DOCX: {$docxPath}");
            error_log("  Output: {$outputDir}");
            
            // Construir el comando para LibreOffice
            $command = sprintf(
                '"%s" --headless --convert-to pdf --outdir "%s" "%s" 2>&1',
                $this->libreOfficePath,
                $outputDir,
                $docxPath
            );
            
            error_log("  Comando: {$command}");
            
            // Ejecutar el comando
            exec($command, $output, $returnCode);
            
            error_log("  Código de retorno: {$returnCode}");
            
            if (!empty($output)) {
                error_log("  Salida de LibreOffice: " . implode("\n", $output));
            }
            
            if ($returnCode === 0) {
                error_log("✅ Conversión exitosa con LibreOffice");
                return true;
            } else {
                error_log("❌ ERROR: Conversión fallida. Código de retorno: {$returnCode}");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("❌ EXCEPCIÓN en convertirDocxAPdfConLibreOffice: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Preparar variables para el template
     */
    private function prepararVariables($solicitud, $donador) {
        $fecha_actual = date('d/m/Y');
        
        // Obtener el número total de donadores para esta solicitud
        $sql_count = "SELECT COUNT(*) FROM donadores_detalle WHERE id_solicitud = :id_solicitud";
        $stmt_count = $this->db->prepare($sql_count);
        $stmt_count->execute([':id_solicitud' => $solicitud['id_solicitud']]);
        $total_donadores = $stmt_count->fetchColumn();
        
        // Preparar el texto de la fracción
        $texto_fraccion = '';
        if ($total_donadores > 1) {
            $texto_fraccion = "1/{$total_donadores} de ";
        }
        
        // Calcular el precio proporcional
        $precio_individual = $solicitud['precio_estimado'] / $total_donadores;
        
        return [
            'nombre_completo' => $donador['nombre_completo'],
            'numero_control' => $donador['numero_control'],
            'nombre_departamento' => $solicitud['nombre_carrera'],
            'categoria' => $solicitud['tipo_donacion'] ?? 'No especificado',
            'id_solicitud' => str_pad($solicitud['id_solicitud'], 5, '0', STR_PAD_LEFT),
            'id_paquete' => $solicitud['id_paquete'],
            'nombre_articulo' => $texto_fraccion . $solicitud['nombre_articulo'],
            'precio_estimado' => '$' . number_format($precio_individual, 2),
            'fecha' => $fecha_actual
        ];
    }
    
    /**
     * Guardar constancia en la base de datos
     */
    private function guardarConstanciaEnBD($id_solicitud, $id_estudiante, $donador, $filename, $pdf_content) {
        try {
            error_log("=== Guardando constancia en BD ===");
            error_log("ID Solicitud: {$id_solicitud}");
            error_log("ID Estudiante: {$id_estudiante}"); 
            error_log("Nombre: {$donador['nombre_completo']}");
            error_log("Filename: {$filename}");
            error_log("PDF Size: " . strlen($pdf_content) . " bytes");
            
            $sql = "INSERT INTO constancias 
                    (id_solicitud, id_estudiante, nombre_estudiante, numero_control, 
                     correo_institucional, pdf_filename, pdf_data, enviado_por_correo) 
                    VALUES 
                    (:id_sol, :id_est, :nombre, :num_ctrl, :correo, :filename, :pdf_data, 0)";
            
            $stmt = $this->db->prepare($sql);
            
            $params = [
                ':id_sol' => $id_solicitud,
                ':id_est' => $id_estudiante,  
                ':nombre' => $donador['nombre_completo'],
                ':num_ctrl' => $donador['numero_control'],
                ':correo' => $donador['correo_institucional'],
                ':filename' => $filename,
                ':pdf_data' => $pdf_content
            ];
            
            error_log("Parámetros SQL: " . print_r($params, true));
            
            $resultado = $stmt->execute($params);
            
            if (!$resultado) {
                $errorInfo = $stmt->errorInfo();
                error_log("❌ ERROR SQL: " . print_r($errorInfo, true));
                throw new Exception("Error al ejecutar INSERT: " . $errorInfo[2]);
            }
            
            $lastId = $this->db->lastInsertId();
            error_log("✅ INSERT exitoso. ID generado: {$lastId}");
            
            $sqlVerificar = "SELECT constancia_generada FROM solicitudes WHERE id_solicitud = :id_sol";
            $stmtVerificar = $this->db->prepare($sqlVerificar);
            $stmtVerificar->execute([':id_sol' => $id_solicitud]);
            $constanciaGenerada = $stmtVerificar->fetchColumn();
            error_log("Estado constancia_generada en solicitud: {$constanciaGenerada}");
            
            return $lastId;
            
        } catch (Exception $e) {
            error_log("❌ EXCEPCIÓN al guardar constancia en BD: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Obtener constancias de una solicitud
     */
    public function obtenerConstanciasPorSolicitud($id_solicitud) {
        $sql = "SELECT * FROM constancias WHERE id_solicitud = :id_solicitud ORDER BY fecha_generacion DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_solicitud' => $id_solicitud]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Marcar constancia como enviada
     */
    public function marcarComoEnviada($id_constancia) {
        $sql = "UPDATE constancias 
                SET enviado_por_correo = 1, fecha_envio = NOW() 
                WHERE id_constancia = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id_constancia]);
    }
    
    /**
     * Sanitizar nombre de archivo
     */
    private function sanitizarNombreArchivo($filename) {
        $filename = str_replace(' ', '_', $filename);
        $filename = preg_replace('/[^A-Za-z0-9_\-\.]/', '', $filename);
        return $filename;
    }
}