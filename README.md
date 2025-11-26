# Cuidándote Presupuestos

Plugin de WordPress para gestión automática de presupuestos de servicios de cuidadores.

## Descripción

Este plugin recibe datos del formulario Nuxt, calcula presupuestos automáticamente según la tabla salarial 2025, envía emails profesionales con la propuesta de asistencia y muestra el desglose completo del presupuesto mediante un enlace con token único.

## Características

- ✅ **API REST** para recibir datos del formulario Nuxt
- ✅ **Cálculo automático** de presupuestos según horas y tipo de servicio
- ✅ **Tabla salarial 2025** integrada (1-40 horas semanales)
- ✅ **Emails HTML profesionales** con diseño corporativo
- ✅ **Página de presupuesto** con desglose completo
- ✅ **Tokens seguros** con expiración de 30 días
- ✅ **Panel de administración** para configuración
- ✅ **Shortcodes** para integración flexible
- ✅ **Responsive** y preparado para impresión

## Instalación

1. Sube la carpeta `cuidandote-presupuestos` a `/wp-content/plugins/`
2. Activa el plugin desde **Plugins** en WordPress
3. Ve a **Ajustes → Presupuestos** para configurar

El plugin creará automáticamente:
- Las tablas necesarias en la base de datos
- La página `/presupuesto-cuidadores/` para mostrar presupuestos

## Configuración

### Panel de Administración

En **Ajustes → Presupuestos** puedes configurar:

| Opción | Descripción |
|--------|-------------|
| URL App Nuxt | URL donde está alojado el formulario |
| Email remitente | Dirección de email para envíos |
| Nombre remitente | Nombre que aparece en los emails |

### CORS

El plugin ya incluye configuración CORS para estos dominios:
- `https://cuidandote.webaliza.cat`
- `https://cuidandoteserviciosauxiliares.com`
- `http://localhost:3000`

Para añadir más dominios, edita el array `$allowed_origins` en el archivo principal.

## Endpoint API

### Crear Presupuesto

```
POST /wp-json/cuidandote/v1/presupuesto
```

**Cuerpo de la petición (JSON):**

```json
{
    "contacto": {
        "name": "María García",
        "email": "maria@email.com",
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
```

**Respuesta exitosa (201):**

```json
{
    "success": true,
    "message": "Presupuesto creado correctamente",
    "token": "abc123...",
    "redirect_url": "https://ejemplo.com/presupuesto-cuidadores/?token=abc123...",
    "email_enviado": true,
    "presupuesto": {
        "tipo_servicio": "Externa jornada completa",
        "pago_mensual": 1762.84,
        "horas_semanales": 40
    }
}
```

### Health Check

```
GET /wp-json/cuidandote/v1/health
```

## Shortcodes

### `[cuidandote_presupuesto]`

Muestra el presupuesto detallado (requiere token en URL).

```php
[cuidandote_presupuesto]
[cuidandote_presupuesto class="mi-clase"]
```

### `[cuidandote_formulario]`

Embebe el formulario Nuxt en un iframe.

```php
[cuidandote_formulario]
[cuidandote_formulario src="https://otra-url.com" height="800px"]
```

## Tipos de Servicio

El plugin clasifica automáticamente el tipo de servicio:

| Tipo | Condición |
|------|-----------|
| Interna entre semana | 24h + días L-V |
| Interna fines de semana | 24h + días SAB-DOM |
| Interna parcial | 24h + 1-2 días |
| Externa jornada completa | >20h semanales |
| Externa media jornada | 4-20h semanales |
| Externa por horas | ≤4h semanales |

## Tarifas 2025

### Cuota de Mantenimiento
- Base: 62€
- IVA: 21%
- **Total: 75,02€/mes**

### Comisión de Agencia
- Estándar: 300€ + IVA = **363€**
- 1 día/semana: 50€ + IVA = **60,50€**
- 2º cuidador: 30% descuento

### Tabla Salarial (extracto)

| Horas/sem | Salario Bruto | Salario Neto | SS |
|-----------|--------------|--------------|-----|
| 8h | 276,27€ | 257,38€ | 84,57€ |
| 16h | 552,54€ | 515,28€ | 166,85€ |
| 24h | 828,80€ | 780,25€ | 217,42€ |
| 32h | 1.105,07€ | 1.033,87€ | 318,84€ |
| 40h | 1.381,34€ | 1.293,21€ | 394,61€ |

## Integración con Nuxt

En tu aplicación Nuxt, después de enviar el formulario:

```javascript
const response = await fetch(
  'https://cuidandoteserviciosauxiliares.com/wp-json/cuidandote/v1/presupuesto',
  {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(formData)
  }
);

const result = await response.json();

if (result.success) {
  // Redirigir al presupuesto
  window.top.location.href = result.redirect_url;
}
```

## Flujo Completo

```
1. Usuario completa formulario en Nuxt
   ↓
2. Nuxt envía POST a WordPress API
   ↓
3. WordPress calcula presupuesto (tabla salarial + tarifas)
   ↓
4. Guarda en base de datos con token único
   ↓
5. Envía email HTML con enlace al desglose
   ↓
6. Responde a Nuxt con URL de redirección
   ↓
7. Usuario recibe email y/o es redirigido
   ↓
8. Al hacer clic, ve el presupuesto completo en WordPress
```

## Estructura de Archivos

```
cuidandote-presupuestos/
├── cuidandote-presupuestos.php    # Plugin principal
├── includes/
│   ├── class-cdp-database.php     # Gestión de BD
│   ├── class-cdp-calculator.php   # Cálculo de presupuestos
│   ├── class-cdp-mailer.php       # Envío de emails
│   ├── class-cdp-api.php          # Endpoints REST
│   └── class-cdp-shortcodes.php   # Shortcodes
├── assets/
│   └── css/
│       └── styles.css             # Estilos
└── README.md
```

## Requisitos

- WordPress 5.0+
- PHP 7.4+
- MySQL/MariaDB

## Changelog

### 2.0.0
- Nueva estructura JSON compatible con formulario Nuxt v.alpha-14
- Cálculo automático de horas semanales
- Clasificación inteligente de tipo de servicio
- Soporte para semanas parciales
- Email HTML responsive mejorado
- Panel de administración con estadísticas

### 1.0.0
- Versión inicial

---

**Cuidándote Servicios Auxiliares**  
📞 911 33 68 33  
🌐 https://cuidandoteserviciosauxiliares.com
