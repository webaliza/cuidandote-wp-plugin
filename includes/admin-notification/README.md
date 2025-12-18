# Cuidándote Presupuestos v2.1.0

Plugin de WordPress para gestión automática de presupuestos de servicios de cuidadores.

## Características

- ✅ Recibe datos del formulario Nuxt vía REST API
- ✅ Calcula presupuestos según tabla salarial 2025
- ✅ Clasifica automáticamente el tipo de servicio
- ✅ Envía emails HTML profesionales a clientes
- ✅ **Notificación automática a administradores por email** 🆕
- ✅ Genera tokens únicos con validez de 30 días
- ✅ Página de agradecimiento tras solicitar presupuesto
- ✅ Página de detalle del presupuesto con enlace desde email
- ✅ Panel de administración con estadísticas
- ✅ Configuración flexible desde panel de WordPress

## Flujo de Trabajo

```
1. Usuario completa formulario en Nuxt
   ↓
2. Nuxt envía POST a WordPress API
   ↓
3. WordPress calcula presupuesto (tabla salarial + tarifas)
   ↓
4. Guarda en base de datos con token único
   ↓
5. Envía email HTML al cliente con desglose
   ↓
6. 🆕 Envía notificación automática al administrador
   ↓
7. Redirige a /presupuesto-solicitado/ (página de gracias)
   ↓
8. Cliente recibe email con enlace al desglose
   ↓
9. Administrador recibe notificación con datos del cliente
   ↓
10. Clic en "Detalle Presupuesto" → /presupuesto-cuidadores/?token=xxx
```

## Estructura de Archivos

```
cuidandote-presupuestos/
├── cuidandote-presupuestos.php    # Plugin principal v2.1.0
├── includes/
│   ├── class-cdp-database.php     # Gestión de BD + tabla salarial
│   ├── class-cdp-calculator.php   # Cálculo de presupuestos
│   ├── class-cdp-mailer.php       # Envío de emails a clientes
│   ├── class-cdp-api.php          # Endpoints REST
│   ├── class-cdp-shortcodes.php   # Shortcodes
│   └── admin-notification/        # 🆕 Sistema de notificación admin
│       ├── class-cdp-admin-notification.php
│       ├── class-cdp-admin-notification-migration.php
│       ├── loader.php
│       ├── migration-admin-notification.sql
│       ├── preview-email-admin.html
│       └── README.md
├── assets/
│   └── css/
│       └── styles.css             # Estilos
├── examples/
│   └── nuxt/
│       └── composables/
│           └── useCuidandotePresupuesto.ts
└── README.md
```

## Instalación

1. Sube la carpeta `cuidandote-presupuestos` a `/wp-content/plugins/`
2. Activa el plugin desde **Plugins** en WordPress
3. Ve a **Ajustes → Presupuestos**
4. Pulsa "🔧 Crear / Reparar Tablas" si es necesario
5. (Opcional) Si aparece aviso de migración, pulsa "Ejecutar Migración Ahora"

El plugin crea automáticamente:
- Tablas de base de datos (presupuestos, tabla salarial, tarifas)
- Página `/presupuesto-cuidadores/`
- Página `/presupuesto-solicitado/`

## Endpoint REST API

```
POST https://tu-dominio.com/wp-json/cuidandote/v1/presupuesto
```

### Request

```json
{
  "contacto": {
    "name": "María García",
    "email": "maria@ejemplo.com",
    "phone": "612345678",
    "postalCode": "28001",
    "privacyPolicy": true
  },
  "selectedDateTime": {
    "date": "27-11-2025",
    "time": "10:00"
  },
  "selectedDays": ["LUN", "MAR", "MIE", "JUE", "VIE"],
  "selectedSchedule": [{
    "label": "Misma hora todos los días",
    "value": "same",
    "days": [{
      "day": "same",
      "slots": [{ "from": "09:00", "to": "14:00" }]
    }]
  }],
  "durationType": "larga",
  "selectedWeeks": "4"
}
```

### Response

```json
{
  "success": true,
  "message": "Presupuesto creado correctamente",
  "token": "uuid-token-xxx",
  "redirect_url": "https://tu-dominio.com/presupuesto-solicitado/",
  "email_enviado": true,
  "presupuesto": {
    "tipo_servicio": "Externa jornada completa",
    "pago_mensual": 1147.16,
    "horas_semanales": 25
  }
}
```

## Shortcodes

| Shortcode | Descripción |
|-----------|-------------|
| `[cuidandote_presupuesto]` | Muestra el detalle del presupuesto (requiere token) |
| `[cuidandote_presupuesto_solicitado]` | Página de agradecimiento |
| `[cuidandote_formulario]` | Iframe con el formulario Nuxt |

## Notificaciones a Administradores 🆕

Cuando un cliente solicita un presupuesto, el sistema envía automáticamente un email al administrador con:

### Contenido del email
- 👤 **Datos del cliente**: Nombre, email, teléfono, código postal
- 📋 **Servicio solicitado**: Tipo de servicio, horas semanales
- 💰 **Pago mensual**: Monto calculado del presupuesto
- 📅 **Fecha de solicitud**: Cuándo se generó el presupuesto
- 📞 **Llamada programada**: Si el cliente solicitó ser contactado
- 🔗 **Botón de acción**: Enlace directo al desglose completo

