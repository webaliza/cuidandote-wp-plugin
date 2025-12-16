<?php
/**
 * Plugin Name: Cuidándote Presupuestos
 * Plugin URI: https://cuidandoteserviciosauxiliares.com
 * Description: Sistema de presupuestos automáticos para servicios de cuidadores. Recibe datos del formulario Nuxt, calcula presupuestos y envía emails profesionales.
 * Version: 2.1.0
 * Author: Webaliza
 * Author URI: https://webaliza.com
 * Text Domain: cuidandote-presupuestos
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Constantes del plugin
define('CDP_VERSION', '2.1.0');
define('CDP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CDP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CDP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Clase principal del plugin
 */
class Cuidandote_Presupuestos {
    
    /**
     * Instancia única (Singleton)
     */
    private static $instance = null;
    
    /**
     * Obtener instancia única
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Cargar dependencias
     */
    private function load_dependencies() {
        require_once CDP_PLUGIN_DIR . 'includes/class-cdp-database.php';
        require_once CDP_PLUGIN_DIR . 'includes/class-cdp-calculator.php';
        require_once CDP_PLUGIN_DIR . 'includes/class-cdp-mailer.php';
        require_once CDP_PLUGIN_DIR . 'includes/class-cdp-api.php';
        require_once CDP_PLUGIN_DIR . 'includes/class-cdp-shortcodes.php';
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Activación y desactivación
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Inicializar clases
        add_action('init', array($this, 'init_classes'));
        
        // Admin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // CORS para Nuxt
        add_action('rest_api_init', array($this, 'add_cors_headers'), 15);
        
        // Estilos
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }
    
    /**
     * Activación del plugin
     */
    public function activate() {
        // Crear tablas
        CDP_Database::create_tables();
        
        // Crear páginas
        $this->create_pages();
        
        // Limpiar permalinks
        flush_rewrite_rules();
    }
    
    /**
     * Desactivación del plugin
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Crear páginas necesarias
     */
    private function create_pages() {
        // Página de detalle del presupuesto
        if (!get_page_by_path('presupuesto-cuidadores')) {
            wp_insert_post(array(
                'post_title'     => 'Presupuesto Cuidadores',
                'post_name'      => 'presupuesto-cuidadores',
                'post_content'   => '[cuidandote_presupuesto]',
                'post_status'    => 'publish',
                'post_type'      => 'page',
                'comment_status' => 'closed',
            ));
        }
        
        // Página de agradecimiento (presupuesto solicitado)
        if (!get_page_by_path('presupuesto-solicitado')) {
            wp_insert_post(array(
                'post_title'     => 'Presupuesto Solicitado',
                'post_name'      => 'presupuesto-solicitado',
                'post_content'   => '[cuidandote_presupuesto_solicitado]',
                'post_status'    => 'publish',
                'post_type'      => 'page',
                'comment_status' => 'closed',
            ));
        }
    }
    
    /**
     * Inicializar clases
     */
    public function init_classes() {
        new CDP_API();
        new CDP_Shortcodes();
    }
    
