<?php
/**
 * Plugin Name: Cuidándote Presupuestos
 * Plugin URI: https://cuidandoteserviciosauxiliares.com
 * Description: Sistema de presupuestos automáticos para servicios de cuidadores. Recibe datos del formulario Nuxt, calcula presupuestos y envía emails profesionales.
 * Version: 2.0.0
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
define('CDP_VERSION', '2.0.0');
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
        // Activación/Desactivación
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Inicializar componentes
        add_action('init', array($this, 'init'));
        add_action('rest_api_init', array('CDP_API', 'register_routes'));
        
        // Estilos y scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        
        // Admin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // CORS para API
        add_action('rest_api_init', array($this, 'add_cors_headers'), 15);
    }
    
    /**
     * Inicialización
     */
    public function init() {
        // Inicializar shortcodes
        new CDP_Shortcodes();
        
        // Crear página de presupuesto si no existe
        $this->create_budget_page();
    }
    
    /**
     * Activación del plugin
     */
    public function activate() {
        // Crear tablas en la base de datos
        CDP_Database::create_tables();
        
        // Crear página de presupuesto
        $this->create_budget_page();
        
        // Limpiar rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Desactivación del plugin
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Crear página de presupuesto automáticamente
     */
    private function create_budget_page() {
        $page_slug = 'presupuesto-cuidadores';
        $page = get_page_by_path($page_slug);
        
        if (!$page) {
            wp_insert_post(array(
                'post_title'     => 'Tu Presupuesto Personalizado',
                'post_name'      => $page_slug,
                'post_content'   => '[cuidandote_presupuesto]',
                'post_status'    => 'publish',
                'post_type'      => 'page',
                'comment_status' => 'closed',
            ));
        }
    }
    
    /**
     * Encolar estilos
     */
    public function enqueue_styles() {
        if (is_page('presupuesto-cuidadores')) {
            wp_enqueue_style(
                'cuidandote-presupuestos',
                CDP_PLUGIN_URL . 'assets/css/styles.css',
                array(),
                CDP_VERSION
            );
        }
    }
    
    /**
     * Añadir headers CORS
     */
    public function add_cors_headers() {
        $allowed_origins = array(
            'https://cuidandote.webaliza.cat',
            'https://cuidandoteserviciosauxiliares.com',
            'http://localhost:3000',
        );
        
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        
        if (in_array($origin, $allowed_origins)) {
            header("Access-Control-Allow-Origin: $origin");
            header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            header('Access-Control-Allow-Credentials: true');
        }
        
        // Manejar preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            status_header(200);
            exit();
        }
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
     * Registrar ajustes
     */
    public function register_settings() {
        register_setting('cdp_settings', 'cdp_nuxt_url');
        register_setting('cdp_settings', 'cdp_email_from');
        register_setting('cdp_settings', 'cdp_email_from_name');
    }
    
    /**
     * Página de administración
     */
    public function admin_page() {
        $nuxt_url = get_option('cdp_nuxt_url', 'https://cuidandote.webaliza.cat');
        $email_from = get_option('cdp_email_from', 'info@cuidandoteserviciosauxiliares.com');
        $email_from_name = get_option('cdp_email_from_name', 'Cuidándote Servicios Auxiliares');
        
        // Obtener estadísticas
        global $wpdb;
        $tabla = $wpdb->prefix . 'cdp_presupuestos';
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $tabla");
        $hoy = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $tabla WHERE DATE(created_at) = %s",
            current_time('Y-m-d')
        ));
        ?>
        <div class="wrap">
            <h1>🏠 Cuidándote Presupuestos</h1>
            
            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <h2>📊 Estadísticas</h2>
                <p><strong>Total presupuestos:</strong> <?php echo esc_html($total ?: 0); ?></p>
                <p><strong>Presupuestos hoy:</strong> <?php echo esc_html($hoy ?: 0); ?></p>
            </div>
            
            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <h2>🔗 Endpoint API</h2>
                <code style="background: #f0f0f0; padding: 10px; display: block; margin: 10px 0;">
                    POST <?php echo esc_url(rest_url('cuidandote/v1/presupuesto')); ?>
                </code>
                <p class="description">Este es el endpoint donde la aplicación Nuxt debe enviar los datos del formulario.</p>
            </div>
            
            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <h2>⚙️ Configuración</h2>
                <form method="post" action="options.php">
                    <?php settings_fields('cdp_settings'); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="cdp_nuxt_url">URL App Nuxt</label></th>
                            <td>
                                <input type="url" id="cdp_nuxt_url" name="cdp_nuxt_url" 
                                       value="<?php echo esc_attr($nuxt_url); ?>" class="regular-text">
                                <p class="description">URL donde está alojado el formulario Nuxt</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cdp_email_from">Email remitente</label></th>
                            <td>
                                <input type="email" id="cdp_email_from" name="cdp_email_from" 
                                       value="<?php echo esc_attr($email_from); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cdp_email_from_name">Nombre remitente</label></th>
                            <td>
                                <input type="text" id="cdp_email_from_name" name="cdp_email_from_name" 
                                       value="<?php echo esc_attr($email_from_name); ?>" class="regular-text">
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Guardar Configuración'); ?>
                </form>
            </div>
            
            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <h2>📝 Estructura JSON esperada</h2>
                <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto; font-size: 12px;">
{
    "contacto": {
        "name": "Nombre Cliente",
        "email": "cliente@email.com",
        "phone": "612345678",
        "postalCode": "28001",
        "privacyPolicy": true
    },
    "selectedDateTime": {
        "date": "26-11-2025",
        "time": "19:56"
    },
    "selectedDays": ["LUN", "MAR", "MIE", "JUE", "VIE"],
    "selectedSchedule": [{
        "label": "Misma hora todos los días",
        "value": "same",
        "days": [{
            "day": "same",
            "slots": [{ "from": "09:00", "to": "17:00" }]
        }]
    }],
    "durationType": "larga",
    "selectedWeeks": "4"
}
                </pre>
            </div>
        </div>
        <?php
    }
}

// Inicializar plugin
function cuidandote_presupuestos() {
    return Cuidandote_Presupuestos::get_instance();
}
add_action('plugins_loaded', 'cuidandote_presupuestos');
