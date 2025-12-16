<?php
/**
 * Migración de Base de Datos: Notificación Admin
 * 
 * Añade los campos necesarios para registrar notificaciones a administradores
 */

if (!defined('ABSPATH')) {
    exit;
}

class CDP_Admin_Notification_Migration {
    
    /**
     * Ejecutar la migración
     */
    public static function run() {
        global $wpdb;
        
        $tabla = $wpdb->prefix . 'cdp_presupuestos';
        
        // Verificar si las columnas ya existen
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $tabla WHERE Field IN ('admin_notificado', 'admin_notificado_at')");
        
        if (count($columns) >= 2) {
            return array(
                'success' => true,
                'message' => 'Las columnas ya existen. No es necesario migrar.'
            );
        }
        
        // Crear las columnas
        $sql = "
            ALTER TABLE $tabla 
            ADD COLUMN IF NOT EXISTS admin_notificado TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Si se notificó al admin',
            ADD COLUMN IF NOT EXISTS admin_notificado_at DATETIME DEFAULT NULL COMMENT 'Fecha de notificación al admin',
            ADD INDEX IF NOT EXISTS idx_admin_notificado (admin_notificado)
        ";
        
        $result = $wpdb->query($sql);
        
        if ($result === false) {
            return array(
                'success' => false,
                'message' => 'Error al ejecutar la migración: ' . $wpdb->last_error
            );
        }
        
        return array(
            'success' => true,
            'message' => 'Migración completada exitosamente. Columnas añadidas.'
        );
    }
    
    /**
     * Verificar estado de la migración
     */
    public static function check_status() {
        global $wpdb;
        
        $tabla = $wpdb->prefix . 'cdp_presupuestos';
        
        $columns = $wpdb->get_results(
            "SHOW COLUMNS FROM $tabla WHERE Field IN ('admin_notificado', 'admin_notificado_at')",
            ARRAY_A
        );
        
        return array(
            'migrated' => count($columns) >= 2,
            'columns' => $columns
        );
    }
}

/**
 * Añadir botón en el panel de admin para ejecutar la migración
 */
add_action('admin_notices', 'cdp_admin_notification_migration_notice');

function cdp_admin_notification_migration_notice() {
    $status = CDP_Admin_Notification_Migration::check_status();
    
    if ($status['migrated']) {
        return; // Ya está migrado
    }
    
    // Mostrar aviso si no está migrado
    if (isset($_GET['cdp_migrate_admin_notification'])) {
        $result = CDP_Admin_Notification_Migration::run();
        
        $class = $result['success'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . $class . ' is-dismissible">';
        echo '<p><strong>Cuidándote Presupuestos:</strong> ' . esc_html($result['message']) . '</p>';
        echo '</div>';
        
        return;
    }
    
    // Mostrar botón para migrar
    ?>
    <div class="notice notice-warning">
        <p>
            <strong>Cuidándote Presupuestos:</strong> 
            Se necesita actualizar la base de datos para habilitar notificaciones a administradores.
        </p>
        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=cuidandote-presupuestos&cdp_migrate_admin_notification=1')); ?>" 
               class="button button-primary">
                Ejecutar Migración Ahora
            </a>
        </p>
    </div>
    <?php
}

/**
 * Ejecutar migración automáticamente al activar el plugin (opcional)
 */
register_activation_hook(__FILE__, array('CDP_Admin_Notification_Migration', 'run'));
