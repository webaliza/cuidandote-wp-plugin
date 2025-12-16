<?php
/**
 * Clase para envío de emails
 * 
 * Envía emails HTML profesionales con la propuesta de asistencia
 */

if (!defined('ABSPATH')) {
    exit;
}

class CDP_Mailer {
    
    /**
     * Datos del presupuesto
     */
    private $presupuesto;
    
    /**
     * Constructor
     */
    public function __construct($presupuesto) {
        $this->presupuesto = $presupuesto;
    }
    
    /**
     * Enviar email de propuesta
     */
    public function enviar_propuesta() {
        $to = $this->presupuesto->email;
        $subject = '🏠 Tu Propuesta de Asistencia - Cuidándote Servicios Auxiliares';
        $message = $this->generar_html_propuesta();
        $headers = $this->get_headers();
        
        // Enviar email
        $enviado = wp_mail($to, $subject, $message, $headers);
        
        if ($enviado) {
            CDP_Database::marcar_email_enviado($this->presupuesto->id);
        }
        
        return $enviado;
    }
    
    /**
     * Obtener headers del email
     */
    private function get_headers() {
        $from_email = get_option('cdp_email_from', 'info@cuidandoteserviciosauxiliares.com');
        $from_name = get_option('cdp_email_from_name', 'Cuidándote Servicios Auxiliares');
        
        return array(
            'Content-Type: text/html; charset=UTF-8',
            "From: $from_name <$from_email>",
            "Reply-To: $from_name <$from_email>",
        );
    }
    
    /**
     * Formatear moneda
     */
    private function formatear_moneda($valor) {
        return number_format((float) $valor, 2, ',', '.') . '€';
    }
    
    /**
     * Obtener URL del detalle del presupuesto
     */
    private function get_url_detalle() {
        $page = get_page_by_path('presupuesto-cuidadores');
        
        if ($page) {
            return add_query_arg('token', $this->presupuesto->token, get_permalink($page->ID));
        }
        
        return home_url('/presupuesto-cuidadores/?token=' . $this->presupuesto->token);
    }
    
    /**
     * Generar HTML del email de propuesta
     */
    private function generar_html_propuesta() {
        $nombre = esc_html($this->presupuesto->nombre);
        $servicio = esc_html($this->presupuesto->tipo_servicio_label);
        $pago_mensual = $this->formatear_moneda($this->presupuesto->pago_mensual);
        $url_detalle = esc_url($this->get_url_detalle());
        
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Propuesta de Asistencia - Cuidándote</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f5f5f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
    
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5;">
        <tr>
            <td align="center" style="padding: 20px 10px;">
                
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #2c8cbe 0%, #1a5276 100%); padding: 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600;">
                                Propuesta de Asistencia
                            </h1>
                        </td>
                    </tr>
                    
                    <!-- Contenido -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            
                            <!-- Saludo -->
                            <p style="margin: 0 0 20px; color: #333; font-size: 16px; line-height: 1.6;">
                                Estimado/a <strong><?php echo $nombre; ?></strong>,
                            </p>
                            <p style="margin: 0 0 30px; color: #555; font-size: 15px; line-height: 1.7;">
                                Te compartimos el enlace a tu presupuesto personalizado. Adicionalmente, un consultor te estará asesorando sin compromiso para ayudarte a encontrar las mejores modalidades de servicio según tus necesidades.
                            </p>
                            
                            <!-- Card del servicio -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 30px;">
                                <tr>
                                    <td style="background: #f8f9fa; border-radius: 12px; padding: 25px; text-align: center;">
                                        
                                        <!-- Icono cuidador -->
                                        <div style="width: 70px; height: 70px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center;">
                                            <span style="font-size: 32px;">👩‍⚕️</span>
                                        </div>
                                        
                                        <!-- Servicio -->
                                        <p style="margin: 0 0 5px; color: #667eea; font-size: 13px; font-weight: 600; text-transform: uppercase;">
                                            Servicio solicitado
                                        </p>
                                        <p style="margin: 0 0 20px; color: #333; font-size: 16px;">
                                            <?php echo $servicio; ?>
                                        </p>
                                        
                                        <!-- Precio -->
                                        <p style="margin: 0 0 5px; color: #667eea; font-size: 13px; font-weight: 600;">
                                            Pago mensual para el cuidador/a
                                        </p>
                                        <p style="margin: 0; color: #2c8cbe; font-size: 36px; font-weight: 700;">
                                            <?php echo $pago_mensual; ?>/mes
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Mensaje adicional -->
                            <p style="margin: 0 0 25px; color: #555; font-size: 15px; line-height: 1.7; text-align: center;">
                                Si tienes cualquier duda o deseas ajustar algún detalle, estaremos encantados de ayudarte.
                            </p>
                            <p style="margin: 0 0 30px; color: #555; font-size: 15px; text-align: center;">
                                Un cordial saludo,<br>
                                <strong>Equipo Cuidándote</strong>
                            </p>
                            
                            <!-- Botón CTA -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <a href="<?php echo $url_detalle; ?>" 
                                           style="display: inline-block; background: linear-gradient(135deg, #2c8cbe 0%, #1a5276 100%); color: #ffffff; padding: 16px 40px; border-radius: 30px; text-decoration: none; font-size: 16px; font-weight: 600; box-shadow: 0 4px 15px rgba(44, 140, 190, 0.3);">
                                            Detalle Presupuesto
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                        </td>
                    </tr>
                    
                    <!-- Logos institucionales -->
                    <tr>
                        <td style="padding: 20px 30px; background: #f8f9fa; text-align: center;">
                            <p style="margin: 0 0 10px; color: #888; font-size: 12px;">
                                Nº Agencia de Colocación: 1300000329
                            </p>
                            <p style="margin: 0; color: #2c8cbe; font-size: 14px; font-weight: 600;">
                                AVALADOS POR LA COMUNIDAD DE MADRID
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #1a5276 0%, #0d3d56 100%); padding: 25px 30px; text-align: center;">
                            <p style="margin: 0 0 5px; color: #ffffff; font-size: 18px; font-weight: 700;">
                                CUIDADO DE MAYORES
                            </p>
                            <p style="margin: 0 0 15px; color: rgba(255,255,255,0.8); font-size: 13px;">
                                Un servicio profesional de CALIDAD y TRATO HUMANO.<br>
                                Comprometidos en encontrar la MEJOR SOLUCIÓN, sin compromiso
                            </p>
                            <p style="margin: 0;">
                                <a href="tel:+34911336833" style="color: #ffffff; text-decoration: none; font-size: 20px; font-weight: 600;">
                                    📞 911 33 68 33
                                </a>
                            </p>
                            <p style="margin: 10px 0 0; color: rgba(255,255,255,0.7); font-size: 13px;">
                                Cuidándote Servicios Auxiliares
                            </p>
                        </td>
                    </tr>
                    
                </table>
                
            </td>
        </tr>
    </table>
    
</body>
</html>
        <?php
        return ob_get_clean();
    }
}
