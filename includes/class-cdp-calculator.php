<?php
/**
 * Clase para cálculo de presupuestos
 * 
 * Procesa los datos del formulario y calcula el presupuesto completo
 */

if (!defined('ABSPATH')) {
    exit;
}

class CDP_Calculator {
    
    /**
     * Datos del formulario
     */
    private $data;
    
    /**
     * Resultado del cálculo
     */
    private $resultado;
    
    /**
     * Constructor
     */
    public function __construct($data) {
        $this->data = $data;
        $this->resultado = array();
    }
    
    /**
     * Calcular presupuesto completo
     */
    public function calcular() {
        // 1. Extraer y validar datos de contacto
        $this->extraer_contacto();
        
        // 2. Calcular horas semanales
        $this->calcular_horas_semanales();
        
        // 3. Determinar tipo de servicio
        $this->determinar_tipo_servicio();
        
        // 4. Obtener tarifas de la tabla salarial
        $this->obtener_tarifas_salariales();
        
        // 5. Aplicar factor por semanas parciales
        $this->aplicar_factor_semanas();
        
        // 6. Calcular cuota Cuidándote
        $this->calcular_cuota_cuidandote();
        
        // 7. Calcular comisión de agencia
        $this->calcular_comision_agencia();
        
        // 8. Calcular totales
        $this->calcular_totales();
        
        // 9. Generar token
        $this->generar_token();
        
        // 10. Preparar datos para guardar
        return $this->preparar_datos_guardado();
    }
    
    /**
     * Extraer datos de contacto
     */
    private function extraer_contacto() {
        $contacto = $this->data['contacto'] ?? array();
        
        $this->resultado['nombre'] = sanitize_text_field($contacto['name'] ?? '');
        $this->resultado['email'] = sanitize_email($contacto['email'] ?? '');
        $this->resultado['telefono'] = sanitize_text_field($contacto['phone'] ?? '');
        $this->resultado['codigo_postal'] = sanitize_text_field($contacto['postalCode'] ?? '');
        
        // Datos de llamada programada
        if (!empty($this->data['selectedDateTime'])) {
            $dateTime = $this->data['selectedDateTime'];
            $this->resultado['llamada_fecha'] = $this->parse_fecha($dateTime['date'] ?? '');
            $this->resultado['llamada_hora'] = sanitize_text_field($dateTime['time'] ?? '');
        }
    }
    
    /**
     * Parsear fecha DD-MM-YYYY a YYYY-MM-DD
     */
    private function parse_fecha($fecha) {
        if (empty($fecha)) return null;
        
        $partes = explode('-', $fecha);
        if (count($partes) === 3) {
            return $partes[2] . '-' . $partes[1] . '-' . $partes[0];
        }
        return null;
    }
    
    /**
     * Calcular horas semanales totales
     */
    private function calcular_horas_semanales() {
        $selectedSchedule = $this->data['selectedSchedule'] ?? array();
        $selectedDays = $this->data['selectedDays'] ?? array();
        $horario_tipo = '';
        $horas_totales = 0;
        
        if (!empty($selectedSchedule)) {
            $schedule = $selectedSchedule[0] ?? array();
            $horario_tipo = $schedule['value'] ?? 'same';
            
            // Si es 24h (interna)
            if ($horario_tipo === '24h') {
                // 24 horas por cada día seleccionado
                // Pero en internas se cuenta como jornada completa (40h)
                $num_dias = count($selectedDays);
                
                if ($num_dias >= 5) {
                    $horas_totales = 40; // Interna completa
                } else {
                    // Proporción basada en días
                    $horas_totales = round(($num_dias / 7) * 40);
                }
            } else {
                // Calcular horas sumando slots
                $days = $schedule['days'] ?? array();
                
                foreach ($days as $day) {
                    $slots = $day['slots'] ?? array();
                    
                    foreach ($slots as $slot) {
                        $from = $slot['from'] ?? '00:00';
                        $to = $slot['to'] ?? '00:00';
                        
                        $horas_slot = $this->calcular_horas_slot($from, $to);
                        
                        // Si es "same" (mismo horario todos los días)
                        if ($horario_tipo === 'same') {
                            $horas_totales = $horas_slot * count($selectedDays);
                            break 2; // Salir de ambos loops
                        } else {
                            // Horario diferente por día
                            $horas_totales += $horas_slot;
                        }
                    }
                }
            }
        }
        
        $this->resultado['horas_semanales'] = max(1, min(40, $horas_totales));
        $this->resultado['horario_tipo'] = $horario_tipo;
        $this->resultado['dias_semana'] = json_encode($selectedDays);
        $this->resultado['horario_detalle'] = json_encode($selectedSchedule);
    }
    
