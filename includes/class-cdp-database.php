<?php
/**
 * Clase para gestión de base de datos
 * 
 * Maneja las tablas y operaciones de BD del plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class CDP_Database {
    
    /**
     * Crear tablas del plugin
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Tabla principal de presupuestos
        $tabla_presupuestos = $wpdb->prefix . 'cdp_presupuestos';
        $sql_presupuestos = "CREATE TABLE $tabla_presupuestos (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            token VARCHAR(64) NOT NULL,
            
            -- Datos de contacto
            nombre VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            telefono VARCHAR(50) NOT NULL,
            codigo_postal VARCHAR(10) DEFAULT NULL,
            
            -- Datos del servicio
            tipo_servicio VARCHAR(100) NOT NULL COMMENT 'interna_completa, interna_fines_semana, externa_jornada, externa_horas',
            tipo_servicio_label VARCHAR(255) NOT NULL COMMENT 'Etiqueta legible del servicio',
            duracion_tipo VARCHAR(20) NOT NULL COMMENT 'corta o larga',
            dias_semana TEXT NOT NULL COMMENT 'JSON array de días',
            semanas_mes INT(1) NOT NULL DEFAULT 4,
            horario_tipo VARCHAR(20) NOT NULL COMMENT 'same, 24h, different',
            horario_detalle TEXT NOT NULL COMMENT 'JSON con slots horarios',
            horas_semanales DECIMAL(5,2) NOT NULL,
            
            -- Cálculos del presupuesto
            salario_bruto DECIMAL(10,2) NOT NULL,
            salario_neto DECIMAL(10,2) NOT NULL,
            cotizacion_ss DECIMAL(10,2) NOT NULL,
            cuota_cuidandote DECIMAL(10,2) NOT NULL,
            cuota_cuidandote_iva DECIMAL(10,2) NOT NULL,
            pago_mensual DECIMAL(10,2) NOT NULL,
            comision_agencia DECIMAL(10,2) NOT NULL,
            comision_agencia_iva DECIMAL(10,2) NOT NULL,
            
            -- Llamada programada
            llamada_fecha DATE DEFAULT NULL,
            llamada_hora TIME DEFAULT NULL,
            
            -- Metadatos
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            email_enviado TINYINT(1) NOT NULL DEFAULT 0,
            email_enviado_at DATETIME DEFAULT NULL,
            token_usado TINYINT(1) NOT NULL DEFAULT 0,
            token_usado_at DATETIME DEFAULT NULL,
            token_expira_at DATETIME NOT NULL,
            
            -- Timestamps
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY email (email),
            KEY created_at (created_at),
            KEY tipo_servicio (tipo_servicio)
        ) $charset_collate;";
        
        dbDelta($sql_presupuestos);
        
        // Tabla salarial
        $tabla_salarial = $wpdb->prefix . 'cdp_tabla_salarial';
        $existe_salarial = $wpdb->get_var("SHOW TABLES LIKE '$tabla_salarial'");
        
        $sql_salarial = "CREATE TABLE $tabla_salarial (
            id INT(11) NOT NULL AUTO_INCREMENT,
            horas_semanales INT(2) NOT NULL COMMENT 'Horas de jornada semanal (1-40)',
            horas_jornada_label VARCHAR(20) NOT NULL COMMENT 'Etiqueta (ej: 16 horas)',
            salario_bruto_mensual DECIMAL(10,2) NOT NULL,
            salario_neto_mensual DECIMAL(10,2) NOT NULL,
            cotizacion_ss DECIMAL(10,2) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY horas_semanales (horas_semanales)
        ) $charset_collate;";
        
        dbDelta($sql_salarial);
        
        // Insertar datos salariales si no existen
        $count_salarial = $wpdb->get_var("SELECT COUNT(*) FROM $tabla_salarial");
        if ($count_salarial < 40) {
            self::insert_tabla_salarial();
        }
        
        // Tabla de tarifas
        $tabla_tarifas = $wpdb->prefix . 'cdp_tarifas';
        
        $sql_tarifas = "CREATE TABLE $tabla_tarifas (
            id INT(11) NOT NULL AUTO_INCREMENT,
            concepto VARCHAR(100) NOT NULL,
            valor DECIMAL(10,2) NOT NULL,
            iva DECIMAL(5,2) NOT NULL DEFAULT 21.00,
            descripcion TEXT DEFAULT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY concepto (concepto)
        ) $charset_collate;";
        
        dbDelta($sql_tarifas);
        
        // Insertar tarifas si no existen
        $count_tarifas = $wpdb->get_var("SELECT COUNT(*) FROM $tabla_tarifas");
        if ($count_tarifas < 8) {
            self::insert_tarifas();
        }
        
        // Guardar versión de la BD
        update_option('cdp_db_version', CDP_VERSION);
    }
    
    /**
     * Insertar datos de tabla salarial 2025
     */
    private static function insert_tabla_salarial() {
        global $wpdb;
        $tabla = $wpdb->prefix . 'cdp_tabla_salarial';
        
        // Limpiar tabla
        $wpdb->query("TRUNCATE TABLE $tabla");
        
        $datos_salariales = array(
            array(1, '1 hora', 34.53, 15.65, 84.57),
            array(2, '2 horas', 69.07, 50.18, 84.57),
            array(3, '3 horas', 103.60, 84.72, 84.57),
            array(4, '4 horas', 138.13, 119.25, 84.57),
            array(5, '5 horas', 172.67, 153.78, 84.57),
            array(6, '6 horas', 207.20, 188.32, 84.57),
            array(7, '7 horas', 241.73, 222.85, 84.57),
            array(8, '8 horas', 276.27, 257.38, 84.57),
            array(9, '9 horas', 310.80, 291.92, 84.57),
            array(10, '10 horas', 345.34, 318.35, 120.85),
            array(11, '11 horas', 379.87, 352.88, 120.85),
            array(12, '12 horas', 414.40, 387.41, 120.85),
            array(13, '13 horas', 448.94, 421.95, 120.85),
            array(14, '14 horas', 483.47, 456.48, 120.85),
            array(15, '15 horas', 518.00, 480.74, 166.85),
            array(16, '16 horas', 552.54, 515.28, 166.85),
            array(17, '17 horas', 587.07, 549.81, 166.85),
            array(18, '18 horas', 621.60, 584.34, 166.85),
            array(19, '19 horas', 656.14, 618.88, 166.85),
            array(20, '20 horas', 690.67, 642.12, 217.42),
            array(21, '21 horas', 725.20, 676.65, 217.42),
            array(22, '22 horas', 759.74, 711.19, 217.42),
            array(23, '23 horas', 794.27, 745.72, 217.42),
            array(24, '24 horas', 828.80, 780.25, 217.42),
            array(25, '25 horas', 863.34, 803.30, 268.84),
            array(26, '26 horas', 897.87, 837.84, 268.84),
            array(27, '27 horas', 932.40, 872.37, 268.84),
            array(28, '28 horas', 966.94, 906.90, 268.84),
            array(29, '29 horas', 1001.47, 941.44, 268.84),
            array(30, '30 horas', 1036.01, 964.80, 318.84),
            array(31, '31 horas', 1070.54, 999.34, 318.84),
            array(32, '32 horas', 1105.07, 1033.87, 318.84),
            array(33, '33 horas', 1139.61, 1068.40, 318.84),
            array(34, '34 horas', 1174.14, 1102.94, 318.84),
            array(35, '35 horas', 1208.67, 1120.55, 394.61),
            array(36, '36 horas', 1243.21, 1155.09, 394.61),
            array(37, '37 horas', 1277.74, 1189.62, 394.61),
            array(38, '38 horas', 1312.27, 1224.15, 394.61),
            array(39, '39 horas', 1346.81, 1258.69, 394.61),
            array(40, '40 horas', 1381.34, 1293.21, 394.61),
        );
        
        foreach ($datos_salariales as $fila) {
            $wpdb->insert($tabla, array(
                'horas_semanales'       => $fila[0],
                'horas_jornada_label'   => $fila[1],
                'salario_bruto_mensual' => $fila[2],
                'salario_neto_mensual'  => $fila[3],
                'cotizacion_ss'         => $fila[4],
            ));
        }
    }
    
    /**
     * Insertar tarifas
     */
    private static function insert_tarifas() {
        global $wpdb;
        $tabla = $wpdb->prefix . 'cdp_tarifas';
        
        $tarifas = array(
            array('cuota_mantenimiento', 62.00, 21.00, 'Cuota mensual de mantenimiento por gestión'),
            array('comision_agencia_estandar', 300.00, 21.00, 'Comisión de agencia estándar'),
            array('comision_agencia_1dia', 50.00, 21.00, 'Comisión agencia para servicio 1 día/semana'),
            array('descuento_segundo_cuidador', 30.00, 0.00, 'Porcentaje descuento 2º cuidador'),
            array('sad_sin_cheque', 24.15, 10.00, 'SAD sin cheque servicio (por hora)'),
            array('sad_cheque_menor_80h', 16.73, 10.00, 'SAD con cheque <80h/mes (por hora)'),
            array('sad_cheque_mayor_80h', 15.53, 10.00, 'SAD con cheque >80h/mes (por hora)'),
            array('incremento_pareja', 10.00, 0.00, 'Porcentaje incremento si cuida a pareja'),
        );
        
        foreach ($tarifas as $tarifa) {
            $wpdb->replace($tabla, array(
                'concepto'    => $tarifa[0],
                'valor'       => $tarifa[1],
                'iva'         => $tarifa[2],
                'descripcion' => $tarifa[3],
            ));
        }
    }
    
    /**
     * Obtener tarifa de la tabla salarial
     */
    public static function get_salario_por_horas($horas_semanales) {
        global $wpdb;
        $tabla = $wpdb->prefix . 'cdp_tabla_salarial';
        
        // Redondear a entero más cercano, mínimo 1, máximo 40
        $horas = max(1, min(40, round($horas_semanales)));
        
        $resultado = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tabla WHERE horas_semanales = %d",
            $horas
        ));
        
        return $resultado;
    }
    
    /**
     * Obtener tarifa por concepto
     */
    public static function get_tarifa($concepto) {
        global $wpdb;
        $tabla = $wpdb->prefix . 'cdp_tarifas';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tabla WHERE concepto = %s AND activo = 1",
            $concepto
        ));
    }
    
    /**
     * Guardar presupuesto
     */
    public static function guardar_presupuesto($datos) {
        global $wpdb;
        $tabla = $wpdb->prefix . 'cdp_presupuestos';
        
        $resultado = $wpdb->insert($tabla, $datos);
        
        if ($resultado === false) {
            error_log('CDP Error al guardar presupuesto: ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Obtener presupuesto por token
     */
    public static function get_presupuesto_por_token($token) {
        global $wpdb;
        $tabla = $wpdb->prefix . 'cdp_presupuestos';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tabla WHERE token = %s",
            $token
        ));
    }
    
    /**
     * Marcar token como usado
     */
    public static function marcar_token_usado($token) {
        global $wpdb;
        $tabla = $wpdb->prefix . 'cdp_presupuestos';
        
        return $wpdb->update(
            $tabla,
            array(
                'token_usado'    => 1,
                'token_usado_at' => current_time('mysql'),
            ),
            array('token' => $token)
        );
    }
    
    /**
     * Marcar email como enviado
     */
    public static function marcar_email_enviado($id) {
        global $wpdb;
        $tabla = $wpdb->prefix . 'cdp_presupuestos';
        
        return $wpdb->update(
            $tabla,
            array(
                'email_enviado'    => 1,
                'email_enviado_at' => current_time('mysql'),
            ),
            array('id' => $id)
        );
    }
}
