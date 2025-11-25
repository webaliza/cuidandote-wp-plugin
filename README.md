# Cuidándote Presupuestos - Plugin WordPress

Plugin de WordPress para recibir solicitudes de presupuesto de cuidadores desde la aplicación Nuxt.

**Dominio:** https://cuidandoteserviciosauxiliares.com

## Instalación

1. Sube la carpeta `cuidandote-presupuestos` a `/wp-content/plugins/`
2. Activa el plugin desde **Plugins** en el panel de WordPress
3. El plugin creará automáticamente la página `/presupuesto-cuidadores/`

## Endpoint REST API

```
POST https://cuidandoteserviciosauxiliares.com/wp-json/cuidandote/v1/presupuesto
```

### Request

```json
{
  "data": {
    "nombre": "María García",
    "telefono": "612345678",
    "email": "maria@ejemplo.com",
    "tipo_servicio": "Cuidador externo",
    "horas_diarias": 8,
    "dias_semana": 5,
    "num_cuidadores": 1,
    "fecha_inicio": "2025-02-01",
    "necesidades": "Acompañamiento y ayuda con medicación"
  }
}
```

### Response

```json
{
  "success": true,
  "token": "abc123xyz...",
  "redirect_url": "https://cuidandoteserviciosauxiliares.com/presupuesto-cuidadores/?token=abc123xyz...",
  "message": "Datos del presupuesto recibidos correctamente"
}
```

## Integración con Nuxt

### Opción 1: Usar el Composable (Recomendado)

Copia `examples/nuxt/composables/useCuidandotePresupuesto.ts` a tu proyecto:

```typescript
// En tu componente de formulario
const { 
  enviarPresupuesto, 
  isSubmitting, 
  error 
} = useCuidandotePresupuesto();

async function handleSubmit() {
  try {
    await enviarPresupuesto({
      nombre: formData.nombre,
      telefono: formData.telefono,
      email: formData.email,
      tipo_servicio: formData.tipoServicio,
      horas_diarias: formData.horasDiarias,
      // ... resto de campos
    });
    // El iframe se cerrará automáticamente y redirigirá a WordPress
  } catch (e) {
    console.error('Error:', e);
  }
}
```

### Opción 2: Implementación Manual

```javascript
async function enviarPresupuesto(datosFormulario) {
  const response = await fetch(
    'https://cuidandoteserviciosauxiliares.com/wp-json/cuidandote/v1/presupuesto',
    {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ data: datosFormulario })
    }
  );
  
  const result = await response.json();
  
  if (result.success) {
    // Comunicar con WordPress para cerrar el iframe
    window.parent.postMessage({
      type: 'cdp_close_iframe',
      redirect_url: result.redirect_url
    }, 'https://cuidandoteserviciosauxiliares.com');
  }
  
  return result;
}
```

## Shortcodes

### `[cuidandote_presupuesto]`

Muestra los datos del presupuesto recibido. Se usa automáticamente en la página creada por el plugin.

```
[cuidandote_presupuesto]
[cuidandote_presupuesto class="mi-clase-personalizada"]
```

### `[cuidandote_formulario]`

Inserta el iframe con el formulario Nuxt en cualquier página de WordPress.

```
[cuidandote_formulario src="https://tu-app-nuxt.com/formulario" height="900px"]
```

**Parámetros:**
- `src` (obligatorio): URL de la aplicación Nuxt
- `width`: Ancho del iframe (default: `100%`)
- `height`: Alto del iframe (default: `800px`)
- `class`: Clase CSS del contenedor

## Flujo Completo

