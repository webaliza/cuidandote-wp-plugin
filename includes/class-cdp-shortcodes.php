<?php
/**
 * Clase para shortcodes
 * 
 * Renderiza el presupuesto detallado y la página de agradecimiento
 */

if (!defined('ABSPATH')) {
    exit;
}

class CDP_Shortcodes {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('cuidandote_presupuesto', array($this, 'render_presupuesto'));
        add_shortcode('cuidandote_presupuesto_solicitado', array($this, 'render_presupuesto_solicitado'));
        add_shortcode('cuidandote_formulario', array($this, 'render_formulario'));
    }
    
    /**
     * Renderizar presupuesto detallado
     */
    public function render_presupuesto($atts) {
        $atts = shortcode_atts(array(
            'class' => '',
        ), $atts);
        
        // Obtener token de la URL
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : null;
        
        if (!$token) {
            return $this->render_error('No se encontró el token del presupuesto.');
        }
        
        // Obtener presupuesto
        $presupuesto = CDP_Database::get_presupuesto_por_token($token);
        
        if (!$presupuesto) {
            return $this->render_error('El presupuesto no existe o ha expirado.');
        }
        
        // Verificar expiración
        if (strtotime($presupuesto->token_expira_at) < time()) {
            return $this->render_error('Este enlace ha expirado. Por favor, solicita un nuevo presupuesto.');
        }
        
        // Marcar como usado
        CDP_Database::marcar_token_usado($token);
        
        return $this->render_desglose($presupuesto, $atts['class']);
    }
    
    /**
     * Renderizar mensaje de error
     */
    private function render_error($mensaje) {
        return '<div class="cdp-error" style="max-width: 600px; margin: 40px auto; padding: 30px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 12px; text-align: center;">
            <h3 style="color: #856404; margin: 0 0 15px;">⚠️ Aviso</h3>
            <p style="color: #856404; margin: 0;">' . esc_html($mensaje) . '</p>
            <p style="margin: 20px 0 0;"><a href="' . esc_url(home_url()) . '" style="color: #667eea;">Volver al inicio</a></p>
        </div>';
    }
    
    /**
     * Formatear moneda
     */
    private function formatear_moneda($valor) {
        return number_format((float) $valor, 2, ',', '.') . '€';
    }
    
    /**
     * Renderizar desglose del presupuesto
     */
    private function render_desglose($p, $class) {
        ob_start();
        ?>
        <style>
            .cdp-desglose { max-width: 700px; margin: 40px auto; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
            .cdp-desglose-header { background: linear-gradient(135deg, #2c8cbe 0%, #1a5276 100%); color: white; padding: 30px; border-radius: 12px 12px 0 0; text-align: center; }
            .cdp-desglose-header h1 { margin: 0; font-size: 28px; }
            .cdp-desglose-body { background: white; padding: 30px; border-radius: 0 0 12px 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            .cdp-servicio-box { background: #f8f9fa; border-left: 4px solid #2c8cbe; padding: 15px 20px; margin-bottom: 25px; border-radius: 0 8px 8px 0; }
            .cdp-servicio-box h3 { margin: 0 0 5px; color: #2c8cbe; font-size: 14px; text-transform: uppercase; }
            .cdp-servicio-box p { margin: 0; font-size: 18px; color: #333; font-weight: 600; }
            .cdp-section-title { color: #2c8cbe; font-size: 18px; font-weight: 600; margin: 25px 0 15px; padding-bottom: 10px; border-bottom: 2px solid #e0e0e0; }
            .cdp-item-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
            .cdp-item-row:last-child { border-bottom: none; }
            .cdp-item-label { color: #555; }
            .cdp-item-value { font-weight: 600; color: #333; }
            .cdp-total-box { background: linear-gradient(135deg, #2c8cbe 0%, #1a5276 100%); color: white; padding: 20px; border-radius: 8px; margin: 25px 0; display: flex; justify-content: space-between; align-items: center; }
            .cdp-total-label { font-size: 16px; }
            .cdp-total-value { font-size: 28px; font-weight: 700; }
            .cdp-cuota-info { background: #e8f4f8; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .cdp-cuota-info h4 { color: #2c8cbe; margin: 0 0 15px; }
            .cdp-cuota-info ul { margin: 0; padding-left: 20px; color: #555; }
            .cdp-cuota-info li { margin-bottom: 8px; }
            .cdp-comision-box { background: #fff3cd; border: 1px solid #ffc107; padding: 15px 20px; border-radius: 8px; margin: 20px 0; }
            .cdp-comision-box h4 { color: #856404; margin: 0 0 10px; }
            .cdp-comision-box p { margin: 0; color: #856404; }
            .cdp-cta-box { text-align: center; padding: 30px 0; }
            .cdp-cta-button { display: inline-block; background: linear-gradient(135deg, #27ae60 0%, #1e8449 100%); color: white; padding: 15px 40px; border-radius: 30px; text-decoration: none; font-size: 16px; font-weight: 600; }
            .cdp-cta-button:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(39, 174, 96, 0.4); }
            .cdp-nota { font-size: 13px; color: #888; margin-top: 10px; }
            @media (max-width: 600px) {
                .cdp-desglose { margin: 20px 15px; }
                .cdp-desglose-header, .cdp-desglose-body { padding: 20px; }
                .cdp-total-box { flex-direction: column; text-align: center; gap: 10px; }
            }
        </style>
        
        <div class="cdp-desglose <?php echo esc_attr($class); ?>">
            <div class="cdp-desglose-header">
                <h1>DETALLE PRESUPUESTO</h1>
            </div>
            
            <div class="cdp-desglose-body">
                <!-- Servicio -->
                <div class="cdp-servicio-box">
                    <h3>Servicio solicitado</h3>
                    <p><?php echo esc_html($p->tipo_servicio_label); ?></p>
                </div>
                
                <p class="cdp-nota">La contratación se realiza por cuenta del cliente</p>
                
                <!-- Retribución -->
                <h3 class="cdp-section-title">RETRIBUCIÓN CUIDADOR/A</h3>
                
                <div class="cdp-item-row">
                    <span class="cdp-item-label">Salario Neto mensual</span>
                    <span class="cdp-item-value"><?php echo $this->formatear_moneda($p->salario_neto); ?></span>
                </div>
                
                <div class="cdp-item-row">
                    <span class="cdp-item-label">Cotización a la Seguridad Social</span>
                    <span class="cdp-item-value"><?php echo $this->formatear_moneda($p->cotizacion_ss); ?></span>
                </div>
                
                <!-- Servicio de Asistencia -->
                <h3 class="cdp-section-title">Servicio de Asistencia</h3>
                
                <div class="cdp-item-row">
                    <span class="cdp-item-label">Cuota CUIDÁNDOTE (IVA inc.)</span>
                    <span class="cdp-item-value"><?php echo $this->formatear_moneda($p->cuota_cuidandote_iva); ?></span>
                </div>
                
                <!-- Info cuota -->
                <div class="cdp-cuota-info">
                    <h4>La cuota CUIDÁNDOTE supone:</h4>
                    <ul>
                        <li>Atención personalizada y seguimiento del servicio con cada familia.</li>
                        <li>Preparación de contratos.</li>
                        <li>Alta/Baja en la Seguridad Social.</li>
                        <li>Nómina Mensual.</li>
                        <li>Suplencia en bajas y vacaciones de la empleada/o.</li>
                        <li>Seguro Responsabilidad Civil.</li>
                        <li>Mediación laboral entre las partes.</li>
                        <li>Asesoramiento legal sobre las empleadas de hogar (consultivo).</li>
                        <li>Descuentos exclusivos en la contratación de cualquier servicio adicional ofrecido.</li>
                    </ul>
                </div>
                
                <!-- Total -->
                <div class="cdp-total-box">
                    <span class="cdp-total-label">PAGO MENSUAL:</span>
                    <span class="cdp-total-value"><?php echo $this->formatear_moneda($p->pago_mensual); ?></span>
                </div>
                
                <p class="cdp-nota">*Una vez aprobado el presupuesto, se le enviará el documento online de "Protección de Datos" para su correspondiente aceptación, y tras ello, recibirá la "Carta de condiciones y garantía" para su firma.</p>
                
                <!-- Comisión -->
                <div class="cdp-comision-box">
                    <h4>PAGO ÚNICO – COMISIÓN DE LA AGENCIA (proceso selección):</h4>
                    <p style="font-size: 24px; font-weight: 700;"><?php echo $this->formatear_moneda($p->comision_agencia_iva); ?></p>
                    <p class="cdp-nota">*No aplicamos tarifas adicionales por procesos acelerados de selección.</p>
                </div>
                
                <!-- CTA -->
                <div class="cdp-cta-box">
                    <a href="tel:+34911336833" class="cdp-cta-button">
                        📞 CONTRATAR - 911 33 68 33
                    </a>
                </div>
                
                <!-- Info cliente -->
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 20px;">
                    <p style="margin: 0; color: #666; font-size: 14px;">
                        <strong>Cliente:</strong> <?php echo esc_html($p->nombre); ?><br>
                        <strong>Teléfono:</strong> <?php echo esc_html($p->telefono); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Renderizar página de presupuesto solicitado (agradecimiento)
     */
    public function render_presupuesto_solicitado($atts) {
        $atts = shortcode_atts(array(
            'class' => '',
        ), $atts);
        
        ob_start();
        ?>
        <style>
            .cdp-gracias-container { max-width: 1024px; margin: 40px auto; padding: 40px; text-align: center; }
            .cdp-gracias-icon { width: 80px; height: 80px; background: linear-gradient(135deg, #0B8547 0%, #256D9B 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 30px; }
            .cdp-gracias-icon svg { width: 40px; height: 40px; fill: white; }
            .cdp-gracias-title { font-size: 28px; font-weight: 700; color: #1a1a2e; margin-bottom: 15px; }
            .cdp-gracias-subtitle { font-size: 18px; color: #555; margin-bottom: 30px; line-height: 1.6; }
            .cdp-gracias-card { background: #f8f9fa; border-radius: 12px; padding: 30px; margin: 30px 0; border-left: 4px solid #046CA5; text-align: left; }
            .cdp-gracias-card-title { font-size: 16px; font-weight: 600; color: #046CA5; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
            .cdp-gracias-card-title svg { width: 24px; height: 24px; fill: #046CA5; }
            .cdp-gracias-card p { color: #444; line-height: 1.7; margin: 0; }
            .cdp-gracias-steps { text-align: left; margin: 30px 0; }
            .cdp-gracias-step { display: flex; align-items: flex-start; gap: 15px; margin-bottom: 20px; }
            .cdp-gracias-step-number { width: 32px; height: 32px; background: #046CA5; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; flex-shrink: 0; }
            .cdp-gracias-step-text { color: #333; line-height: 1.6; padding-top: 4px; }
            .cdp-gracias-step-text strong { color: #1a1a2e; }
            .cdp-gracias-contact { background: linear-gradient(135deg, #0B8547 0%, #256D9B 100%); color: white; padding: 25px 30px; border-radius: 12px; margin-top: 30px; }
            .cdp-gracias-contact p { margin: 0 0 15px; font-size: 15px; }
            .cdp-gracias-contact a { color: white; font-weight: 600; text-decoration: none; font-size: 20px; display: inline-flex; align-items: center; gap: 8px; }
            .cdp-gracias-contact a:hover { text-decoration: underline; }
            .cdp-gracias-spam-tip { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 15px 20px; margin: 20px 0; display: flex; align-items: flex-start; gap: 12px; text-align: left; }
            .cdp-gracias-spam-tip svg { width: 24px; height: 24px; fill: #856404; flex-shrink: 0; margin-top: 2px; }
            .cdp-gracias-spam-tip p { margin: 0; color: #856404; font-size: 14px; line-height: 1.5; }
            @media (max-width: 600px) {
                .cdp-gracias-container { padding: 25px 20px; margin: 20px auto; }
                .cdp-gracias-title { font-size: 24px; }
                .cdp-gracias-subtitle { font-size: 16px; }
                .cdp-gracias-card { padding: 20px; }
            }
        </style>
        
        <div class="cdp-gracias-container <?php echo esc_attr($atts['class']); ?>">
            <!-- Icono de éxito -->
            <div class="cdp-gracias-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                </svg>
            </div>
            
            <!-- Título -->
            <h1 class="cdp-gracias-title">¡Gracias por tu interés!</h1>
            
            <!-- Subtítulo -->
            <p class="cdp-gracias-subtitle">
                Hemos recibido tu solicitud de presupuesto correctamente.
            </p>
            
            <!-- Card con instrucciones del email -->
            <div class="cdp-gracias-card">
                <div class="cdp-gracias-card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                    </svg>
                    Revisa tu correo electrónico
                </div>
                <p>
                    Te hemos enviado un email con el <strong>detalle completo de tu presupuesto personalizado</strong>. 
                    En él encontrarás el desglose de costes, los servicios incluidos y un enlace para ver toda la información.
                </p>
            </div>
            
            <!-- Aviso de spam -->
            <div class="cdp-gracias-spam-tip">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>
                </svg>
                <p>
                    Si no ves el email en tu bandeja de entrada, <strong>revisa la carpeta de spam o correo no deseado</strong>. 
                    El remitente es <em>info@cuidandoteserviciosauxiliares.com</em>.
                </p>
            </div>
            
            <!-- Pasos siguientes -->
            <div class="cdp-gracias-steps">
                <h3 style="font-size: 18px; color: #1a1a2e; margin-bottom: 20px;">¿Qué sucede ahora?</h3>
                
                <div class="cdp-gracias-step">
                    <div class="cdp-gracias-step-number">1</div>
                    <div class="cdp-gracias-step-text">
                        <strong>Revisa tu presupuesto</strong> haciendo clic en el botón "Detalle Presupuesto" del email.
                    </div>
                </div>
                
                <div class="cdp-gracias-step">
                    <div class="cdp-gracias-step-number">2</div>
                    <div class="cdp-gracias-step-text">
                        Si lo has solicitado, <strong>un consultor te contactará</strong> para resolver cualquier duda y ayudarte a encontrar la mejor solución.
                    </div>
                </div>
                
                <div class="cdp-gracias-step">
                    <div class="cdp-gracias-step-number">3</div>
                    <div class="cdp-gracias-step-text">
                        Sin compromiso. Estamos aquí para <strong>asesorarte según tus necesidades</strong>.
                    </div>
                </div>
            </div>
            
            <!-- Contacto -->
            <div class="cdp-gracias-contact">
                <p>¿Tienes alguna pregunta urgente? Llámanos directamente:</p>
                <a href="tel:+34911336833">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
                        <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                    </svg>
                    911 33 68 33
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Renderizar iframe del formulario Nuxt
     */
    public function render_formulario($atts) {
        $atts = shortcode_atts(array(
            'url' => get_option('cdp_nuxt_url', 'https://cuidandote.webaliza.cat'),
            'height' => '800px',
        ), $atts);
        
        return '<iframe src="' . esc_url($atts['url']) . '" 
                        style="width: 100%; height: ' . esc_attr($atts['height']) . '; border: none;" 
                        title="Formulario de solicitud de presupuesto"></iframe>';
    }
}
