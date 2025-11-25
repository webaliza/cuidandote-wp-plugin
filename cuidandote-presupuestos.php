<?php
/**
 * Plugin Name: Cuidándote - Presupuestos
 * Plugin URI: https://cuidandoteserviciosauxiliares.com/
 * Description: Recibe datos del formulario de presupuestos desde la aplicación Nuxt y los muestra en una página dedicada.
 * Version: 1.0.0
 * Author: Cuidándote Servicios Auxiliares
 * License: GPL v2 or later
 * Text Domain: cuidandote-presupuestos
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CDP_VERSION', '1.0.0');
define('CDP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CDP_PLUGIN_URL', plugin_dir_url(__FILE__));

class Cuidandote_Presupuestos {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('init', [$this, 'register_shortcodes']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // Crear página al activar el plugin
        register_activation_hook(__FILE__, [$this, 'activate_plugin']);
    }
    
    /**
     * Activación del plugin - crear página de presupuesto
     */
    public function activate_plugin() {
        // Crear página para mostrar presupuesto si no existe
        $page_exists = get_page_by_path('presupuesto-cuidadores');
        
        if (!$page_exists) {
            wp_insert_post([
                'post_title'     => 'Tu Presupuesto',
                'post_name'      => 'presupuesto-cuidadores',
                'post_content'   => '[cuidandote_presupuesto]',
                'post_status'    => 'publish',
                'post_type'      => 'page',
                'comment_status' => 'closed'
            ]);
        }
        
        flush_rewrite_rules();
    }
    
    /**
     * Registrar rutas REST API
     */
    public function register_rest_routes() {
        register_rest_route('cuidandote/v1', '/presupuesto', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_presupuesto_submission'],
            'permission_callback' => '__return_true',
            'args'                => [
                'data' => [
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return is_array($param) || is_object($param);
                    }
                ]
            ]
        ]);
        
        // Endpoint para verificar datos almacenados
        register_rest_route('cuidandote/v1', '/presupuesto/(?P<token>[a-zA-Z0-9]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'check_presupuesto'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    /**
     * Manejar envío del presupuesto
     */
    public function handle_presupuesto_submission($request) {
        $data = $request->get_param('data');
        
        // Sanitizar datos recursivamente
        $sanitized_data = $this->sanitize_data($data);
        
        // Generar token único para este presupuesto
        $token = wp_generate_password(32, false);
        
        // Almacenar en transient (expira en 24 horas para presupuestos)
        $stored = set_transient(
            'cdp_presupuesto_' . $token, 
            $sanitized_data, 
            DAY_IN_SECONDS
        );
        
        if (!$stored) {
            return new WP_Error(
                'storage_error',
                'No se pudieron almacenar los datos del presupuesto',
                ['status' => 500]
            );
        }
        
        // También guardar en sesión para usuarios no logueados
        if (!session_id()) {
            session_start();
        }
        $_SESSION['cdp_current_token'] = $token;
        
        // URL de redirección
        $results_page = get_page_by_path('presupuesto-cuidadores');
        $redirect_url = $results_page 
            ? add_query_arg('token', $token, get_permalink($results_page->ID))
            : add_query_arg('token', $token, home_url('/presupuesto-cuidadores/'));
        
        // Log opcional para debug
        if (WP_DEBUG) {
            error_log('CDP: Presupuesto recibido con token ' . $token);
        }
        
        return rest_ensure_response([
            'success'      => true,
            'token'        => $token,
            'redirect_url' => $redirect_url,
            'message'      => 'Datos del presupuesto recibidos correctamente'
        ]);
    }
    
    /**
     * Verificar presupuesto existente
     */
    public function check_presupuesto($request) {
        $token = $request->get_param('token');
        $data = get_transient('cdp_presupuesto_' . $token);
        
        return rest_ensure_response([
            'exists' => $data !== false,
            'token'  => $token
        ]);
    }
    
    /**
     * Sanitizar datos recursivamente
     */
    private function sanitize_data($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitize_data'], $data);
        }
        
        if (is_object($data)) {
            $sanitized = new stdClass();
            foreach ($data as $key => $value) {
                $clean_key = sanitize_key($key);
                $sanitized->$clean_key = $this->sanitize_data($value);
            }
            return $sanitized;
        }
        
        if (is_string($data)) {
            if ($data !== strip_tags($data)) {
                return wp_kses_post($data);
            }
            return sanitize_text_field($data);
        }
        
        if (is_numeric($data)) {
            return $data;
        }
        
        if (is_bool($data)) {
            return $data;
        }
        
        return '';
    }
    
    /**
     * Registrar shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('cuidandote_presupuesto', [$this, 'shortcode_display_presupuesto']);
        add_shortcode('cuidandote_formulario', [$this, 'shortcode_iframe']);
    }
    
    /**
     * Shortcode para mostrar datos del presupuesto
     */
    public function shortcode_display_presupuesto($atts) {
        $atts = shortcode_atts([
            'template' => 'default',
            'class'    => 'cdp-presupuesto'
        ], $atts);
        
        // Obtener token de la URL o sesión
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : null;
        
        if (!$token && !session_id()) {
            session_start();
        }
        
        if (!$token && isset($_SESSION['cdp_current_token'])) {
            $token = $_SESSION['cdp_current_token'];
        }
        
        if (!$token) {
            return $this->render_message(
                'No se encontraron datos del presupuesto. Por favor, complete el formulario de solicitud.', 
                'warning'
            );
        }
        
        // Recuperar datos
        $data = get_transient('cdp_presupuesto_' . $token);
        
        if ($data === false) {
            return $this->render_message(
                'Los datos del presupuesto han expirado. Por favor, solicite un nuevo presupuesto.', 
                'error'
            );
        }
        
        // Renderizar presupuesto
        ob_start();
        ?>
        <div class="<?php echo esc_attr($atts['class']); ?>">
            <div class="cdp-presupuesto-header">
                <div class="cdp-logo-section">
                    <?php if (has_custom_logo()): ?>
                        <?php the_custom_logo(); ?>
                    <?php endif; ?>
                </div>
                <h2><?php _e('Resumen de tu Solicitud de Presupuesto', 'cuidandote-presupuestos'); ?></h2>
                <p class="cdp-subtitle">
                    <?php _e('Servicio de Cuidadores de Personas Mayores', 'cuidandote-presupuestos'); ?>
                </p>
                <p class="cdp-timestamp">
                    <?php printf(__('Fecha de solicitud: %s', 'cuidandote-presupuestos'), current_time('d/m/Y H:i')); ?>
                </p>
                <p class="cdp-reference">
                    <?php printf(__('Referencia: %s', 'cuidandote-presupuestos'), strtoupper(substr($token, 0, 8))); ?>
                </p>
            </div>
            
            <div class="cdp-presupuesto-content">
                <?php echo $this->render_data_table($data); ?>
            </div>
            
            <div class="cdp-presupuesto-footer">
                <div class="cdp-next-steps">
                    <h3><?php _e('Próximos pasos', 'cuidandote-presupuestos'); ?></h3>
                    <p><?php _e('Nuestro equipo revisará su solicitud y se pondrá en contacto con usted en las próximas 24-48 horas para ofrecerle un presupuesto personalizado.', 'cuidandote-presupuestos'); ?></p>
                </div>
                
                <div class="cdp-contact-info">
                    <p><?php _e('¿Tiene alguna pregunta? Contáctenos:', 'cuidandote-presupuestos'); ?></p>
                    <p>
                        <strong><?php _e('Cuidándote Servicios Auxiliares', 'cuidandote-presupuestos'); ?></strong><br>
                        <a href="https://cuidandoteserviciosauxiliares.com">cuidandoteserviciosauxiliares.com</a>
                    </p>
                </div>
            </div>
            
            <?php 
            // Hook para añadir contenido adicional (cálculos de precio, etc.)
            do_action('cuidandote_after_presupuesto', $data, $token); 
            ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode para insertar el iframe del formulario Nuxt
     */
    public function shortcode_iframe($atts) {
        $atts = shortcode_atts([
            'src'    => '',
            'width'  => '100%',
            'height' => '800px',
            'class'  => 'cdp-formulario-container'
        ], $atts);
        
        if (empty($atts['src'])) {
            return $this->render_message('URL del formulario no configurada.', 'error');
        }
        
        $iframe_id = 'cdp-formulario-' . wp_generate_password(8, false, false);
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($atts['class']); ?>">
            <iframe 
                id="<?php echo esc_attr($iframe_id); ?>"
                src="<?php echo esc_url($atts['src']); ?>"
                width="<?php echo esc_attr($atts['width']); ?>"
                height="<?php echo esc_attr($atts['height']); ?>"
                frameborder="0"
                allow="clipboard-write"
                class="cdp-formulario-iframe"
            ></iframe>
        </div>
        
        <script>
        (function() {
            window.addEventListener('message', function(event) {
                // Verificar origen - descomentar en producción
                // if (event.origin !== 'https://url-de-tu-app-nuxt.com') return;
                
                if (event.data && event.data.type === 'cdp_close_iframe') {
                    var iframe = document.getElementById('<?php echo esc_js($iframe_id); ?>');
                    if (iframe) {
                        iframe.style.display = 'none';
                    }
                    
                    if (event.data.redirect_url) {
                        window.location.href = event.data.redirect_url;
                    }
                }
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Renderizar tabla de datos del presupuesto
     */
    private function render_data_table($data, $level = 0) {
        if (empty($data)) {
            return '<p>' . __('No hay datos disponibles.', 'cuidandote-presupuestos') . '</p>';
        }
        
        $html = '<table class="cdp-data-table cdp-level-' . $level . '">';
        $html .= '<tbody>';
        
        foreach ($data as $key => $value) {
            $label = $this->format_label($key);
            
            $html .= '<tr>';
            $html .= '<th scope="row">' . esc_html($label) . '</th>';
            $html .= '<td>';
            
            if (is_array($value) || is_object($value)) {
                $html .= $this->render_data_table((array)$value, $level + 1);
            } elseif (is_bool($value)) {
                $html .= $value ? __('Sí', 'cuidandote-presupuestos') : __('No', 'cuidandote-presupuestos');
            } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
                $html .= '<a href="' . esc_url($value) . '" target="_blank">' . esc_html($value) . '</a>';
            } elseif (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $html .= '<a href="mailto:' . esc_attr($value) . '">' . esc_html($value) . '</a>';
            } else {
                $html .= nl2br(esc_html($value));
            }
            
            $html .= '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        
        return $html;
    }
    
    /**
     * Formatear etiqueta de campo
     */
    private function format_label($key) {
        // Mapeo de campos comunes para presupuestos de cuidadores
        $labels = [
            'nombre'              => 'Nombre',
            'apellidos'           => 'Apellidos',
            'email'               => 'Correo electrónico',
            'telefono'            => 'Teléfono',
            'direccion'           => 'Dirección',
            'ciudad'              => 'Ciudad',
            'codigo_postal'       => 'Código postal',
            'tipo_servicio'       => 'Tipo de servicio',
            'horas_diarias'       => 'Horas diarias',
            'dias_semana'         => 'Días a la semana',
            'num_cuidadores'      => 'Número de cuidadores',
            'fecha_inicio'        => 'Fecha de inicio',
            'horario'             => 'Horario preferido',
            'necesidades'         => 'Necesidades especiales',
            'movilidad'           => 'Movilidad del paciente',
            'medicacion'          => 'Medicación',
            'comentarios'         => 'Comentarios adicionales',
            'edad_paciente'       => 'Edad del paciente',
            'patologias'          => 'Patologías',
            'interno'             => 'Servicio interno',
            'externo'             => 'Servicio externo',
            'fin_de_semana'       => 'Fines de semana',
            'festivos'            => 'Festivos',
            'urgente'             => 'Solicitud urgente',
        ];
        
        if (isset($labels[$key])) {
            return $labels[$key];
        }
        
        // Fallback: convertir snake_case o camelCase a texto legible
        $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key);
        $label = str_replace(['_', '-'], ' ', $label);
        return ucfirst(strtolower($label));
    }
    
    /**
     * Renderizar mensaje
     */
    private function render_message($message, $type = 'info') {
        return sprintf(
            '<div class="cdp-message cdp-message-%s"><p>%s</p></div>',
            esc_attr($type),
            esc_html($message)
        );
    }
    
    /**
     * Cargar estilos
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'cuidandote-presupuestos-styles',
            CDP_PLUGIN_URL . 'assets/css/styles.css',
            [],
            CDP_VERSION
        );
    }
    
    /**
     * Menú de administración
     */
    public function add_admin_menu() {
        add_options_page(
            __('Cuidándote Presupuestos', 'cuidandote-presupuestos'),
            __('Presupuestos', 'cuidandote-presupuestos'),
            'manage_options',
            'cuidandote-presupuestos',
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Página de administración
     */
    public function render_admin_page() {
        $results_page = get_page_by_path('presupuesto-cuidadores');
        $endpoint_url = rest_url('cuidandote/v1/presupuesto');
        ?>
        <div class="wrap">
            <h1><?php _e('Cuidándote - Configuración de Presupuestos', 'cuidandote-presupuestos'); ?></h1>
            
            <div class="card">
                <h2><?php _e('Endpoint para la Aplicación Nuxt', 'cuidandote-presupuestos'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th><?php _e('URL del Endpoint', 'cuidandote-presupuestos'); ?></th>
                        <td>
                            <code id="endpoint-url"><?php echo esc_url($endpoint_url); ?></code>
                            <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js($endpoint_url); ?>').then(() => alert('¡Copiado!'))">
                                <?php _e('Copiar', 'cuidandote-presupuestos'); ?>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Método HTTP', 'cuidandote-presupuestos'); ?></th>
                        <td><code>POST</code></td>
                    </tr>
                    <tr>
                        <th><?php _e('Página de Presupuesto', 'cuidandote-presupuestos'); ?></th>
                        <td>
                            <?php if ($results_page): ?>
                                <a href="<?php echo get_permalink($results_page->ID); ?>" target="_blank">
                                    <?php echo esc_html($results_page->post_title); ?>
                                </a>
                                <br>
                                <code><?php echo get_permalink($results_page->ID); ?></code>
                            <?php else: ?>
                                <span style="color: #d63638;"><?php _e('⚠️ Página no encontrada. Desactive y reactive el plugin para crearla.', 'cuidandote-presupuestos'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="card">
                <h2><?php _e('Código para la Aplicación Nuxt', 'cuidandote-presupuestos'); ?></h2>
                
                <pre style="background: #f6f7f7; padding: 15px; overflow-x: auto; border: 1px solid #ddd; border-radius: 4px;"><code>// Enviar datos del formulario de presupuesto
async function enviarPresupuesto(datosFormulario) {
  try {
    const response = await fetch('<?php echo esc_js($endpoint_url); ?>', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        data: datosFormulario
      })
    });
    
    const result = await response.json();
    
    if (result.success) {
      // Notificar a WordPress que cierre el iframe y redirija
      window.parent.postMessage({
        type: 'cdp_close_iframe',
        redirect_url: result.redirect_url
      }, 'https://cuidandoteserviciosauxiliares.com');
    }
    
    return result;
  } catch (error) {
    console.error('Error al enviar presupuesto:', error);
    throw error;
  }
}</code></pre>
            </div>
            
            <div class="card">
                <h2><?php _e('Shortcodes Disponibles', 'cuidandote-presupuestos'); ?></h2>
                
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Shortcode', 'cuidandote-presupuestos'); ?></th>
                            <th><?php _e('Descripción', 'cuidandote-presupuestos'); ?></th>
                            <th><?php _e('Ejemplo', 'cuidandote-presupuestos'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>[cuidandote_presupuesto]</code></td>
                            <td><?php _e('Muestra los datos del presupuesto recibido', 'cuidandote-presupuestos'); ?></td>
                            <td><code>[cuidandote_presupuesto class="mi-clase"]</code></td>
                        </tr>
                        <tr>
                            <td><code>[cuidandote_formulario]</code></td>
                            <td><?php _e('Inserta el iframe con el formulario Nuxt', 'cuidandote-presupuestos'); ?></td>
                            <td><code>[cuidandote_formulario src="https://app.ejemplo.com" height="900px"]</code></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="card">
                <h2><?php _e('Estructura del JSON esperado', 'cuidandote-presupuestos'); ?></h2>
                <p><?php _e('El formulario Nuxt puede enviar cualquier estructura de datos. Ejemplo:', 'cuidandote-presupuestos'); ?></p>
                
                <pre style="background: #f6f7f7; padding: 15px; overflow-x: auto; border: 1px solid #ddd; border-radius: 4px;"><code>{
  "data": {
    "nombre": "María García",
    "telefono": "612345678",
    "email": "maria@ejemplo.com",
    "tipo_servicio": "Cuidador externo",
    "horas_diarias": 8,
    "dias_semana": 5,
    "num_cuidadores": 1,
    "fecha_inicio": "2025-02-01",
    "necesidades": "Acompañamiento y ayuda con medicación",
    "comentarios": "Preferencia por cuidadora con experiencia en Alzheimer"
  }
}</code></pre>
            </div>
        </div>
        <?php
    }
}

// Inicializar plugin
Cuidandote_Presupuestos::get_instance();