    /**
     * Añadir menú de administración
     */
    public function add_admin_menu() {
        add_options_page(
            'Cuidándote Presupuestos',
            'Presupuestos',
            'manage_options',
            'cuidandote-presupuestos',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Registrar configuraciones
     */
    public function register_settings() {
        register_setting('cdp_settings', 'cdp_nuxt_url');
        register_setting('cdp_settings', 'cdp_email_from');
        register_setting('cdp_settings', 'cdp_email_from_name');
        register_setting('cdp_settings', 'cdp_admin_notification_email');
    }
    
    /**
     * Página de administración
     */
    public function admin_page() {
        // Procesar acciones
        if (isset($_POST['cdp_crear_tablas']) && check_admin_referer('cdp_crear_tablas_nonce')) {
            CDP_Database::create_tables();
            echo '<div class="notice notice-success"><p>✅ Tablas creadas/actualizadas correctamente.</p></div>';
        }
        
        $nuxt_url = get_option('cdp_nuxt_url', 'https://cuidandote.webaliza.cat');
        $email_from = get_option('cdp_email_from', 'info@cuidandoteserviciosauxiliares.com');
        $email_from_name = get_option('cdp_email_from_name', 'Cuidándote Servicios Auxiliares');
        $admin_notification_email = get_option('cdp_admin_notification_email', 'info@cuidandoteserviciosauxiliares.com');
        
        // Verificar estado de las tablas
        global $wpdb;
        $tabla_presupuestos = $wpdb->prefix . 'cdp_presupuestos';
        $tabla_salarial = $wpdb->prefix . 'cdp_tabla_salarial';
        $tabla_tarifas = $wpdb->prefix . 'cdp_tarifas';
        
        $existe_presupuestos = $wpdb->get_var("SHOW TABLES LIKE '$tabla_presupuestos'");
        $existe_salarial = $wpdb->get_var("SHOW TABLES LIKE '$tabla_salarial'");
        $existe_tarifas = $wpdb->get_var("SHOW TABLES LIKE '$tabla_tarifas'");
        
        $registros_salarial = $existe_salarial ? $wpdb->get_var("SELECT COUNT(*) FROM $tabla_salarial") : 0;
        $registros_tarifas = $existe_tarifas ? $wpdb->get_var("SELECT COUNT(*) FROM $tabla_tarifas") : 0;
        
        // Obtener estadísticas
        $total = $existe_presupuestos ? $wpdb->get_var("SELECT COUNT(*) FROM $tabla_presupuestos") : 0;
        $hoy = $existe_presupuestos ? $wpdb->get_var("SELECT COUNT(*) FROM $tabla_presupuestos WHERE DATE(created_at) = CURDATE()") : 0;
        
        ?>
        <div class="wrap">
            <h1>🏠 Cuidándote Presupuestos</h1>
            
            <!-- Estado de tablas -->
            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <h2>🗄️ Estado de las Tablas</h2>
                <table class="widefat" style="margin-bottom: 15px;">
                    <thead>
                        <tr>
                            <th>Tabla</th>
                            <th>Estado</th>
                            <th>Registros</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>cdp_presupuestos</code></td>
                            <td><?php echo $existe_presupuestos ? '✅ OK' : '❌ No existe'; ?></td>
                            <td><?php echo esc_html($total ?: 0); ?></td>
                        </tr>
                        <tr>
                            <td><code>cdp_tabla_salarial</code></td>
                            <td><?php echo $existe_salarial ? '✅ OK' : '❌ No existe'; ?></td>
                            <td><?php echo esc_html($registros_salarial); ?> / 40</td>
                        </tr>
                        <tr>
                            <td><code>cdp_tarifas</code></td>
                            <td><?php echo $existe_tarifas ? '✅ OK' : '❌ No existe'; ?></td>
                            <td><?php echo esc_html($registros_tarifas); ?> / 8</td>
                        </tr>
                    </tbody>
                </table>
                
                <?php if (!$existe_presupuestos || !$existe_salarial || !$existe_tarifas || $registros_salarial < 40 || $registros_tarifas < 8): ?>
                <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                    <strong>⚠️ Atención:</strong> Faltan tablas o datos. Pulsa el botón para crearlas.
                </div>
                <?php endif; ?>
                
                <form method="post">
                    <?php wp_nonce_field('cdp_crear_tablas_nonce'); ?>
                    <button type="submit" name="cdp_crear_tablas" class="button button-primary">
                        🔧 Crear / Reparar Tablas
                    </button>
                </form>
            </div>
            
            <!-- Estadísticas -->
            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <h2>📊 Estadísticas</h2>
                <p><strong>Total presupuestos:</strong> <?php echo esc_html($total ?: 0); ?></p>
                <p><strong>Presupuestos hoy:</strong> <?php echo esc_html($hoy ?: 0); ?></p>
            </div>
            
            <!-- Configuración -->
            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <h2>⚙️ Configuración</h2>
                <form method="post" action="options.php">
                    <?php settings_fields('cdp_settings'); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="cdp_nuxt_url">URL Formulario Nuxt</label></th>
                            <td>
                                <input type="url" name="cdp_nuxt_url" id="cdp_nuxt_url" 
                                       value="<?php echo esc_attr($nuxt_url); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cdp_email_from">Email remitente</label></th>
                            <td>
                                <input type="email" name="cdp_email_from" id="cdp_email_from" 
                                       value="<?php echo esc_attr($email_from); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cdp_email_from_name">Nombre remitente</label></th>
                            <td>
                                <input type="text" name="cdp_email_from_name" id="cdp_email_from_name"
                                       value="<?php echo esc_attr($email_from_name); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cdp_admin_notification_email">Email para Notificaciones</label></th>
                            <td>
                                <input type="email" name="cdp_admin_notification_email" id="cdp_admin_notification_email"
                                       value="<?php echo esc_attr($admin_notification_email); ?>" class="regular-text">
                                <p class="description">Email donde se recibirán las notificaciones de nuevos presupuestos solicitados.</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Guardar Configuración'); ?>
                </form>
            </div>
            
            <!-- Endpoint -->
            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <h2>🔌 Endpoint API</h2>
                <p><code><?php echo esc_url(rest_url('cuidandote/v1/presupuesto')); ?></code></p>
                <p><small>Método: POST | Content-Type: application/json</small></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Añadir headers CORS
     */
    public function add_cors_headers() {
        $nuxt_url = get_option('cdp_nuxt_url', 'https://cuidandote.webaliza.cat');
        
        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
        
        add_filter('rest_pre_serve_request', function($value) use ($nuxt_url) {
            $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
            
            $allowed_origins = array(
                $nuxt_url,
                'https://cuidandote.webaliza.cat',
                'http://localhost:3000',
            );
            
            if (in_array($origin, $allowed_origins)) {
                header("Access-Control-Allow-Origin: $origin");
                header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, Authorization');
                header('Access-Control-Allow-Credentials: true');
            }
            
            return $value;
        });
    }
    
    /**
     * Cargar estilos
     */
    public function enqueue_styles() {
        if (is_page(array('presupuesto-cuidadores', 'presupuesto-solicitado'))) {
            wp_enqueue_style(
                'cdp-styles',
                CDP_PLUGIN_URL . 'assets/css/styles.css',
                array(),
                CDP_VERSION
            );
        }
    }
}

// Inicializar plugin
add_action('plugins_loaded', array('Cuidandote_Presupuestos', 'get_instance'));

// Cargar sistema de notificación a administradores
require_once CDP_PLUGIN_DIR . 'includes/admin-notification/loader.php';
require_once CDP_PLUGIN_DIR . 'includes/admin-notification/class-cdp-admin-notification-migration.php';