    /**
     * Calcular horas de un slot horario
     */
    private function calcular_horas_slot($from, $to) {
        $from_parts = explode(':', $from);
        $to_parts = explode(':', $to);
        
        $from_minutes = (int)$from_parts[0] * 60 + (int)($from_parts[1] ?? 0);
        $to_minutes = (int)$to_parts[0] * 60 + (int)($to_parts[1] ?? 0);
        
        // Si termina antes de empezar, asumir que cruza medianoche
        if ($to_minutes <= $from_minutes) {
            $to_minutes += 24 * 60;
        }
        
        return ($to_minutes - $from_minutes) / 60;
    }
    
    /**
     * Determinar tipo de servicio
     */
    private function determinar_tipo_servicio() {
        $horario_tipo = $this->resultado['horario_tipo'];
        $horas = $this->resultado['horas_semanales'];
        $dias = json_decode($this->resultado['dias_semana'], true) ?: array();
        
        $dias_laborables = array('LUN', 'MAR', 'MIE', 'JUE', 'VIE');
        $fin_semana = array('SAB', 'DOM');
        
        $es_solo_finde = count(array_diff($dias, $fin_semana)) === 0 && count($dias) > 0;
        $es_solo_laborables = count(array_diff($dias, $dias_laborables)) === 0 && count($dias) > 0;
        $es_completa = count($dias) >= 6;
        
        // Clasificación
        if ($horario_tipo === '24h') {
            if ($es_completa) {
                $tipo = 'interna_completa';
                $label = 'Interna completa (24h)';
            } elseif ($es_solo_finde) {
                $tipo = 'interna_fines_semana';
                $label = 'Interna fines de semana';
            } else {
                $tipo = 'interna_entre_semana';
                $label = 'Interna entre semana';
            }
        } else {
            // Externa
            $horas_diarias = $horas / max(1, count($dias));
            
            if ($horas_diarias < 4) {
                $tipo = 'externa_horas';
                $label = 'Externa por horas';
            } elseif ($horas >= 35) {
                $tipo = 'externa_jornada_completa';
                $label = 'Externa jornada completa';
            } else {
                $tipo = 'externa_jornada_parcial';
                $label = 'Externa jornada parcial';
            }
        }
        
        $this->resultado['tipo_servicio'] = $tipo;
        $this->resultado['tipo_servicio_label'] = $label;
    }
    
    /**
     * Obtener tarifas de la tabla salarial
     */
    private function obtener_tarifas_salariales() {
        $horas = $this->resultado['horas_semanales'];
        $tarifa = CDP_Database::get_salario_por_horas($horas);
        
        if ($tarifa) {
            $this->resultado['salario_bruto'] = (float) $tarifa->salario_bruto_mensual;
            $this->resultado['salario_neto'] = (float) $tarifa->salario_neto_mensual;
            $this->resultado['cotizacion_ss'] = (float) $tarifa->cotizacion_ss;
        } else {
            // Fallback si no hay datos
            $this->resultado['salario_bruto'] = 0;
            $this->resultado['salario_neto'] = 0;
            $this->resultado['cotizacion_ss'] = 0;
            
            error_log('CDP: No se encontró tarifa salarial para ' . $horas . ' horas');
        }
    }
    
    /**
     * Aplicar factor por semanas parciales
     */
    private function aplicar_factor_semanas() {
        $semanas = (int) ($this->data['selectedWeeks'] ?? 4);
        $this->resultado['semanas_mes'] = $semanas;
        
        // Factor de ajuste (4 semanas = 100%)
        $factor = $semanas / 4;
        
        if ($factor < 1) {
            $this->resultado['salario_bruto'] *= $factor;
            $this->resultado['salario_neto'] *= $factor;
            // La SS no se reduce proporcionalmente
        }
        
        $this->resultado['duracion_tipo'] = $this->data['durationType'] ?? 'larga';
    }
    
