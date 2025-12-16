# Cuidándote Presupuestos v2.1.0

Plugin de WordPress para gestión automática de presupuestos de servicios de cuidadores.

## Características

- ✅ Recibe datos del formulario Nuxt vía REST API
- ✅ Calcula presupuestos según tabla salarial 2025
- ✅ Clasifica automáticamente el tipo de servicio
- ✅ Envía emails HTML profesionales
- ✅ Genera tokens únicos con validez de 30 días
- ✅ Página de agradecimiento tras solicitar presupuesto
- ✅ Página de detalle del presupuesto con enlace desde email
- ✅ Panel de administración con estadísticas

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
5. Envía email HTML con enlace al desglose
   ↓
6. Redirige a /presupuesto-solicitado/ (página de gracias)
   ↓
7. Usuario recibe email
   ↓
8. Clic en "Detalle Presupuesto"
   ↓
9. Ve /presupuesto-cuidadores/?token=xxx
```

## Estructura de Archivos

```
cuidandote-presupuestos/
├── cuidandote-presupuestos.php    # Plugin principal
├── includes/
│   ├── class-cdp-database.php     # Gestión de BD + tabla salarial
│   ├── class-cdp-calculator.php   # Cálculo de presupuestos
│   ├── class-cdp-mailer.php       # Envío de emails
│   ├── class-cdp-api.php          # Endpoints REST
│   └── class-cdp-shortcodes.php   # Shortcodes
├── assets/
│   └── css/
│       └── styles.css             # Estilos
└── README.md
```

## Instalación

1. Sube la carpeta `cuidandote-presupuestos` a `/wp-content/plugins/`
2. Activa el plugin desde **Plugins** en WordPress
3. Ve a **Ajustes → Presupuestos**
4. Pulsa "🔧 Crear / Reparar Tablas" si es necesario

El plugin crea automáticamente:
- Tablas de base de datos
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

## Configuración CORS

El plugin configura automáticamente CORS para los dominios:
- URL configurada en ajustes
- https://cuidandote.webaliza.cat
- http://localhost:3000 (desarrollo)

## Tablas de Base de Datos

### cdp_presupuestos
Almacena todos los presupuestos generados con sus cálculos.

### cdp_tabla_salarial
40 registros con salarios brutos, netos y cotización SS para 1-40 horas semanales.

### cdp_tarifas
Tarifas configurables: cuota mantenimiento, comisiones, SAD, etc.

## Changelog

### 2.1.0
- Nueva página de agradecimiento `/presupuesto-solicitado/`
- Flujo actualizado: redirección a página de gracias en lugar de detalle
- Email mantiene enlace al detalle del presupuesto

### 2.0.0
- Nueva estructura JSON compatible con formulario Nuxt
- Cálculo automático de horas semanales
- Clasificación inteligente de tipo de servicio
- Email HTML responsive

---

**Cuidándote Servicios Auxiliares**  
📞 911 33 68 33  
🌐 https://cuidandoteserviciosauxiliares.com
