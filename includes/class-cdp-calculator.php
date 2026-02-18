<?php
/**
 * Clase para cálculo de presupuestos
 *
 * Procesa los datos del formulario y calcula el presupuesto completo
 *
 * FÓRMULA v2.0 (Enero 2026):
 * horas_equivalentes = ((horas_dia × dias_semana) / 4) × semanas_mes
 * Se busca en tabla salarial por horas_equivalentes (redondeado a entero)
 * Ya NO se aplica factor de semanas parciales por separado.
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

		// 2. Calcular horas semanales y horas equivalentes
		$this->calcular_horas_semanales();

		// 3. Determinar tipo de servicio (usa horas reales, no equivalentes)
		$this->determinar_tipo_servicio();

		// 4. Obtener tarifas de la tabla salarial (usa horas equivalentes)
		$this->obtener_tarifas_salariales();

		// *** ELIMINADO: aplicar_factor_semanas() ***
		// Ya no es necesario porque las semanas están integradas
		// en la fórmula de horas_equivalentes

		// 5. Calcular cuota Cuidándote
		$this->calcular_cuota_cuidandote();

		// 6. Calcular comisión de agencia
		$this->calcular_comision_agencia();

		// 7. Calcular totales
		$this->calcular_totales();

		// 8. Generar token
		$this->generar_token();

		// 9. Preparar datos para guardar
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

		// Fecha y hora de llamada
		$datetime = $this->data['selectedDateTime'] ?? array();
		$fecha_raw = $datetime['date'] ?? null;
		$hora_raw = $datetime['time'] ?? null;

		// Convertir fecha de DD-MM-YYYY a YYYY-MM-DD
		if ($fecha_raw) {
			$partes = explode('-', $fecha_raw);
			if (count($partes) === 3) {
				$this->resultado['llamada_fecha'] = $partes[2] . '-' . $partes[1] . '-' . $partes[0];
			} else {
				$this->resultado['llamada_fecha'] = null;
			}
		} else {
			$this->resultado['llamada_fecha'] = null;
		}

		$this->resultado['llamada_hora'] = $hora_raw ? $hora_raw . ':00' : null;
	}

	/**
	 * Calcular horas semanales (reales) y horas equivalentes (para tabla salarial)
	 *
	 * FÓRMULA v2.0:
	 * 1. Se calculan las horas reales por semana (horas_dia × dias, o suma de slots)
	 * 2. Se calculan horas_equivalentes = ((horas_dia × dias) / 4) × semanas
	 *    Simplificado: (horas_semanales_reales / 4) × semanas
	 * 3. horas_equivalentes se usa para buscar en la tabla salarial
	 */
	private function calcular_horas_semanales() {
		$dias = $this->data['selectedDays'] ?? array();
		$num_dias = count($dias);
		$semanas = intval($this->data['selectedWeeks'] ?? 4);
		$schedule = $this->data['selectedSchedule'] ?? array();

		// Guardar datos base
		$this->resultado['dias_semana'] = wp_json_encode($dias);
		$this->resultado['semanas_mes'] = max(1, min(4, $semanas));
		$this->resultado['duracion_tipo'] = sanitize_text_field($this->data['durationType'] ?? 'larga');

		// Determinar tipo de horario
		$horario_tipo = 'same'; // Por defecto
		$horario_detalle = array();

		if (!empty($schedule)) {
			$primer_schedule = $schedule[0] ?? array();
			$horario_tipo = $primer_schedule['value'] ?? 'same';
			$horario_detalle = $schedule;
		}

		$this->resultado['horario_tipo'] = $horario_tipo;
		$this->resultado['horario_detalle'] = wp_json_encode($horario_detalle);

		// Calcular horas semanales REALES según tipo de horario
		$horas_semanales = 0;

		if ($horario_tipo === '24h') {
			// Interna: 8 horas de trabajo efectivo por día
			$horas_semanales = 8 * $num_dias;
		} else {
			// Externa: calcular desde los slots horarios
			$days = array();
			foreach ($schedule as $sched) {
				if (isset($sched['days'])) {
					$days = $sched['days'];
					break;
				}
			}

			foreach ($days as $day_config) {
				$slots = $day_config['slots'] ?? array();
				$horas_dia = 0;

				foreach ($slots as $slot) {
					$from = $slot['from'] ?? '00:00';
					$to = $slot['to'] ?? '00:00';
					$horas_slot = $this->calcular_diferencia_horas($from, $to);
					$horas_dia += $horas_slot;
				}

				if ($horario_tipo === 'same') {
					// Misma hora todos los días: multiplicar por número de días
					$horas_semanales = $horas_dia * $num_dias;
					break; // Solo hay un registro para todos los días
				} else {
					// Diferente horario cada día: sumar
					$horas_semanales += $horas_dia;
				}
			}
		}

		// Limitar horas semanales reales a rango válido
		$horas_semanales = max(1, min(40, $horas_semanales));

		// =====================================================
		// FÓRMULA v2.0: Calcular horas equivalentes
		// ((horas_dia × dias) / 4) × semanas
		// Equivale a: (horas_semanales / 4) × semanas
		// =====================================================
		$semanas_val = $this->resultado['semanas_mes'];
		$horas_equivalentes = ($horas_semanales / 4) * $semanas_val;

		// Redondear al entero más cercano y limitar a rango 1-40
		$horas_equivalentes = max(1, min(40, round($horas_equivalentes)));

		// Guardar ambos valores
		// horas_semanales: horas reales por semana (para clasificación de servicio)
		// horas_equivalentes: valor para buscar en tabla salarial
		$this->resultado['horas_semanales_reales'] = $horas_semanales;
		$this->resultado['horas_semanales'] = $horas_equivalentes; // Este se guarda en BD y se usa para lookup

		// Log para depuración
		error_log(sprintf(
			'CDP Calculator v2.0: horas_reales=%s, dias=%d, semanas=%d, equivalentes=%s',
			$horas_semanales, $num_dias, $semanas_val, $horas_equivalentes
		));
	}

	/**
	 * Calcular diferencia de horas entre dos tiempos
	 */
	private function calcular_diferencia_horas($from, $to) {
		$from_parts = explode(':', $from);
		$to_parts = explode(':', $to);

		$from_minutes = (intval($from_parts[0]) * 60) + intval($from_parts[1] ?? 0);
		$to_minutes = (intval($to_parts[0]) * 60) + intval($to_parts[1] ?? 0);

		// Si to es menor que from, asumimos que cruza medianoche
		if ($to_minutes <= $from_minutes) {
			$to_minutes += 24 * 60;
		}

		$diferencia_minutos = $to_minutes - $from_minutes;
		return $diferencia_minutos / 60;
	}

	/**
	 * Determinar tipo de servicio
	 *
	 * NOTA: Usa horas_semanales_reales (no equivalentes) para la clasificación,
	 * ya que el tipo de servicio depende del patrón real de trabajo.
	 */
	private function determinar_tipo_servicio() {
		$dias = $this->data['selectedDays'] ?? array();
		$horario_tipo = $this->resultado['horario_tipo'];
		$horas = $this->resultado['horas_semanales_reales']; // Usar horas reales para clasificación

		// Verificar si es fin de semana
		$es_fin_semana = $this->es_solo_fin_semana($dias);
		$es_entre_semana = $this->es_solo_entre_semana($dias);

		// Determinar tipo
		if ($horario_tipo === '24h') {
			// Servicio interno
			if ($es_fin_semana) {
				$tipo = 'interna_fines_semana';
				$label = 'Interna fines de semana';

				// Ajustar label según días
				if (count($dias) === 1) {
					$label = 'Interna 1 día a la semana';
				} elseif (count($dias) === 2) {
					$label = 'Interna fines de semana (día y medio)';
				}
			} elseif (count($dias) <= 2) {
				$tipo = 'interna_parcial';
				$label = 'Interna ' . count($dias) . ' días a la semana';
			} elseif (count($dias) >= 6) {
				$tipo = 'interna_completa';
				$label = 'Interna completa (24h)';
			} elseif ($es_entre_semana) {
				$tipo = 'interna_entre_semana';
				$label = 'Interna entre semana';
			} else {
				$tipo = 'interna_parcial';
				$label = 'Interna ' . count($dias) . ' días a la semana';
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
	 * Verificar si los días seleccionados son solo fin de semana
	 */
	private function es_solo_fin_semana($dias) {
		$fin_semana = array('SAB', 'DOM');
		foreach ($dias as $dia) {
			if (!in_array($dia, $fin_semana)) {
				return false;
			}
		}
		return count($dias) > 0;
	}

	/**
	 * Verificar si los días seleccionados son solo entre semana
	 */
	private function es_solo_entre_semana($dias) {
		$laborables = array('LUN', 'MAR', 'MIE', 'JUE', 'VIE');
		foreach ($dias as $dia) {
			if (!in_array($dia, $laborables)) {
				return false;
			}
		}
		return count($dias) > 0;
	}

	/**
	 * Obtener tarifas de la tabla salarial
	 *
	 * CAMBIO v2.0: Usa horas_equivalentes (ya guardado en horas_semanales)
	 * en lugar de las horas reales. Ya no necesita ajuste posterior por semanas.
	 */
	private function obtener_tarifas_salariales() {
		$horas = $this->resultado['horas_semanales']; // Ya contiene horas_equivalentes
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

			error_log('CDP: No se encontró tarifa salarial para ' . $horas . ' horas equivalentes');
		}
	}

	/**
	 * *** ELIMINADO en v2.0 ***
	 *
	 * aplicar_factor_semanas() ya no existe.
	 *
	 * Antes: si semanas < 4, se multiplicaban salario_bruto, salario_neto y
	 * cotizacion_ss por (semanas / 4).
	 *
	 * Ahora: las semanas ya están integradas en la fórmula de horas_equivalentes:
	 * horas_equivalentes = (horas_semanales / 4) × semanas
	 * y se busca directamente en la tabla salarial por ese valor.
	 */

	/**
	 * Calcular cuota Cuidándote
	 */
	private function calcular_cuota_cuidandote() {
		$tarifa = CDP_Database::get_tarifa('cuota_mantenimiento');

		if ($tarifa) {
			$base = floatval($tarifa->valor);
			$iva = floatval($tarifa->iva);
		} else {
			$base = 65.00;
			$iva = 21.00;
		}

		$this->resultado['cuota_cuidandote'] = $base;
		$this->resultado['cuota_cuidandote_iva'] = round($base * (1 + $iva / 100), 2);
	}

	/**
	 * Calcular comisión de agencia
	 */
	private function calcular_comision_agencia() {
		$dias = $this->data['selectedDays'] ?? array();
		$num_dias = count($dias);

		// Determinar qué comisión aplicar
		if ($num_dias <= 1) {
			$tarifa = CDP_Database::get_tarifa('comision_agencia_1dia');
		} else {
			$tarifa = CDP_Database::get_tarifa('comision_agencia_estandar');
		}

		if ($tarifa) {
			$base = floatval($tarifa->valor);
			$iva = floatval($tarifa->iva);
		} else {
			$base = ($num_dias <= 1) ? 50.00 : 300.00;
			$iva = 21.00;
		}

		$this->resultado['comision_agencia'] = $base;
		$this->resultado['comision_agencia_iva'] = round($base * (1 + $iva / 100), 2);
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
	 * Preparar datos para guardar en base de datos
	 */
	private function preparar_datos_guardado() {
		return array(
			'token'                 => $this->resultado['token'],
			'nombre'                => $this->resultado['nombre'],
			'email'                 => $this->resultado['email'],
			'telefono'              => $this->resultado['telefono'],
			'codigo_postal'         => $this->resultado['codigo_postal'],
			'tipo_servicio'         => $this->resultado['tipo_servicio'],
			'tipo_servicio_label'   => $this->resultado['tipo_servicio_label'],
			'duracion_tipo'         => $this->resultado['duracion_tipo'],
			'dias_semana'           => $this->resultado['dias_semana'],
			'semanas_mes'           => $this->resultado['semanas_mes'],
			'horario_tipo'          => $this->resultado['horario_tipo'],
			'horario_detalle'       => $this->resultado['horario_detalle'],
			'horas_semanales'       => $this->resultado['horas_semanales'], // horas equivalentes
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