```
┌─────────────────────────────────────────────────────────────────────────┐
│              cuidandoteserviciosauxiliares.com (WordPress)               │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   PÁGINA CON FORMULARIO                                                  │
│   [cuidandote_formulario src="https://app-nuxt.com"]                    │
│   ┌──────────────────────────────────────────────────────────────────┐  │
│   │                    IFRAME (Aplicación Nuxt)                       │  │
│   │                                                                   │  │
│   │   1. Usuario completa el formulario de presupuesto               │  │
│   │   2. Click en "Solicitar Presupuesto"                            │  │
│   │   3. POST → /wp-json/cuidandote/v1/presupuesto                   │  │
│   │   4. Recibe { success, token, redirect_url }                     │  │
│   │   5. postMessage → { type: 'cdp_close_iframe', redirect_url }    │  │
│   │                                                                   │  │
│   └──────────────────────────────────────────────────────────────────┘  │
│                              │                                           │
│                              ▼                                           │
│   6. WordPress recibe el postMessage                                    │
│   7. Oculta/cierra el iframe                                            │
│   8. Redirige a /presupuesto-cuidadores/?token=xxx                      │
│                                                                          │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   PÁGINA DE RESULTADOS (/presupuesto-cuidadores/)                       │
│   [cuidandote_presupuesto]                                              │
│                                                                          │
│   ┌──────────────────────────────────────────────────────────────────┐  │
│   │                                                                   │  │
│   │   📋 Resumen de tu Solicitud de Presupuesto                      │  │
│   │   Servicio de Cuidadores de Personas Mayores                     │  │
│   │                                                                   │  │
│   │   Referencia: ABC12345                                           │  │
│   │   Fecha: 25/11/2025 10:30                                        │  │
│   │                                                                   │  │
│   │   ─────────────────────────────────────────────                  │  │
│   │   Nombre:           María García                                  │  │
│   │   Teléfono:         612345678                                     │  │
│   │   Tipo servicio:    Cuidador externo                             │  │
│   │   Horas diarias:    8                                            │  │
│   │   ...                                                            │  │
│   │   ─────────────────────────────────────────────                  │  │
│   │                                                                   │  │
│   │   ✅ Próximos pasos                                              │  │
│   │   Nuestro equipo se pondrá en contacto en 24-48h                 │  │
│   │                                                                   │  │
│   └──────────────────────────────────────────────────────────────────┘  │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

## Campos Reconocidos

El plugin formatea automáticamente estos nombres de campo:

| Campo JSON | Se muestra como |
|------------|-----------------|
| `nombre` | Nombre |
| `apellidos` | Apellidos |
| `email` | Correo electrónico |
| `telefono` | Teléfono |
| `tipo_servicio` | Tipo de servicio |
| `horas_diarias` | Horas diarias |
| `dias_semana` | Días a la semana |
| `num_cuidadores` | Número de cuidadores |
| `fecha_inicio` | Fecha de inicio |
| `necesidades` | Necesidades especiales |
| `movilidad` | Movilidad del paciente |
| `edad_paciente` | Edad del paciente |
| `patologias` | Patologías |
| `interno` | Servicio interno |
| `externo` | Servicio externo |
| `urgente` | Solicitud urgente |

Cualquier otro campo se mostrará con formato automático (snake_case → Texto legible).

## Hooks para Desarrolladores

### `cuidandote_after_presupuesto`

Añade contenido después del presupuesto (ideal para futuros cálculos de precio):

```php
add_action('cuidandote_after_presupuesto', function($data, $token) {
    // Ejemplo: mostrar precio estimado en el futuro
    echo '<div class="cdp-precio-estimado">';
    echo '<h3>Precio Estimado</h3>';
    // Lógica de cálculo...
    echo '</div>';
}, 10, 2);
```

## Configuración CORS

Si la aplicación Nuxt está en un dominio diferente, añade esto a `functions.php`:

```php
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($value) {
        $origin = 'https://tu-app-nuxt.com'; // Cambiar por el dominio real
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Allow-Credentials: true');
        return $value;
    });
}, 15);
```

## Panel de Administración

Accede a **Ajustes → Presupuestos** en WordPress para ver:

- URL del endpoint (con botón de copiar)
- Estado de la página de resultados
- Código de ejemplo para Nuxt
- Estructura JSON esperada

## Seguridad

- Todos los datos se sanitizan antes de almacenar
- Los presupuestos expiran en 24 horas (transient)
- Se recomienda configurar CORS específico para tu dominio Nuxt
- El token es único por cada solicitud

## Requisitos

- WordPress 5.0+
- PHP 7.4+
- Sesiones PHP habilitadas

## Changelog

### 1.0.0
- Versión inicial
- Endpoint REST `/cuidandote/v1/presupuesto`
- Shortcodes `[cuidandote_presupuesto]` y `[cuidandote_formulario]`
- Panel de administración
- Estilos responsive
- Soporte para impresión

---

**Cuidándote Servicios Auxiliares**  
https://cuidandoteserviciosauxiliares.com
