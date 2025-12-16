<?php
/**
 * Clase para endpoints REST API
 * 
 * Versión actualizada: Redirige a página de agradecimiento
 * en lugar de mostrar directamente el detalle del presupuesto.
 */

if (!defined('ABSPATH')) {
    exit;
}

class CDP_API {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }
    
    /**
     * Registrar rutas REST
     */
    public static function register_routes() {
        register_rest_route('cuidandote/v1', '/presupuesto', array(
            array(
                'methods'             => 'POST',
                'callback'            => array(__CLASS__, 'crear_presupuesto'),
                'permission_callback' => '__return_true',
                // La validación se hace en el callback con validar_datos()
                // No usar 'args' aquí porque el payload viene envuelto en 'data'
            ),
            array(
                'methods'             => 'OPTIONS',
                'callback'            => '__return_true',
                'permission_callback' => '__return_true',
            ),
        ));
        
        register_rest_route('cuidandote/v1', '/presupuesto/(?P<token>[a-zA-Z0-9\-]+)', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'obtener_presupuesto'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'token' => array(
                    'required'          => true,
                    'validate_callback' => function($param, $request, $key) {
                        return preg_match('/^[a-zA-Z0-9\-]+$/', $param) === 1;
                    },
                ),
            ),
        ));
        
        register_rest_route('cuidandote/v1', '/health', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'health_check'),
            'permission_callback' => '__return_true',
        ));
    }
    
    /**
     * Crear nuevo presupuesto
     */
    public static function crear_presupuesto($request) {
        // Obtener datos del cuerpo de la petición
        $body = $request->get_body();
        $data = json_decode($body, true);
        
        // Si viene envuelto en 'data', extraerlo
        if (isset($data['data'])) {
            $data = $data['data'];
        }
        
        // Validar datos mínimos
        $validacion = self::validar_datos($data);
        if (is_wp_error($validacion)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error'   => $validacion->get_error_message(),
            ), 400);
        }
        
        try {
            // Calcular presupuesto
            $calculator = new CDP_Calculator($data);
            $datos_presupuesto = $calculator->calcular();
            
            // Guardar en base de datos
            $presupuesto_id = CDP_Database::guardar_presupuesto($datos_presupuesto);
            
            if (!$presupuesto_id) {
                throw new Exception('Error al guardar el presupuesto en la base de datos');
            }
            
            // Obtener presupuesto guardado para enviar email
            $presupuesto = CDP_Database::get_presupuesto_por_token($datos_presupuesto['token']);
            
            // Enviar email
            $mailer = new CDP_Mailer($presupuesto);
            $email_enviado = $mailer->enviar_propuesta();
            
            // =====================================================
            // CAMBIO PRINCIPAL: Redirigir a página de agradecimiento
            // en lugar de la página de detalle del presupuesto
            // =====================================================
            $redirect_url = self::get_redirect_url_gracias();
            
            // Respuesta exitosa
            return new WP_REST_Response(array(
                'success'       => true,
                'message'       => 'Presupuesto creado correctamente',
                'token'         => $datos_presupuesto['token'],
                'redirect_url'  => $redirect_url,
                'email_enviado' => $email_enviado,
                'presupuesto'   => array(
                    'tipo_servicio'  => $datos_presupuesto['tipo_servicio_label'],
                    'pago_mensual'   => $datos_presupuesto['pago_mensual'],
                    'horas_semanales'=> $datos_presupuesto['horas_semanales'],
                ),
            ), 201);
            
        } catch (Exception $e) {
            error_log('CDP Error: ' . $e->getMessage());
            
            return new WP_REST_Response(array(
                'success' => false,
                'error'   => 'Error al procesar el presupuesto: ' . $e->getMessage(),
            ), 500);
        }
    }
    
    /**
     * Obtener presupuesto por token
     */
    public static function obtener_presupuesto($request) {
        $token = $request->get_param('token');
        
        $presupuesto = CDP_Database::get_presupuesto_por_token($token);
        
        if (!$presupuesto) {
            return new WP_REST_Response(array(
                'success' => false,
                'error'   => 'Presupuesto no encontrado o expirado',
            ), 404);
        }
        
        return new WP_REST_Response(array(
            'success'     => true,
            'presupuesto' => $presupuesto,
        ), 200);
    }
    
    /**
     * Health check
     */
    public static function health_check() {
        global $wpdb;
        $tabla = $wpdb->prefix . 'cdp_presupuestos';
        $existe = $wpdb->get_var("SHOW TABLES LIKE '$tabla'");
        
        return new WP_REST_Response(array(
            'status'    => 'ok',
            'version'   => CDP_VERSION,
            'database'  => $existe ? 'connected' : 'error',
            'timestamp' => current_time('c'),
        ), 200);
    }
    
    /**
     * Validar datos del formulario
     */
    private static function validar_datos($data) {
        // Verificar datos de contacto
        $contacto = $data['contacto'] ?? array();
        
        if (empty($contacto['name'])) {
            return new WP_Error('missing_name', 'El nombre es obligatorio');
        }
        
        if (empty($contacto['email']) || !is_email($contacto['email'])) {
            return new WP_Error('invalid_email', 'El email no es válido');
        }
        
        if (empty($contacto['phone'])) {
            return new WP_Error('missing_phone', 'El teléfono es obligatorio');
        }
        
        // Verificar días seleccionados
        if (empty($data['selectedDays']) || !is_array($data['selectedDays'])) {
            return new WP_Error('missing_days', 'Debes seleccionar al menos un día');
        }
        
        // Verificar horario
        if (empty($data['selectedSchedule']) || !is_array($data['selectedSchedule'])) {
            return new WP_Error('missing_schedule', 'Debes indicar el horario');
        }
        
        // Verificar política de privacidad
        if (empty($contacto['privacyPolicy'])) {
            return new WP_Error('privacy_not_accepted', 'Debes aceptar la política de privacidad');
        }
        
        return true;
    }
    
    /**
     * NUEVA FUNCIÓN: Obtener URL de la página de agradecimiento
     * 
     * Redirige a /presupuesto-solicitado/ en lugar de mostrar 
     * directamente el detalle del presupuesto
     */
    private static function get_redirect_url_gracias() {
        $page = get_page_by_path('presupuesto-solicitado');
        
        if ($page) {
            return get_permalink($page->ID);
        }
        
        // Fallback si la página no existe
        return home_url('/presupuesto-solicitado/');
    }
    
    /**
     * FUNCIÓN ORIGINAL (mantenida por compatibilidad)
     * Obtener URL de redirección al detalle del presupuesto
     * 
     * Esta función la usa el enlace del email para llevar
     * al usuario a la página de detalle con su token.
     */
    public static function get_redirect_url_detalle($token) {
        $page = get_page_by_path('presupuesto-cuidadores');
        
        if ($page) {
            return add_query_arg('token', $token, get_permalink($page->ID));
        }
        
        return home_url('/presupuesto-cuidadores/?token=' . $token);
    }
}
