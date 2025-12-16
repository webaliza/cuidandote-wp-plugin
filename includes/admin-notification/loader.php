<?php
/**
 * Loader para el sistema de notificación a administradores
 * 
 * Incluir este archivo en el plugin principal:
 * require_once CDP_PLUGIN_DIR . 'includes/admin-notification/loader.php';
 */

if (!defined('ABSPATH')) {
    exit;
}

// Cargar la clase de notificación
require_once __DIR__ . '/class-cdp-admin-notification.php';

/**
 * Hook para inicializar después de que WordPress esté listo
 */
add_action('plugins_loaded', 'cdp_init_admin_notification');

function cdp_init_admin_notification() {
    // La clase ya se auto-inicializa en su constructor
    // pero podemos añadir configuración adicional aquí si es necesario
    
    // Registrar configuración en el panel de admin (opcional)
    add_action('admin_init', 'cdp_register_admin_notification_settings');
}

/**
 * Registrar configuración en el panel de WordPress
 */
function cdp_register_admin_notification_settings() {
    register_setting(
        'cuidandote_settings',
        'cdp_admin_notification_email',
        array(
            'type' => 'string',
            'description' => 'Email para notificaciones de administrador',
            'sanitize_callback' => 'sanitize_email',
            'default' => 'info@cuidandoteserviciosauxiliares.com'
        )
    );
}

/**
 * Añadir campo en la página de configuración del plugin
 * (Añadir esto a tu página de settings existente)
 */
add_action('cdp_settings_page_fields', 'cdp_add_admin_email_field');

function cdp_add_admin_email_field() {
    $email = get_option('cdp_admin_notification_email', 'info@cuidandoteserviciosauxiliares.com');
    ?>
    <tr>
        <th scope="row">
            <label for="cdp_admin_notification_email">
                Email para Notificaciones
            </label>
        </th>
        <td>
            <input 
                type="email" 
                name="cdp_admin_notification_email" 
                id="cdp_admin_notification_email" 
                value="<?php echo esc_attr($email); ?>" 
                class="regular-text"
            >
            <p class="description">
                Dirección de correo donde se recibirán notificaciones de nuevos presupuestos.
            </p>
        </td>
    </tr>
    <?php
}