### Configuración

1. Ve a **Ajustes → Presupuestos**
2. Busca el campo **"Email para Notificaciones"**
3. Introduce el email del administrador
4. Guarda los cambios

**Email por defecto**: `info@cuidandoteserviciosauxiliares.com`

### Características técnicas
- ✅ Diseño HTML responsive profesional
- ✅ Compatible con todos los clientes de correo
- ✅ Registro en BD (`admin_notificado`, `admin_notificado_at`)
- ✅ Logs automáticos en WordPress
- ✅ Se activa con el hook `cdp_presupuesto_guardado`

**Documentación completa**: Ver `includes/admin-notification/README.md`

## Configuración CORS

El plugin configura automáticamente CORS para los dominios:
- URL configurada en ajustes
- https://cuidandote.webaliza.cat
- http://localhost:3000 (desarrollo)

## Tablas de Base de Datos

### cdp_presupuestos
Almacena todos los presupuestos generados con sus cálculos.

**Campos principales**:
- Datos de contacto (nombre, email, teléfono, código postal)
- Configuración del servicio (tipo, días, horarios, horas semanales)
- Cálculos (salario bruto/neto, cotización SS, cuotas, comisiones)
- Token único con fecha de expiración
- Estado de emails enviados (`email_enviado`, `admin_notificado`) 🆕
- Timestamps de creación y actualización

### cdp_tabla_salarial
40 registros con salarios brutos, netos y cotización SS para 1-40 horas semanales (2025).

### cdp_tarifas
Tarifas configurables: cuota mantenimiento, comisiones, SAD, incrementos, etc.

## Panel de Administración

Accede desde **Ajustes → Presupuestos** para ver:

- 🗄️ **Estado de las tablas**: Verificación de BD
- 📊 **Estadísticas**: Total de presupuestos y solicitudes diarias
- ⚙️ **Configuración**: URLs, emails, dominios CORS
- 🔌 **Endpoint API**: URL del servicio REST
- 🔧 **Herramientas**: Botón para crear/reparar tablas

## Changelog

### 2.1.0 (Diciembre 2024)
- ✨ **Nuevo:** Sistema de notificación automática a administradores
- 📧 Email profesional HTML a admin con datos del cliente y servicio
- ⚙️ Configuración de email destinatario desde panel de admin
- 🗄️ Nuevos campos `admin_notificado` y `admin_notificado_at` en BD
- 🔄 Migración automática con aviso en panel admin
- 📄 Nueva página de agradecimiento `/presupuesto-solicitado/`
- 🔀 Flujo actualizado: redirección a página de gracias tras solicitar
- 📨 Email cliente mantiene enlace al detalle del presupuesto

### 2.0.0
- Nueva estructura JSON compatible con formulario Nuxt
- Cálculo automático de horas semanales
- Clasificación inteligente de tipo de servicio
- Email HTML responsive
- Sistema de tokens con validez de 30 días
- Panel de administración mejorado
- Shortcodes para integración flexible

### 1.0.0
- Versión inicial del plugin
- API REST básica
- Cálculo de presupuestos
- Envío de emails

## Requisitos

- WordPress 5.0 o superior
- PHP 7.4 o superior
- MySQL 5.7 o superior / MariaDB 10.2 o superior

## Compatibilidad

- ✅ WordPress 5.x, 6.x
- ✅ PHP 7.4, 8.0, 8.1, 8.2
- ✅ Multisite: No probado
- ✅ WooCommerce: Compatible

## Soporte y Documentación

- **Plugin Principal**: Ver este archivo
- **Notificaciones Admin**: `includes/admin-notification/README.md`
- **Ejemplo Nuxt**: `examples/nuxt/composables/useCuidandotePresupuesto.ts`
- **Template WordPress**: Archivo adjunto `WordPress - Template Página con Iframe.txt`

## Desarrollo

### Estructura de clases

- **CDP_Database**: Gestión de tablas y datos
- **CDP_Calculator**: Lógica de cálculo de presupuestos
- **CDP_Mailer**: Envío de emails a clientes
- **CDP_API**: Endpoints REST
- **CDP_Shortcodes**: Shortcodes de WordPress
- **CDP_Admin_Notification**: 🆕 Notificaciones a administradores

### Hooks disponibles

```php
// Después de guardar un presupuesto
do_action('cdp_presupuesto_guardado', $presupuesto_id, $data);

// Antes de enviar email al cliente
apply_filters('cdp_email_data', $email_data, $presupuesto);

// Personalizar template de email
apply_filters('cdp_email_template', $template, $email_data);
```

## Licencia

Propiedad de Cuidándote Servicios Auxiliares  
Desarrollo: Webaliza

---

**Cuidándote Servicios Auxiliares**  
📞 911 33 68 33  
🌐 https://cuidandoteserviciosauxiliares.com  
📧 info@cuidandoteserviciosauxiliares.com

**Última actualización**: Diciembre 2024  
**Versión**: 2.1.0