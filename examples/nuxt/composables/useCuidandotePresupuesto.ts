/**
 * Composable para enviar presupuestos a WordPress
 * Cuidándote Servicios Auxiliares
 * 
 * Ubicación: composables/useCuidandotePresupuesto.ts
 */

interface PresupuestoResponse {
  success: boolean;
  token: string;
  redirect_url: string;
  message: string;
}

interface UseCuidandotePresupuestoOptions {
  /** URL base de WordPress (sin trailing slash) */
  wordpressUrl?: string;
  /** Si debe cerrar el iframe automáticamente tras éxito */
  autoCloseIframe?: boolean;
  /** Si debe redirigir automáticamente tras éxito */
  autoRedirect?: boolean;
}

// Configuración por defecto para Cuidándote
const DEFAULT_OPTIONS: Required<UseCuidandotePresupuestoOptions> = {
  wordpressUrl: 'https://cuidandoteserviciosauxiliares.com',
  autoCloseIframe: true,
  autoRedirect: true
};

export function useCuidandotePresupuesto(options: UseCuidandotePresupuestoOptions = {}) {
  const config = { ...DEFAULT_OPTIONS, ...options };
  
  const endpoint = `${config.wordpressUrl}/wp-json/cuidandote/v1/presupuesto`;
  const parentOrigin = config.wordpressUrl;

  const isSubmitting = ref(false);
  const error = ref<string | null>(null);
  const response = ref<PresupuestoResponse | null>(null);

  /**
   * Enviar datos del presupuesto a WordPress
   */
  async function enviarPresupuesto<T extends Record<string, any>>(
    datosPresupuesto: T
  ): Promise<PresupuestoResponse> {
    isSubmitting.value = true;
    error.value = null;

    try {
      const res = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          data: datosPresupuesto
        })
      });

      if (!res.ok) {
        throw new Error(`Error HTTP: ${res.status}`);
      }

      const result: PresupuestoResponse = await res.json();
      response.value = result;

      if (result.success) {
        if (config.autoCloseIframe) {
          cerrarIframe(result.redirect_url);
        }
      } else {
        throw new Error(result.message || 'Error al procesar el presupuesto');
      }

      return result;

    } catch (e) {
      const errorMessage = e instanceof Error 
        ? e.message 
        : 'Error al enviar el presupuesto';
      error.value = errorMessage;
      throw e;

    } finally {
      isSubmitting.value = false;
    }
  }

  /**
   * Cerrar el iframe y opcionalmente redirigir
   */
  function cerrarIframe(redirectUrl?: string) {
    if (typeof window !== 'undefined' && window.parent !== window) {
      window.parent.postMessage({
        type: 'cdp_close_iframe',
        redirect_url: config.autoRedirect ? redirectUrl : undefined
      }, parentOrigin);
    }
  }

  /**
   * Enviar mensaje personalizado a WordPress
   */
  function enviarMensaje(mensaje: Record<string, any>) {
    if (typeof window !== 'undefined' && window.parent !== window) {
      window.parent.postMessage(mensaje, parentOrigin);
    }
  }

  /**
   * Verificar si estamos dentro de un iframe
   */
  function estaEnIframe(): boolean {
    if (typeof window === 'undefined') return false;
    return window.parent !== window;
  }

  /**
   * Resetear el estado del composable
   */
  function reset() {
    isSubmitting.value = false;
    error.value = null;
    response.value = null;
  }

  return {
    // Estado (solo lectura)
    isSubmitting: readonly(isSubmitting),
    error: readonly(error),
    response: readonly(response),
    
    // Configuración
    endpoint,
    
    // Métodos
    enviarPresupuesto,
    cerrarIframe,
    enviarMensaje,
    estaEnIframe,
    reset
  };
}