    /**
     * Calcular cuota Cuidándote
     */
    private function calcular_cuota_cuidandote() {
        $tarifa = CDP_Database::get_tarifa('cuota_mantenimiento');
        
        if ($tarifa) {
            $base = (float) $tarifa->valor;
            $iva = (float) $tarifa->iva;
            
            $this->resultado['cuota_cuidandote'] = $base;
            $this->resultado['cuota_cuidandote_iva'] = round($base * (1 + $iva / 100), 2);
        } else {
            // Valores por defecto
            $this->resultado['cuota_cuidandote'] = 62.00;
            $this->resultado['cuota_cuidandote_iva'] = 75.02;
        }
    }
    
    /**
     * Calcular comisión de agencia
     */
    private function calcular_comision_agencia() {
        $dias = json_decode($this->resultado['dias_semana'], true) ?: array();
        
        // Si es solo 1 día, comisión reducida
        $concepto = count($dias) <= 1 ? 'comision_agencia_1dia' : 'comision_agencia_estandar';
        $tarifa = CDP_Database::get_tarifa($concepto);
        
        if ($tarifa) {
            $base = (float) $tarifa->valor;
            $iva = (float) $tarifa->iva;
            
            $this->resultado['comision_agencia'] = $base;
            $this->resultado['comision_agencia_iva'] = round($base * (1 + $iva / 100), 2);
        } else {
            // Valores por defecto
            $this->resultado['comision_agencia'] = count($dias) <= 1 ? 50.00 : 300.00;
            $this->resultado['comision_agencia_iva'] = count($dias) <= 1 ? 60.50 : 363.00;
        }
    }
    
    /**
     * Calcular totales
     */
    private function calcular_totales() {
        $this->resultado['pago_mensual'] = round(
            $this->resultado['salario_neto'] +
            $this->resultado['cotizacion_ss'] +
            $this->resultado['cuota_cuidandote_iva'],
            2
        );
    }
    
    /**
     * Generar token único
     */
    private function generar_token() {
        $this->resultado['token'] = wp_generate_uuid4() . '-' . bin2hex(random_bytes(8));
        $this->resultado['token_expira_at'] = date('Y-m-d H:i:s', strtotime('+30 days'));
    }
    
    /**
     * Preparar datos para guardar en BD
     */
    private function preparar_datos_guardado() {
        return array(
            'token'                 => $this->resultado['token'],
            'nombre'                => $this->resultado['nombre'],
            'email'                 => $this->resultado['email'],
            'telefono'              => $this->resultado['telefono'],
            'codigo_postal'         => $this->resultado['codigo_postal'] ?? null,
            'tipo_servicio'         => $this->resultado['tipo_servicio'],
            'tipo_servicio_label'   => $this->resultado['tipo_servicio_label'],
            'duracion_tipo'         => $this->resultado['duracion_tipo'],
            'dias_semana'           => $this->resultado['dias_semana'],
            'semanas_mes'           => $this->resultado['semanas_mes'],
            'horario_tipo'          => $this->resultado['horario_tipo'],
            'horario_detalle'       => $this->resultado['horario_detalle'],
            'horas_semanales'       => $this->resultado['horas_semanales'],
            'salario_bruto'         => $this->resultado['salario_bruto'],
            'salario_neto'          => $this->resultado['salario_neto'],
            'cotizacion_ss'         => $this->resultado['cotizacion_ss'],
            'cuota_cuidandote'      => $this->resultado['cuota_cuidandote'],
            'cuota_cuidandote_iva'  => $this->resultado['cuota_cuidandote_iva'],
            'pago_mensual'          => $this->resultado['pago_mensual'],
            'comision_agencia'      => $this->resultado['comision_agencia'],
            'comision_agencia_iva'  => $this->resultado['comision_agencia_iva'],
            'llamada_fecha'         => $this->resultado['llamada_fecha'] ?? null,
            'llamada_hora'          => $this->resultado['llamada_hora'] ?? null,
            'ip_address'            => $this->get_client_ip(),
            'user_agent'            => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'token_expira_at'       => $this->resultado['token_expira_at'],
        );
    }
    
    /**
     * Obtener IP del cliente
     */
    private function get_client_ip() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field($ip);
    }
    
    /**
     * Obtener resultado del cálculo
     */
    public function get_resultado() {
        return $this->resultado;
    }
}
