<?php
require_once __DIR__ . '/../vendor/autoload.php';  // ← AGREGAR ESTA LÍNEA
require_once __DIR__ . '/../config/database.php';

/**
 * Clase para enviar correos electrónicos
 * Usa PHPMailer - instalar con: composer require phpmailer/phpmailer
 */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Email {  
    private $mailer;
    
    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->configurar();
    }
    
    private function configurar() {
        try {
            // Configuración del servidor
            $this->mailer->isSMTP();
            $this->mailer->Host = SMTP_HOST;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = SMTP_USER;
            $this->mailer->Password = SMTP_PASS;
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = SMTP_PORT;
            $this->mailer->CharSet = 'UTF-8';
            
            // Timeouts para evitar bloqueos largos
            $this->mailer->Timeout = 10; // 10 segundos máximo
            $this->mailer->SMTPDebug = 0; // Sin debug
            
            // Remitente
            $this->mailer->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        } catch (Exception $e) {
            error_log("Error al configurar correo: {$this->mailer->ErrorInfo}");
        }
    }
    
    /**
     * Enviar correo de confirmación de solicitud
     */
    public function enviarConfirmacionSolicitud($solicitud_data, $donadores) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearCCs();
            
            // Agregar a todos los donadores
            foreach ($donadores as $donador) {
                $this->mailer->addAddress($donador['correo']);
            }
            
            // Copia al email de contacto si es diferente
            if (!in_array($solicitud_data['email_contacto'], array_column($donadores, 'correo'))) {
                $this->mailer->addCC($solicitud_data['email_contacto']);
            }
            
            $this->mailer->Subject = 'Solicitud de Donación Recibida - TSJ Zapopan';
            
            // Cuerpo del mensaje
            $html = $this->templateConfirmacion($solicitud_data, $donadores);
            $this->mailer->isHTML(true);
            $this->mailer->Body = $html;
            $this->mailer->AltBody = strip_tags($html);
            
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Error al enviar correo: {$this->mailer->ErrorInfo}");
            return false;
        }
    }
    
    /**
     * Enviar correo de aprobación
     */
    public function enviarAprobacion($solicitud_data, $donadores_emails) {
        try {
            $this->mailer->clearAddresses();
            foreach ($donadores_emails as $email) {
                $this->mailer->addAddress($email);
            }
            
            $this->mailer->Subject = '✅ Tu donación ha sido APROBADA - TSJ Zapopan';
            
            $html = $this->templateAprobacion($solicitud_data);
            $this->mailer->isHTML(true);
            $this->mailer->Body = $html;
            
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Error al enviar aprobación: {$this->mailer->ErrorInfo}");
            return false;
        }
    }
    
    /**
     * Enviar correo de rechazo
     */
    public function enviarRechazo($solicitud_data, $donadores_emails, $motivo = '') {
        try {
            $this->mailer->clearAddresses();
            foreach ($donadores_emails as $email) {
                $this->mailer->addAddress($email);
            }
            
            $this->mailer->Subject = 'Actualización sobre tu donación - TSJ Zapopan';
            
            $html = $this->templateRechazo($solicitud_data, $motivo);
            $this->mailer->isHTML(true);
            $this->mailer->Body = $html;
            
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Error al enviar rechazo: {$this->mailer->ErrorInfo}");
            return false;
        }
    }
    
    /**
     * Enviar recordatorio de expiración próxima
     */
    public function enviarRecordatorioExpiracion($solicitud_data, $donadores_emails) {
        try {
            $this->mailer->clearAddresses();
            foreach ($donadores_emails as $email) {
                $this->mailer->addAddress($email);
            }
            
            $this->mailer->Subject = '⚠️ Tu solicitud de donación expira pronto - TSJ Zapopan';
            
            $html = $this->templateRecordatorio($solicitud_data);
            $this->mailer->isHTML(true);
            $this->mailer->Body = $html;
            
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Error al enviar recordatorio: {$this->mailer->ErrorInfo}");
            return false;
        }
    }
    
    /**
     * Template: Confirmación de solicitud
     */
    private function templateConfirmacion($data, $donadores) {
        $nombres_donadores = implode('<br>', array_map(function($d) {
            return "• {$d['nombre']} ({$d['numero_control']})";
        }, $donadores));
        
        $fecha_exp = date('d/m/Y H:i', strtotime($data['fecha_expiracion']));
        
        return "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #5b6ad0; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; }
                .info-box { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #5b6ad0; }
                .warning { background: #fff3cd; border-left-color: #ffc107; padding: 15px; margin: 15px 0; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Solicitud de Donación Recibida</h1>
                </div>
                
                <div class='content'>
                    <p>Estimado(s) estudiante(s):</p>
                    
                    <p>Hemos recibido correctamente su propuesta de donación para el proceso de titulación.</p>
                    
                    <div class='info-box'>
                        <h3>📋 Detalles de la solicitud:</h3>
                        <p><strong>ID del Paquete:</strong> {$data['id_paquete']}</p>
                        <p><strong>Artículo:</strong> {$data['nombre_articulo']}</p>
                        <p><strong>Carrera:</strong> {$data['carrera']}</p>
                        <p><strong>Categoría:</strong> {$data['tipo_donacion']}</p>
                        <p><strong>Fecha de solicitud:</strong> " . date('d/m/Y H:i') . "</p>
                    </div>
                    
                    <div class='info-box'>
                        <h3>👥 Donadores:</h3>
                        <p>{$nombres_donadores}</p>
                    </div>
                    
                    <div class='warning'>
                        <h3>⚠️ IMPORTANTE - LEA ATENTAMENTE:</h3>
                        <ol>
                            <li><strong>NO adquiera el artículo todavía.</strong> Espere la aprobación oficial vía correo electrónico.</li>
                            <li>Su solicitud expira el <strong>{$fecha_exp}</strong> si no recibe aprobación.</li>
                            <li>Una vez aprobada, tendrá un plazo para entregar el artículo físicamente.</li>
                        </ol>
                    </div>
                    
                    <p>Recibirá un correo de confirmación cuando la subdirección revise y apruebe su solicitud.</p>
                </div>
                
                <div class='footer'>
                    <p>Este es un mensaje automático. Para cualquier duda, contacte a:<br>
                    <strong>administrador@zapopan.tecmm.edu.mx</strong></p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Template: Aprobación
     */
    private function templateAprobacion($data) {
        return "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; }
                .success-box { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 15px 0; }
                .info-box { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #5b6ad0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>✅ ¡Tu donación ha sido aprobada!</h1>
                </div>
                
                <div class='content'>
                    <div class='success-box'>
                        <h3>¡Felicidades!</h3>
                        <p>La subdirección ha aprobado tu propuesta de donación para el paquete <strong>{$data['id_paquete']}</strong>.</p>
                    </div>
                    
                    <div class='info-box'>
                        <h3>📦 Siguientes pasos:</h3>
                        <ol>
                            <li>Ya puedes adquirir el artículo aprobado: <strong>{$data['nombre_articulo']}</strong></li>
                            <li>Una vez que lo tengas, preséntalo en la subdirección para su validación.</li>
                            <li>Lleva contigo tu identificación y número de control.</li>
                        </ol>
                    </div>
                    
                    <p>¡Gracias por tu contribución al instituto!</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Template: Rechazo
     */
    private function templateRechazo($data, $motivo) {
        $motivo_html = $motivo ? "<p><strong>Motivo:</strong> {$motivo}</p>" : "";
        
        return "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; }
                .info-box { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Actualización sobre tu donación</h1>
                </div>
                
                <div class='content'>
                    <div class='info-box'>
                        <p>Lamentamos informarte que tu solicitud para el paquete <strong>{$data['id_paquete']}</strong> no ha sido aprobada.</p>
                        {$motivo_html}
                    </div>
                    
                    <p>Te invitamos a revisar el catálogo nuevamente y presentar una nueva propuesta con otro artículo disponible.</p>
                    
                    <p>Para cualquier aclaración, contacta a la subdirección.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Template: Recordatorio de expiración
     */
    private function templateRecordatorio($data) {
        $fecha_exp = date('d/m/Y H:i', strtotime($data['fecha_expiracion']));
        
        return "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #ffc107; color: #333; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; }
                .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>⚠️ Tu solicitud expira pronto</h1>
                </div>
                
                <div class='content'>
                    <div class='warning'>
                        <h3>Recordatorio importante</h3>
                        <p>Tu solicitud para el paquete <strong>{$data['id_paquete']}</strong> expirará el <strong>{$fecha_exp}</strong>.</p>
                        <p>Si no recibes confirmación antes de esa fecha, el artículo volverá a estar disponible para otros estudiantes.</p>
                    </div>
                    
                    <p>Si tienes dudas sobre el estatus de tu solicitud, contacta a la subdirección.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Enviar constancia de donación por correo electrónico con PDF adjunto
     */
    public function enviarConstancia($nombre_estudiante, $email_estudiante, $pdf_path, $pdf_filename) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            
            $this->mailer->addAddress($email_estudiante);
            
            $this->mailer->Subject = '📄 Constancia de Donación - TSJ Zapopan';
            
            // Adjuntar el PDF
            if (file_exists($pdf_path)) {
                $this->mailer->addAttachment($pdf_path, $pdf_filename);
            } else {
                throw new Exception("Archivo PDF no encontrado: {$pdf_path}");
            }
            
            $html = $this->templateConstancia($nombre_estudiante);
            $this->mailer->isHTML(true);
            $this->mailer->Body = $html;
            $this->mailer->AltBody = strip_tags($html);
            
            $this->mailer->send();
            return true;
            
        } catch (Exception $e) {
            error_log("Error al enviar constancia: {$this->mailer->ErrorInfo}");
            return false;
        }
    }
    
    /**
     * Template: Envío de constancia
     */
    private function templateConstancia($nombre_estudiante) {
        return "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; }
                .success-box { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 15px 0; }
                .info-box { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #5b6ad0; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>📄 Constancia de Donación</h1>
                </div>
                
                <div class='content'>
                    <p>Estimado(a) <strong>{$nombre_estudiante}</strong>:</p>
                    
                    <div class='success-box'>
                        <h3>¡Felicidades!</h3>
                        <p>Tu donación ha sido recibida y validada exitosamente.</p>
                    </div>
                    
                    <div class='info-box'>
                        <h3>📎 Documento adjunto</h3>
                        <p>Encontrarás adjunta tu <strong>Constancia de Donación</strong> en formato PDF.</p>
                        <p>Este documento certifica oficialmente tu contribución al Tecnológico Superior de Jalisco.</p>
                    </div>
                    
                    <div class='info-box'>
                        <h3>📋 Siguientes pasos para tu titulación:</h3>
                        <ol>
                            <li>Descarga y guarda tu constancia en un lugar seguro</li>
                            <li>Presenta este documento en el departamento de Servicios Escolares</li>
                            <li>Continúa con los siguientes requisitos de tu proceso de titulación</li>
                        </ol>
                    </div>
                    
                    <p><strong>Importante:</strong> Conserva este documento, ya que es parte de tu expediente de titulación.</p>
                    
                    <p>¡Gracias por tu contribución al instituto!</p>
                </div>
                
                <div class='footer'>
                    <p>Este es un mensaje automático. Para cualquier duda, contacte a:<br>
                    <strong>administrador@zapopan.tecmm.edu.mx</strong></p>
                    <p>Tecnológico Superior de Jalisco - Campus Zapopan</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}