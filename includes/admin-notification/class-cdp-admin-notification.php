<?php
/**
 * Cuidándote - Notificación a Administradores
 * 
 * Envía emails de notificación a los administradores cuando
 * un usuario solicita un presupuesto.
 * 
 * @package CuidandotePresupuestos
 * @version 2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CDP_Admin_Notification {
    
    /**
     * Email de destino para notificaciones
     */
    private $admin_email = 'info@cuidandoteserviciosauxiliares.com';
    
    /**
     * Constructor
     */
    public function __construct() {
        // Conectar al hook cuando se guarda un presupuesto
        add_action('cdp_presupuesto_guardado', array($this, 'enviar_notificacion_admin'), 20, 2);
        
        // Permitir configurar el email desde opciones de WordPress
        $email_config = get_option('cdp_admin_notification_email');
        if (!empty($email_config)) {
            $this->admin_email = $email_config;
        }
    }
    
    /**
     * Enviar notificación a administradores
     * 
     * @param int $presupuesto_id ID del presupuesto guardado
     * @param array $data Datos del presupuesto
     */
    public function enviar_notificacion_admin($presupuesto_id, $data) {
        // Obtener datos completos del presupuesto de la BD
        global $wpdb;
        $tabla = $wpdb->prefix . 'cdp_presupuestos';
        
        $presupuesto = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tabla WHERE id = %d",
            $presupuesto_id
        ), ARRAY_A);
        
        if (!$presupuesto) {
            error_log('CDP Admin Notification: No se encontró presupuesto ID ' . $presupuesto_id);
            return false;
        }
        
        // Preparar datos para el email
        $email_data = array(
            'presupuesto_id' => $presupuesto_id,
            'nombre' => $presupuesto['nombre'],
            'email' => $presupuesto['email'],
            'telefono' => $presupuesto['telefono'],
            'codigo_postal' => $presupuesto['codigo_postal'] ?? 'No especificado',
            'tipo_servicio' => $presupuesto['tipo_servicio_label'],
            'horas_semanales' => $presupuesto['horas_semanales'],
            'pago_mensual' => number_format($presupuesto['pago_mensual'], 2, ',', '.'),
            'fecha_solicitud' => date('d/m/Y H:i', strtotime($presupuesto['created_at'])),
            'llamada_fecha' => !empty($presupuesto['llamada_fecha']) ? date('d/m/Y', strtotime($presupuesto['llamada_fecha'])) : 'No programada',
            'llamada_hora' => !empty($presupuesto['llamada_hora']) ? $presupuesto['llamada_hora'] : '',
        );
        
        // Generar URL al panel de admin (si existe)
        $admin_url = admin_url('admin.php?page=cuidandote-presupuestos&presupuesto_id=' . $presupuesto_id);
        $email_data['admin_url'] = $admin_url;
        
        // Preparar el email
        $to = $this->admin_email;
        $subject = '🔔 Nuevo presupuesto solicitado - ' . $email_data['nombre'];
        $message = $this->get_email_template($email_data);
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Sistema Cuidándote <noreply@cuidandoteserviciosauxiliares.com>',
            'Reply-To: ' . $presupuesto['email'],
        );
        
        // Enviar el email
        $enviado = wp_mail($to, $subject, $message, $headers);
        
        if ($enviado) {
            // Registrar en log
            error_log(sprintf(
                'CDP Admin Notification: Email enviado a %s para presupuesto #%d',
                $to,
                $presupuesto_id
            ));
            
            // Actualizar registro en BD
            $wpdb->update(
                $tabla,
                array('admin_notificado' => 1, 'admin_notificado_at' => current_time('mysql')),
                array('id' => $presupuesto_id),
                array('%d', '%s'),
                array('%d')
            );
            
            return true;
        } else {
            error_log(sprintf(
                'CDP Admin Notification: ERROR al enviar email a %s para presupuesto #%d',
                $to,
                $presupuesto_id
            ));
            return false;
        }
    }
    
    /**
     * Generar template HTML del email
     * 
     * @param array $data Datos del presupuesto
     * @return string HTML del email
     */
    private function get_email_template($data) {
        $template = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Presupuesto Solicitado</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f5f5f5; padding: 20px 0;">
        <tr>
            <td align="center">
                <!-- Contenedor principal -->
                <table role="presentation" cellpadding="0" cellspacing="0" width="600" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    
                    <!-- Header con color corporativo -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #0B8547 0%, #256D9B 100%); padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: bold;">
                                🔔 Nuevo Presupuesto Solicitado
                            </h1>
                        </td>
                    </tr>
                    
                    <!-- Contenido -->
                    <tr>
                        <td style="padding: 30px;">
                            
                            <p style="margin: 0 0 20px 0; font-size: 16px; color: #333;">
                                Se ha recibido una nueva solicitud de presupuesto:
                            </p>
                            
                            <!-- Datos del cliente -->
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 20px; border: 1px solid #e0e0e0; border-radius: 6px;">
                                <tr style="background-color: #f8f9fa;">
                                    <td colspan="2" style="padding: 15px; border-bottom: 1px solid #e0e0e0;">
                                        <h2 style="margin: 0; font-size: 18px; color: #256D9B;">
                                            👤 Datos del Cliente
                                        </h2>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid #f0f0f0; font-weight: bold; color: #666; width: 40%;">
                                        Nombre:
                                    </td>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid #f0f0f0; color: #333;">
                                        ' . esc_html($data['nombre']) . '
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid #f0f0f0; font-weight: bold; color: #666;">
                                        Email:
                                    </td>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid #f0f0f0;">
                                        <a href="mailto:' . esc_attr($data['email']) . '" style="color: #667eea; text-decoration: none;">
                                            ' . esc_html($data['email']) . '
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid #f0f0f0; font-weight: bold; color: #666;">
                                        Teléfono:
                                    </td>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid #f0f0f0;">
                                        <a href="tel:' . esc_attr($data['telefono']) . '" style="color: #667eea; text-decoration: none;">
                                            ' . esc_html($data['telefono']) . '
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 15px; font-weight: bold; color: #666;">
                                        Código Postal:
                                    </td>
                                    <td style="padding: 12px 15px; color: #333;">
                                        ' . esc_html($data['codigo_postal']) . '
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Datos del servicio -->
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 20px; border: 1px solid #e0e0e0; border-radius: 6px;">
                                <tr style="background-color: #f8f9fa;">
                                    <td colspan="2" style="padding: 15px; border-bottom: 1px solid #e0e0e0;">
                                        <h2 style="margin: 0; font-size: 18px; color: #256D9B;">
                                            📋 Servicio Solicitado
                                        </h2>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid #f0f0f0; font-weight: bold; color: #666; width: 40%;">
                                        Tipo de Servicio:
                                    </td>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid #f0f0f0; color: #333;">
                                        ' . esc_html($data['tipo_servicio']) . '
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid #f0f0f0; font-weight: bold; color: #666;">
                                        Horas Semanales:
                                    </td>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid #f0f0f0; color: #333;">
                                        ' . esc_html($data['horas_semanales']) . ' horas
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid #f0f0f0; font-weight: bold; color: #666;">
                                        Pago Mensual:
                                    </td>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid #f0f0f0;">
                                        <strong style="color: #28a745; font-size: 18px;">' . esc_html($data['pago_mensual']) . ' €</strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 15px; font-weight: bold; color: #666;">
                                        Fecha Solicitud:
                                    </td>
                                    <td style="padding: 12px 15px; color: #333;">
                                        ' . esc_html($data['fecha_solicitud']) . '
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Llamada programada -->
                            ' . ($data['llamada_fecha'] !== 'No programada' ? '
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 20px; border: 1px solid #ffc107; border-radius: 6px; background-color: #fff8e1;">
                                <tr>
                                    <td style="padding: 15px;">
                                        <p style="margin: 0; font-size: 14px; color: #856404;">
                                            <strong>📞 Llamada programada:</strong> ' . esc_html($data['llamada_fecha']) . ' a las ' . esc_html($data['llamada_hora']) . '
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            ' : '') . '
                            
                            <!-- Botón de acción -->
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="' . esc_url($data['admin_url']) . '" 
                                           style="display: inline-block; padding: 15px 40px; background: linear-gradient(135deg, #0B8547 0%, #256D9B 100%); color: #ffffff; text-decoration: none; border-radius: 30px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 6px rgba(102, 126, 234, 0.3);">
                                            Ver Detalles en el Panel Admin
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Información adicional -->
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 6px;">
                                <tr>
                                    <td>
                                        <p style="margin: 0; font-size: 13px; color: #666; line-height: 1.6;">
                                            💡 <strong>Nota:</strong> Este email es una notificación automática. 
                                            El cliente ha recibido su presupuesto por correo electrónico y puede estar esperando tu contacto.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 8px 8px; border-top: 1px solid #e0e0e0;">
                            <p style="margin: 0 0 10px 0; font-size: 14px; color: #666;">
                                <strong>Cuidándote Servicios Auxiliares</strong>
                            </p>
                            <p style="margin: 0; font-size: 12px; color: #999;">
                                Sistema automático de gestión de presupuestos
                            </p>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
        
        return $template;
    }
    
    /**
     * Configurar email de administrador desde el panel
     * (para usar en el archivo de configuración del plugin)
     */
    public static function config_admin_email($email) {
        update_option('cdp_admin_notification_email', sanitize_email($email));
    }
}

// Inicializar la clase
new CDP_Admin_Notification();
